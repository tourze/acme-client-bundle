<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;

/**
 * ACME 授权服务
 *
 * 负责 ACME 授权的获取、状态同步、挑战管理等操作
 */
class AuthorizationService
{
    public function __construct(
        private readonly AcmeApiClient $apiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationRepository $authorizationRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * 获取授权详细信息
     */
    public function fetchAuthorizationDetails(Authorization $authorization): Authorization
    {
        $order = $authorization->getOrder();
        $account = $order->getAccount();
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if (!$privateKey || !$authorization->getAuthorizationUrl()) {
            throw new AcmeClientException('Invalid authorization or account data');
        }

        try {
            $response = $this->apiClient->get($authorization->getAuthorizationUrl());

            // 更新授权状态
            if (isset($response['status'])) {
                $status = AuthorizationStatus::from($response['status']);
                $authorization->setStatus($status);
            }

            // 域名信息通过Identifier管理，这里不需要单独更新

            // 更新过期时间
            if (isset($response['expires'])) {
                $authorization->setExpiresTime(new \DateTimeImmutable($response['expires']));
            }

            // 处理挑战
            if (isset($response['challenges'])) {
                $this->processAuthorizationChallenges($authorization, $response['challenges']);
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

            throw new AcmeClientException(
                "Failed to fetch authorization details: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 查找特定域名的授权
     */
    public function findAuthorizationByDomain(string $domain): ?Authorization
    {
        return $this->authorizationRepository->findOneBy(['domain' => $domain]);
    }

    /**
     * 查找特定状态的授权
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
        return $authorization->getStatus() === AuthorizationStatus::VALID
            && !$this->isAuthorizationExpired($authorization);
    }

    /**
     * 检查授权是否已过期
     */
    public function isAuthorizationExpired(Authorization $authorization): bool
    {
        $expiresTime = $authorization->getExpiresTime();
        if ($expiresTime === null) {
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
            if ($challenge->getType() === ChallengeType::DNS_01) {
                return $challenge;
            }
        }

        return null;
    }

    /**
     * 停用授权
     */
    public function deactivateAuthorization(Authorization $authorization): Authorization
    {
        $order = $authorization->getOrder();
        $account = $order->getAccount();
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if (!$privateKey || !$authorization->getAuthorizationUrl()) {
            throw new AcmeClientException('Invalid authorization or account data');
        }

        $payload = [
            'status' => 'deactivated',
        ];

        try {
            $response = $this->apiClient->post(
                $authorization->getAuthorizationUrl(),
                $payload,
                $privateKey,
                $account->getAccountUrl()
            );

            // 更新授权状态
            if (isset($response['status'])) {
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
            throw new AcmeClientException(
                "Authorization deactivation failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 处理授权的挑战列表
     */
    private function processAuthorizationChallenges(Authorization $authorization, array $challengesData): void
    {
        foreach ($challengesData as $challengeData) {
            // 只处理DNS-01挑战
            if ($challengeData['type'] !== 'dns-01') {
                continue;
            }

            // 查找现有挑战或创建新的
            $challenge = $this->findOrCreateChallenge($authorization, $challengeData);

            // 更新挑战信息
            $challenge->setType(ChallengeType::DNS_01);
            $challenge->setToken($challengeData['token'] ?? '');
            $challenge->setChallengeUrl($challengeData['url'] ?? '');

            if (isset($challengeData['status'])) {
                $challenge->setStatus(ChallengeStatus::from($challengeData['status']));
            }

            if (isset($challengeData['validated'])) {
                $challenge->setValidatedTime(new \DateTimeImmutable($challengeData['validated']));
            }

            if (isset($challengeData['error'])) {
                $challenge->setError(json_encode($challengeData['error']) ?: null);
            }

            $this->entityManager->persist($challenge);
        }
    }

    /**
     * 查找或创建挑战
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
}
