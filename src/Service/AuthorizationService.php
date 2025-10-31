<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;

/**
 * ACME 授权服务
 *
 * 负责 ACME 授权的获取、状态同步、挑战管理等操作
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'acme_client')]
readonly class AuthorizationService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
        private AuthorizationRepository $authorizationRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 获取授权详细信息
     */
    public function fetchAuthorizationDetails(Authorization $authorization): Authorization
    {
        $order = $authorization->getOrder();
        if (null === $order) {
            throw new AcmeOperationException('Authorization has no associated order');
        }

        $account = $order->getAccount();
        if (null === $account) {
            throw new AcmeOperationException('Order has no associated account');
        }

        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if (false === $privateKey || '' === $authorization->getAuthorizationUrl()) {
            throw new AcmeOperationException('Invalid authorization or account data');
        }

        try {
            $response = $this->apiClient->get($authorization->getAuthorizationUrl());

            // 更新授权状态
            if (isset($response['status']) && is_string($response['status'])) {
                $status = AuthorizationStatus::from($response['status']);
                $authorization->setStatus($status);
            }

            // 域名信息通过Identifier管理，这里不需要单独更新

            // 更新过期时间
            if (isset($response['expires']) && is_string($response['expires'])) {
                $authorization->setExpiresTime(new \DateTimeImmutable($response['expires']));
            }

            // 处理挑战
            if (isset($response['challenges']) && is_array($response['challenges'])) {
                /** @var array<array{type?: string, token?: string, url?: string, status?: string, validated?: string}> $challenges */
                $challenges = $response['challenges'];
                $this->processAuthorizationChallenges($authorization, $challenges);
            }

            $this->entityManager->persist($authorization);
            $this->entityManager->flush();

            $this->logger->info('Authorization details fetched successfully', [
                'authorization_id' => $authorization->getId(),
                'domain' => $authorization->getIdentifier()?->getValue(),
                'status' => $authorization->getStatus()->value,
            ]);

            return $authorization;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch authorization details', [
                'authorization_id' => $authorization->getId(),
                'error' => $e->getMessage(),
            ]);

            throw new AcmeOperationException("Failed to fetch authorization details: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 查找特定域名的授权
     */
    public function findAuthorizationByDomain(string $domain): ?Authorization
    {
        $qb = $this->authorizationRepository->createQueryBuilder('a');
        $qb->join('a.identifier', 'i')
            ->where('i.value = :domain')
            ->setParameter('domain', $domain)
            ->setMaxResults(1)
        ;

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof Authorization ? $result : null;
    }

    /**
     * 查找特定状态的授权
     */
    /**
     * @return Authorization[]
     */
    public function findAuthorizationsByStatus(AuthorizationStatus $status): array
    {
        return $this->authorizationRepository->findBy(['status' => $status]);
    }

    /**
     * 检查授权是否有效
     */
    public function isAuthorizationValid(Authorization $authorization): bool
    {
        return AuthorizationStatus::VALID === $authorization->getStatus()
            && !$this->isAuthorizationExpired($authorization);
    }

    /**
     * 检查授权是否已过期
     */
    public function isAuthorizationExpired(Authorization $authorization): bool
    {
        $expiresTime = $authorization->getExpiresTime();
        if (null === $expiresTime) {
            return false;
        }

        return $expiresTime < new \DateTimeImmutable();
    }

    /**
     * 获取授权的DNS-01挑战
     */
    public function getDnsChallenge(Authorization $authorization): ?Challenge
    {
        foreach ($authorization->getChallenges() as $challenge) {
            return match ($challenge->getType()) {
                ChallengeType::DNS_01 => $challenge,
            };
        }

        return null;
    }

    /**
     * 停用授权
     */
    public function deactivateAuthorization(Authorization $authorization): Authorization
    {
        $order = $authorization->getOrder();
        if (null === $order) {
            throw new AcmeOperationException('Authorization has no associated order');
        }

        $account = $order->getAccount();
        if (null === $account) {
            throw new AcmeOperationException('Order has no associated account');
        }

        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if (false === $privateKey || '' === $authorization->getAuthorizationUrl()) {
            throw new AcmeOperationException('Invalid authorization or account data');
        }

        $payload = [
            'status' => 'deactivated',
        ];

        $accountUrl = $account->getAccountUrl();
        if ('' === $accountUrl) {
            throw new AcmeOperationException('Account URL is empty');
        }

        try {
            $response = $this->apiClient->post(
                $authorization->getAuthorizationUrl(),
                $payload,
                $privateKey,
                $accountUrl
            );

            // 更新授权状态
            if (isset($response['status']) && (is_string($response['status']) || is_int($response['status']))) {
                $status = AuthorizationStatus::from($response['status']);
                $authorization->setStatus($status);
            }

            $this->entityManager->persist($authorization);
            $this->entityManager->flush();

            $this->logger->info('Authorization deactivated successfully', [
                'authorization_id' => $authorization->getId(),
                'domain' => $authorization->getIdentifier()?->getValue(),
            ]);

            return $authorization;
        } catch (\Throwable $e) {
            throw new AcmeOperationException("Authorization deactivation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 处理授权的挑战列表
     */
    /**
     * @param array<array{type?: string, token?: string, url?: string, status?: string, validated?: string}> $challengesData
     */
    private function processAuthorizationChallenges(Authorization $authorization, array $challengesData): void
    {
        foreach ($challengesData as $challengeData) {
            if ($this->shouldSkipChallenge($challengeData)) {
                continue;
            }

            $challenge = $this->findOrCreateChallenge($authorization, $challengeData);
            $this->updateChallengeFromData($challenge, $challengeData, $authorization);
            $this->entityManager->persist($challenge);
        }
    }

    /**
     * @param array{type?: string} $challengeData
     */
    private function shouldSkipChallenge(array $challengeData): bool
    {
        return !isset($challengeData['type']) || 'dns-01' !== $challengeData['type'];
    }

    /**
     * @param array{token?: string, url?: string} $challengeData
     */
    private function updateChallengeFromData(Challenge $challenge, array $challengeData, Authorization $authorization): void
    {
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setToken($challengeData['token'] ?? '');
        $challenge->setChallengeUrl($challengeData['url'] ?? '');

        $this->setChallengeKeyAuthorization($challenge, $authorization);
        $this->updateChallengeStatusAndTime($challenge, $challengeData);
    }

    private function setChallengeKeyAuthorization(Challenge $challenge, Authorization $authorization): void
    {
        $token = $challenge->getToken();
        $account = $authorization->getOrder()?->getAccount();

        if (null !== $account && '' !== $token) {
            $keyAuthorization = $this->generateKeyAuthorization($token, $account->getPublicKeyJwk());
            $challenge->setKeyAuthorization($keyAuthorization);
        } else {
            $challenge->setKeyAuthorization('' !== $token ? $token : 'default-key-auth');
        }
    }

    /**
     * @param array{status?: string, validated?: string, error?: array<string, mixed>} $challengeData
     */
    private function updateChallengeStatusAndTime(Challenge $challenge, array $challengeData): void
    {
        // PHPDoc 已明确 status 和 validated 的类型，isset 之后类型已确定
        if (isset($challengeData['status'])) {
            $challenge->setStatus(ChallengeStatus::from($challengeData['status']));
        }

        if (isset($challengeData['validated'])) {
            $challenge->setValidatedTime(new \DateTimeImmutable($challengeData['validated']));
        }

        if (isset($challengeData['error'])) {
            /** @var array<string, mixed> $error */
            $error = $challengeData['error'];
            $challenge->setError($error);
        }
    }

    /**
     * 查找或创建挑战
     * @param array{url?: string, token?: string, type?: string, status?: string, validated?: string} $challengeData
     */
    private function findOrCreateChallenge(Authorization $authorization, array $challengeData): Challenge
    {
        // 尝试根据URL查找现有挑战
        $challengeUrl = $challengeData['url'] ?? '';

        foreach ($authorization->getChallenges() as $existingChallenge) {
            if ($existingChallenge->getChallengeUrl() === $challengeUrl) {
                return $existingChallenge;
            }
        }

        // 创建新挑战
        $challenge = new Challenge();
        $challenge->setAuthorization($authorization);
        $authorization->addChallenge($challenge);

        return $challenge;
    }

    private function generateKeyAuthorization(string $token, string $publicKeyJwk): string
    {
        $thumbprint = $this->generateJwkThumbprint($publicKeyJwk);

        return "{$token}.{$thumbprint}";
    }

    private function generateJwkThumbprint(string $publicKeyJwk): string
    {
        $jwk = json_decode($publicKeyJwk, true);
        if (false === $jwk || null === $jwk || !is_array($jwk)) {
            throw new AcmeOperationException('Invalid public key JWK format');
        }

        if (!isset($jwk['e'], $jwk['kty'], $jwk['n']) || !is_string($jwk['e']) || !is_string($jwk['kty']) || !is_string($jwk['n'])) {
            throw new AcmeOperationException('Missing or invalid JWK fields');
        }

        // 创建规范的 JWK（只包含必需字段，按字母顺序排列）
        $canonicalJwk = [
            'e' => $jwk['e'],
            'kty' => $jwk['kty'],
            'n' => $jwk['n'],
        ];

        $jwkJson = json_encode($canonicalJwk, JSON_UNESCAPED_SLASHES);
        if (false === $jwkJson) {
            throw new AcmeOperationException('Failed to encode JWK to JSON');
        }

        return $this->base64UrlEncode(hash('sha256', $jwkJson, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
