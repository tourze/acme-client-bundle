<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\ACMEClientBundle\Repository\ChallengeRepository;

/**
 * ACME 挑战服务
 *
 * 负责 ACME DNS-01 挑战的处理和状态管理
 *
 * 注意：DNS记录的实际创建和删除需要集成具体的DNS提供商（如CloudFlare、阿里云DNS等）
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'acme_client')]
readonly class ChallengeService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
        private ChallengeRepository $challengeRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 准备 DNS-01 挑战
     */
    public function prepareDnsChallenge(Challenge $challenge): Challenge
    {
        match ($challenge->getType()) {
            ChallengeType::DNS_01 => null,
        };

        [$account, $domain, $token] = $this->extractDnsChallengeRequirements($challenge);

        try {
            $this->setupDnsChallengeData($challenge, $account, $domain, $token);
            $this->saveDnsChallenge($challenge);
            $this->logDnsChallengeSuccess($challenge, $domain);

            return $challenge;
        } catch (\Throwable $e) {
            $this->logDnsChallengeError($challenge, $domain, $e);
            throw new AcmeOperationException("DNS challenge preparation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 提取DNS挑战所需的基本信息
     *
     * @return array{0: Account, 1: string, 2: string}
     */
    private function extractDnsChallengeRequirements(Challenge $challenge): array
    {
        $authorization = $challenge->getAuthorization();
        if (null === $authorization) {
            throw new AcmeOperationException('Challenge has no associated authorization');
        }

        $order = $authorization->getOrder();
        if (null === $order) {
            throw new AcmeOperationException('Authorization has no associated order');
        }

        $account = $order->getAccount();
        if (null === $account) {
            throw new AcmeOperationException('Order has no associated account');
        }

        $domain = $authorization->getIdentifier()?->getValue();
        if (null === $domain || '' === $domain) {
            throw new AcmeOperationException('Domain not found in authorization');
        }

        $token = $challenge->getToken();
        if ('' === $token) {
            throw new AcmeOperationException('Challenge token not found');
        }

        return [$account, $domain, $token];
    }

    /**
     * 设置DNS挑战数据
     */
    private function setupDnsChallengeData(Challenge $challenge, Account $account, string $domain, string $token): void
    {
        $keyAuthorization = $this->generateKeyAuthorization($token, $account->getPrivateKey());
        $challenge->setKeyAuthorization($keyAuthorization);

        $recordValue = $this->calculateDnsRecordValue($keyAuthorization);
        $challenge->setDnsRecordValue($recordValue);

        $recordName = "_acme-challenge.{$domain}";
        $challenge->setDnsRecordName($recordName);
        $challenge->setStatus(ChallengeStatus::PENDING);
    }

    /**
     * 计算DNS记录值
     */
    private function calculateDnsRecordValue(string $keyAuthorization): string
    {
        return $this->base64UrlEncode(hash('sha256', $keyAuthorization, true));
    }

    /**
     * 保存DNS挑战
     */
    private function saveDnsChallenge(Challenge $challenge): void
    {
        $this->entityManager->persist($challenge);
        $this->entityManager->flush();
    }

    /**
     * 记录DNS挑战成功日志
     */
    private function logDnsChallengeSuccess(Challenge $challenge, string $domain): void
    {
        $this->logger->info('DNS challenge prepared successfully', [
            'challenge_id' => $challenge->getId(),
            'domain' => $domain,
            'record_name' => $challenge->getDnsRecordName(),
            'record_value' => $challenge->getDnsRecordValue(),
        ]);
    }

    /**
     * 记录DNS挑战错误日志
     */
    private function logDnsChallengeError(Challenge $challenge, string $domain, \Throwable $e): void
    {
        $this->logger->error('Failed to prepare DNS challenge', [
            'challenge_id' => $challenge->getId(),
            'domain' => $domain,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * 响应挑战（通知ACME服务器开始验证）
     */
    public function respondToChallenge(Challenge $challenge): Challenge
    {
        match ($challenge->getType()) {
            ChallengeType::DNS_01 => null,
        };

        $account = $this->validateChallengeChain($challenge);
        $privateKey = $this->getChallengePrivateKey($account, $challenge);

        try {
            $response = $this->sendChallengeResponse($challenge, $privateKey, $account);
            $this->updateChallengeFromResponse($challenge, $response);
            $this->saveChallengeUpdate($challenge);
            $this->logChallengeResponseSuccess($challenge);

            return $challenge;
        } catch (\Throwable $e) {
            throw new AcmeOperationException("Challenge response failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 验证挑战链完整性
     */
    private function validateChallengeChain(Challenge $challenge): Account
    {
        $authorization = $challenge->getAuthorization();
        if (null === $authorization) {
            throw new AcmeOperationException('Challenge has no associated authorization');
        }

        $order = $authorization->getOrder();
        if (null === $order) {
            throw new AcmeOperationException('Authorization has no associated order');
        }

        $account = $order->getAccount();
        if (null === $account) {
            throw new AcmeOperationException('Order has no associated account');
        }

        return $account;
    }

    /**
     * 获取挑战所需的私钥
     *
     * @return \OpenSSLAsymmetricKey
     */
    private function getChallengePrivateKey(Account $account, Challenge $challenge): \OpenSSLAsymmetricKey
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (false === $privateKey || '' === $challenge->getChallengeUrl()) {
            throw new AcmeOperationException('Invalid challenge or account data');
        }

        return $privateKey;
    }

    /**
     * 发送挑战响应到ACME服务器
     *
     * @param \OpenSSLAsymmetricKey $privateKey
     * @return array<string, mixed>
     */
    private function sendChallengeResponse(Challenge $challenge, \OpenSSLAsymmetricKey $privateKey, Account $account): array
    {
        $payload = []; // 空载荷表示准备好接受验证

        return $this->apiClient->post(
            $challenge->getChallengeUrl(),
            $payload,
            $privateKey,
            $account->getAccountUrl()
        );
    }

    /**
     * 从响应更新挑战状态
     *
     * @param array<string, mixed> $response
     */
    private function updateChallengeFromResponse(Challenge $challenge, array $response): void
    {
        $this->updateChallengeStatus($challenge, $response);
        $this->updateChallengeValidatedTime($challenge, $response);
        $this->updateChallengeError($challenge, $response);
    }

    /**
     * 更新挑战状态
     *
     * @param array<string, mixed> $response
     */
    private function updateChallengeStatus(Challenge $challenge, array $response): void
    {
        if (isset($response['status']) && (is_string($response['status']) || is_int($response['status']))) {
            $status = ChallengeStatus::from($response['status']);
            $challenge->setStatus($status);
        }
    }

    /**
     * 更新挑战验证时间
     *
     * @param array<string, mixed> $response
     */
    private function updateChallengeValidatedTime(Challenge $challenge, array $response): void
    {
        if (isset($response['validated']) && is_string($response['validated'])) {
            $challenge->setValidatedTime(new \DateTimeImmutable($response['validated']));
        }
    }

    /**
     * 更新挑战错误信息
     *
     * @param array<string, mixed> $response
     */
    private function updateChallengeError(Challenge $challenge, array $response): void
    {
        if (isset($response['error']) && is_array($response['error'])) {
            /** @var array<string, mixed> $error */
            $error = $response['error'];
            $challenge->setError($error);
        }
    }

    /**
     * 保存挑战更新
     */
    private function saveChallengeUpdate(Challenge $challenge): void
    {
        $this->entityManager->persist($challenge);
        $this->entityManager->flush();
    }

    /**
     * 记录挑战响应成功日志
     */
    private function logChallengeResponseSuccess(Challenge $challenge): void
    {
        $this->logger->info('Challenge response sent successfully', [
            'challenge_id' => $challenge->getId(),
            'status' => $challenge->getStatus()->value,
        ]);
    }

    /**
     * 检查挑战状态
     *
     * @phpstan-impure
     */
    public function checkChallengeStatus(Challenge $challenge): Challenge
    {
        if ('' === $challenge->getChallengeUrl()) {
            throw new AcmeOperationException('Challenge URL not found');
        }

        try {
            $response = $this->apiClient->get($challenge->getChallengeUrl());

            // 更新挑战状态
            if (isset($response['status']) && (is_string($response['status']) || is_int($response['status']))) {
                $status = ChallengeStatus::from($response['status']);
                $challenge->setStatus($status);
            }

            if (isset($response['validated']) && is_string($response['validated'])) {
                $challenge->setValidatedTime(new \DateTimeImmutable($response['validated']));
            }

            if (isset($response['error']) && is_array($response['error'])) {
                /** @var array<string, mixed> $error */
                $error = $response['error'];
                $challenge->setError($error);
            }

            $this->entityManager->persist($challenge);
            $this->entityManager->flush();

            return $challenge;
        } catch (\Throwable $e) {
            throw new AcmeOperationException("Failed to check challenge status: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 清理DNS记录
     */
    public function cleanupDnsRecord(Challenge $challenge): void
    {
        $recordName = $challenge->getDnsRecordName();
        $recordValue = $challenge->getDnsRecordValue();

        if (null === $recordName || null === $recordValue) {
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
        return ChallengeStatus::VALID === $challenge->getStatus();
    }

    /**
     * 检查挑战是否处理中
     */
    public function isChallengeProcessing(Challenge $challenge): bool
    {
        return ChallengeStatus::PROCESSING === $challenge->getStatus();
    }

    /**
     * 检查挑战是否无效
     */
    public function isChallengeInvalid(Challenge $challenge): bool
    {
        return ChallengeStatus::INVALID === $challenge->getStatus();
    }

    /**
     * 查找特定状态的挑战
     *
     * @return array<Challenge>
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
        $privateKey = $this->validateAndGetPrivateKey($privateKeyPem);
        $rsaDetails = $this->extractRsaDetails($privateKey);
        $thumbprint = $this->generateJwkThumbprint($rsaDetails);

        return "{$token}.{$thumbprint}";
    }

    /**
     * 验证并获取私钥
     *
     * @return \OpenSSLAsymmetricKey
     */
    private function validateAndGetPrivateKey(string $privateKeyPem): \OpenSSLAsymmetricKey
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid private key');
        }

        return $privateKey;
    }

    /**
     * 提取RSA密钥详情
     *
     * @param \OpenSSLAsymmetricKey $privateKey
     * @return array<string, string>
     */
    private function extractRsaDetails(\OpenSSLAsymmetricKey $privateKey): array
    {
        $keyDetails = openssl_pkey_get_details($privateKey);
        $this->validateKeyDetails($keyDetails);

        if (false === $keyDetails) {
            throw new AcmeOperationException('Failed to get key details');
        }

        $this->validateRsaArray($keyDetails);

        $rsaDetails = $keyDetails['rsa'];
        $this->validateRsaComponents($rsaDetails);

        return [
            'e' => $rsaDetails['e'],
            'n' => $rsaDetails['n'],
        ];
    }

    /**
     * 验证密钥详情
     *
     * @param array<string, mixed>|false $keyDetails
     */
    private function validateKeyDetails(array|false $keyDetails): void
    {
        if (false === $keyDetails || OPENSSL_KEYTYPE_RSA !== $keyDetails['type']) {
            throw new AcmeOperationException('Only RSA keys are supported');
        }
    }

    /**
     * 验证RSA数组存在
     *
     * @param array<string, mixed> $keyDetails
     */
    private function validateRsaArray(array $keyDetails): void
    {
        if (!isset($keyDetails['rsa']) || !is_array($keyDetails['rsa'])) {
            throw new AcmeOperationException('Missing RSA key details');
        }
    }

    /**
     * 验证RSA组件
     *
     * @param array<string, mixed> $rsaDetails
     */
    private function validateRsaComponents(array $rsaDetails): void
    {
        if (!isset($rsaDetails['e'], $rsaDetails['n']) || !is_string($rsaDetails['e']) || !is_string($rsaDetails['n'])) {
            throw new AcmeOperationException('Invalid RSA key details');
        }
    }

    /**
     * 生成JWK拇印
     *
     * @param array<string, string> $rsaDetails
     */
    private function generateJwkThumbprint(array $rsaDetails): string
    {
        $jwk = $this->buildJwk($rsaDetails);
        $jwkJson = $this->encodeJwkToJson($jwk);

        return $this->base64UrlEncode(hash('sha256', $jwkJson, true));
    }

    /**
     * 构建JWK
     *
     * @param array<string, string> $rsaDetails
     * @return array<string, string>
     */
    private function buildJwk(array $rsaDetails): array
    {
        return [
            'e' => $this->base64UrlEncode($rsaDetails['e']),
            'kty' => 'RSA',
            'n' => $this->base64UrlEncode($rsaDetails['n']),
        ];
    }

    /**
     * 将JWK编码为JSON
     *
     * @param array<string, string> $jwk
     */
    private function encodeJwkToJson(array $jwk): string
    {
        $jwkJson = json_encode($jwk, JSON_UNESCAPED_SLASHES);
        if (false === $jwkJson) {
            throw new AcmeOperationException('Failed to encode JWK as JSON');
        }

        return $jwkJson;
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
    public function getDns01Challenge(Authorization $authorization): ?Challenge
    {
        foreach ($authorization->getChallenges() as $challenge) {
            return match ($challenge->getType()) {
                ChallengeType::DNS_01 => $challenge,
            };
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

    /**
     * 完成挑战验证流程
     */
    public function completeChallenge(Challenge $challenge): Challenge
    {
        if (ChallengeStatus::PENDING !== $challenge->getStatus()) {
            throw new AcmeOperationException(sprintf('Challenge must be in PENDING status to complete, current status: %s', $challenge->getStatus()->value));
        }

        match ($challenge->getType()) {
            ChallengeType::DNS_01 => $this->prepareDnsChallenge($challenge),
        };

        $this->respondToChallenge($challenge);

        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(5);

            $this->checkChallengeStatus($challenge);

            // checkChallengeStatus 会更新挑战状态，所以这里需要重新检查
            $currentStatus = $challenge->getStatus();
            // checkChallengeStatus 可能更新为任何状态，需要检查所有可能性
            switch ($currentStatus) {
                case ChallengeStatus::VALID:
                    $this->logger->info('Challenge completed successfully', [
                        'challenge_id' => $challenge->getId(),
                        'attempts' => $attempt + 1,
                    ]);

                    match ($challenge->getType()) {
                        ChallengeType::DNS_01 => $this->cleanupDnsRecord($challenge),
                    };

                    return $challenge;

                case ChallengeStatus::INVALID:
                    throw new AcmeOperationException(sprintf('Challenge validation failed: %s', json_encode($challenge->getError())));
                case ChallengeStatus::PENDING:
                case ChallengeStatus::PROCESSING:
                    // Continue waiting
                    break;
            }

            ++$attempt;
        }

        throw new AcmeOperationException('Challenge completion timeout after ' . $maxAttempts . ' attempts');
    }

    /**
     * 获取DNS挑战记录详情
     *
     * @return array<string, mixed>
     */
    public function getDnsChallengeRecord(Challenge $challenge): array
    {
        // 目前只支持 DNS-01 类型，使用 match 确保类型正确
        match ($challenge->getType()) {
            ChallengeType::DNS_01 => null,
        };

        if (null === $challenge->getDnsRecordName() || null === $challenge->getDnsRecordValue()) {
            $this->prepareDnsChallenge($challenge);
        }

        $authorization = $challenge->getAuthorization();
        if (null === $authorization) {
            throw new AcmeOperationException('Challenge authorization is null');
        }
        $identifier = $authorization->getIdentifier();
        $domain = null !== $identifier ? $identifier->getValue() : '';

        return [
            'type' => 'TXT',
            'name' => $challenge->getDnsRecordName() ?? "_acme-challenge.{$domain}",
            'value' => $challenge->getDnsRecordValue() ?? '',
            'ttl' => 300,
            'domain' => $domain,
            'challenge_id' => $challenge->getId(),
        ];
    }

    /**
     * 验证挑战状态
     */
    public function validateChallenge(Challenge $challenge): Challenge
    {
        match ($challenge->getType()) {
            ChallengeType::DNS_01 => $this->logDnsChallenge($challenge),
        };

        $this->checkChallengeStatus($challenge);

        if (ChallengeStatus::PENDING === $challenge->getStatus()) {
            $this->respondToChallenge($challenge);
        }

        sleep(2);
        $this->checkChallengeStatus($challenge);

        $this->logger->info('Challenge validation completed', [
            'challenge_id' => $challenge->getId(),
            'status' => $challenge->getStatus()->value,
        ]);

        return $challenge;
    }

    /**
     * 记录DNS挑战日志
     */
    private function logDnsChallenge(Challenge $challenge): void
    {
        $dnsRecord = $this->getDnsChallengeRecord($challenge);

        $this->logger->info('Validating DNS challenge', [
            'challenge_id' => $challenge->getId(),
            'dns_record' => $dnsRecord,
        ]);
    }

    /**
     * 按类型查找挑战
     *
     * @return array<Challenge>
     */
    public function findChallengesByType(ChallengeType $type): array
    {
        return $this->challengeRepository->findBy(['type' => $type]);
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
