<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\ChallengeRepository;


/**
 * ACME 挑战服务
 *
 * 负责 ACME DNS-01 挑战的处理和状态管理
 *
 * 注意：DNS记录的实际创建和删除需要集成具体的DNS提供商（如CloudFlare、阿里云DNS等）
 */
class ChallengeService
{
    public function __construct(
        private readonly AcmeApiClient $apiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChallengeRepository $challengeRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * 准备 DNS-01 挑战
     */
    public function prepareDnsChallenge(Challenge $challenge): Challenge
    {
        if ($challenge->getType() !== ChallengeType::DNS_01) {
            throw new AcmeClientException('Only DNS-01 challenges are supported');
        }

        $authorization = $challenge->getAuthorization();
        $order = $authorization->getOrder();
        $account = $order->getAccount();

        $domain = $authorization->getIdentifier()?->getValue();
        if (!$domain) {
            throw new AcmeClientException('Domain not found in authorization');
        }

        $token = $challenge->getToken();
        if (!$token) {
            throw new AcmeClientException('Challenge token not found');
        }

        try {
            // 生成挑战响应
            $keyAuthorization = $this->generateKeyAuthorization($token, $account->getPrivateKey());
            $challenge->setKeyAuthorization($keyAuthorization);

            // 计算DNS记录值
            $recordValue = $this->base64UrlEncode(hash('sha256', $keyAuthorization, true));
            $challenge->setDnsRecordValue($recordValue);

            // 设置DNS记录名称
            $recordName = "_acme-challenge.{$domain}";
            $challenge->setDnsRecordName($recordName);

            // TODO: 集成DNS提供商来创建DNS记录
            // $this->createDnsRecord($recordName, $recordValue);

            $challenge->setStatus(ChallengeStatus::PENDING);
            $this->entityManager->persist($challenge);
            $this->entityManager->flush();

            $this->logger->info('DNS challenge prepared successfully', [
                'challenge_id' => $challenge->getId(),
                'domain' => $domain,
                'record_name' => $recordName,
                'record_value' => $recordValue,
            ]);

            return $challenge;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to prepare DNS challenge', [
                'challenge_id' => $challenge->getId(),
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            throw new AcmeClientException(
                "DNS challenge preparation failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 响应挑战（通知ACME服务器开始验证）
     */
    public function respondToChallenge(Challenge $challenge): Challenge
    {
        if ($challenge->getType() !== ChallengeType::DNS_01) {
            throw new AcmeClientException('Only DNS-01 challenges are supported');
        }

        $authorization = $challenge->getAuthorization();
        $order = $authorization->getOrder();
        $account = $order->getAccount();

        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (!$privateKey || !$challenge->getChallengeUrl()) {
            throw new AcmeClientException('Invalid challenge or account data');
        }

        // 空载荷表示准备好接受验证
        $payload = [];

        try {
            $response = $this->apiClient->post(
                $challenge->getChallengeUrl(),
                $payload,
                $privateKey,
                $account->getAccountUrl()
            );

            // 更新挑战状态
            if (isset($response['status'])) {
                $status = ChallengeStatus::from($response['status']);
                $challenge->setStatus($status);
            }

            if (isset($response['validated'])) {
                $challenge->setValidatedTime(new \DateTimeImmutable($response['validated']));
            }

            if (isset($response['error'])) {
                $challenge->setError(json_encode($response['error']) ?: null);
            }

            $this->entityManager->persist($challenge);
            $this->entityManager->flush();

            $this->logger->info('Challenge response sent successfully', [
                'challenge_id' => $challenge->getId(),
                'status' => $challenge->getStatus()->value,
            ]);

            return $challenge;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Challenge response failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 检查挑战状态
     */
    public function checkChallengeStatus(Challenge $challenge): Challenge
    {
        if (!$challenge->getChallengeUrl()) {
            throw new AcmeClientException('Challenge URL not found');
        }

        try {
            $response = $this->apiClient->get($challenge->getChallengeUrl());

            // 更新挑战状态
            if (isset($response['status'])) {
                $status = ChallengeStatus::from($response['status']);
                $challenge->setStatus($status);
            }

            if (isset($response['validated'])) {
                $challenge->setValidatedTime(new \DateTimeImmutable($response['validated']));
            }

            if (isset($response['error'])) {
                $challenge->setError(json_encode($response['error']) ?: null);
            }

            $this->entityManager->persist($challenge);
            $this->entityManager->flush();

            return $challenge;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Failed to check challenge status: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 清理DNS记录
     */
    public function cleanupDnsRecord(Challenge $challenge): void
    {
        $recordName = $challenge->getDnsRecordName();
        $recordValue = $challenge->getDnsRecordValue();

        if (!$recordName || !$recordValue) {
            $this->logger->warning('DNS record name or value not found for cleanup', [
                'challenge_id' => $challenge->getId(),
            ]);
            return;
        }

        // TODO: 集成DNS提供商来删除DNS记录
        // try {
        //     $this->deleteDnsRecord($recordName, $recordValue);
        //     
        //     $this->logger->info('DNS record cleaned up successfully', [
        //         'challenge_id' => $challenge->getId(),
        //         'record_name' => $recordName,
        //     ]);
        // } catch (\Throwable $e) {
        //     $this->logger->error('Failed to cleanup DNS record', [
        //         'challenge_id' => $challenge->getId(),
        //         'record_name' => $recordName,
        //         'error' => $e->getMessage(),
        //     ]);
        //     
        //     // 清理失败不应该阻止整个流程
        // }

        $this->logger->info('DNS record cleanup requested', [
            'challenge_id' => $challenge->getId(),
            'record_name' => $recordName,
            'record_value' => $recordValue,
        ]);
    }

    /**
     * 检查挑战是否有效
     */
    public function isChallengeValid(Challenge $challenge): bool
    {
        return $challenge->getStatus() === ChallengeStatus::VALID;
    }

    /**
     * 检查挑战是否处理中
     */
    public function isChallengeProcessing(Challenge $challenge): bool
    {
        return $challenge->getStatus() === ChallengeStatus::PROCESSING;
    }

    /**
     * 检查挑战是否无效
     */
    public function isChallengeInvalid(Challenge $challenge): bool
    {
        return $challenge->getStatus() === ChallengeStatus::INVALID;
    }

    /**
     * 查找特定状态的挑战
     */
    public function findChallengesByStatus(ChallengeStatus $status): array
    {
        return $this->challengeRepository->findBy(['status' => $status]);
    }

    /**
     * 生成密钥授权字符串
     */
    private function generateKeyAuthorization(string $token, string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new AcmeClientException('Invalid private key');
        }

        $keyDetails = openssl_pkey_get_details($privateKey);
        if (!$keyDetails || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new AcmeClientException('Only RSA keys are supported');
        }

        // 创建JWK拇指印
        $jwk = [
            'e' => $this->base64UrlEncode($keyDetails['rsa']['e']),
            'kty' => 'RSA',
            'n' => $this->base64UrlEncode($keyDetails['rsa']['n']),
        ];

        $jwkJson = json_encode($jwk, JSON_UNESCAPED_SLASHES);
        $thumbprint = $this->base64UrlEncode(hash('sha256', $jwkJson, true));

        return "{$token}.{$thumbprint}";
    }

    /**
     * Base64 URL 编码
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * 从授权中获取DNS-01质询
     */
    public function getDns01Challenge(\Tourze\ACMEClientBundle\Entity\Authorization $authorization): ?Challenge
    {
        foreach ($authorization->getChallenges() as $challenge) {
            if ($challenge->getType() === ChallengeType::DNS_01) {
                return $challenge;
            }
        }
        return null;
    }

    /**
     * 设置DNS记录
     */
    public function setupDnsRecord(Challenge $challenge): void
    {
        // 先准备DNS质询
        $this->prepareDnsChallenge($challenge);

        // TODO: 这里应该集成实际的DNS提供商来创建DNS记录
        // 目前仅记录日志
        $this->logger->info('DNS record setup requested', [
            'challenge_id' => $challenge->getId(),
            'record_name' => $challenge->getDnsRecordName(),
            'record_value' => $challenge->getDnsRecordValue(),
        ]);
    }

    /**
     * 启动质询验证
     */
    public function startChallenge(Challenge $challenge): Challenge
    {
        return $this->respondToChallenge($challenge);
    }

    // TODO: 实现DNS记录管理方法
    // /**
    //  * 创建DNS记录
    //  */
    // private function createDnsRecord(string $name, string $value): void
    // {
    //     // 集成具体的DNS提供商实现
    // }
    //
    // /**
    //  * 删除DNS记录
    //  */
    // private function deleteDnsRecord(string $name, string $value): void
    // {
    //     // 集成具体的DNS提供商实现
    // }
}
