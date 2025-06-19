<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\AccountRepository;

/**
 * ACME 账户服务
 *
 * 负责 ACME 账户的注册、密钥管理、状态同步等操作
 */
class AccountService
{
    public function __construct(
        private readonly AcmeApiClient $apiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accountRepository,
        private readonly LoggerInterface $logger,
        private readonly string $acmeServerUrl
    ) {}

    /**
     * 注册新的 ACME 账户
     */
    public function registerAccount(
        array $contacts,
        bool $termsOfServiceAgreed = true,
        ?string $privateKeyPem = null
    ): Account {
        // 生成或使用提供的私钥
        if ($privateKeyPem === null) {
            $privateKeyPem = $this->generateAccountKey();
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new AcmeClientException('Invalid private key provided');
        }

        // 准备注册载荷
        $payload = [
            'contact' => $contacts,
            'termsOfServiceAgreed' => $termsOfServiceAgreed,
        ];

        try {
            // 发送注册请求
            $response = $this->apiClient->post('newAccount', $payload, $privateKey);

            // 创建账户实体
            $account = new Account();
            $account->setAcmeServerUrl($this->acmeServerUrl);
            $account->setContacts($contacts);
            $account->setPrivateKey($privateKeyPem);
            $account->setPublicKeyJwk($this->createJwkFromPrivateKey($privateKeyPem));
            $account->setStatus(AccountStatus::VALID);
            $account->setAccountUrl($this->getLocationFromResponse($response));
            $account->setTermsOfServiceAgreed($termsOfServiceAgreed);

            $this->entityManager->persist($account);
            $this->entityManager->flush();

            $this->logger->info('ACME account registered successfully', [
                'account_id' => $account->getId(),
                'contacts' => $contacts,
                'server_url' => $this->acmeServerUrl,
            ]);

            return $account;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register ACME account', [
                'contacts' => $contacts,
                'error' => $e->getMessage(),
            ]);

            throw new AcmeClientException(
                "Account registration failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 获取现有账户信息
     */
    public function getAccountInfo(Account $account): array
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (!$privateKey) {
            throw new AcmeClientException('Invalid account private key');
        }

        if ($account->getAccountUrl() === null) {
            throw new AcmeClientException('Account URL not found');
        }

        try {
            // 发送获取账户信息的请求
            $response = $this->apiClient->post(
                $account->getAccountUrl(),
                [],
                $privateKey,
                $account->getAccountUrl()
            );

            return $response;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Failed to get account info: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 更新账户信息
     */
    public function updateAccount(Account $account, array $contacts): Account
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (!$privateKey) {
            throw new AcmeClientException('Invalid account private key');
        }

        if ($account->getAccountUrl() === null) {
            throw new AcmeClientException('Account URL not found');
        }

        $payload = [
            'contact' => $contacts,
        ];

        try {
            $response = $this->apiClient->post(
                $account->getAccountUrl(),
                $payload,
                $privateKey,
                $account->getAccountUrl()
            );

            // 更新本地账户信息
            $account->setContacts($contacts);

            $this->entityManager->persist($account);
            $this->entityManager->flush();

            $this->logger->info('ACME account updated successfully', [
                'account_id' => $account->getId(),
                'contacts' => $contacts,
            ]);

            return $account;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Account update failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 停用账户
     */
    public function deactivateAccount(Account $account): Account
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (!$privateKey) {
            throw new AcmeClientException('Invalid account private key');
        }

        if ($account->getAccountUrl() === null) {
            throw new AcmeClientException('Account URL not found');
        }

        $payload = [
            'status' => 'deactivated',
        ];

        try {
            $response = $this->apiClient->post(
                $account->getAccountUrl(),
                $payload,
                $privateKey,
                $account->getAccountUrl()
            );

            // 更新本地状态
            $account->setStatus(AccountStatus::DEACTIVATED);

            $this->entityManager->persist($account);
            $this->entityManager->flush();

            $this->logger->info('ACME account deactivated successfully', [
                'account_id' => $account->getId(),
                'status' => AccountStatus::DEACTIVATED->value,
            ]);

            return $account;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Account deactivation failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 根据服务器URL查找账户
     */
    public function findAccountsByServerUrl(string $serverUrl): array
    {
        return $this->accountRepository->findBy(['acmeServerUrl' => $serverUrl]);
    }

    /**
     * 根据状态查找账户
     */
    public function findAccountsByStatus(AccountStatus $status): array
    {
        return $this->accountRepository->findBy(['status' => $status]);
    }

    /**
     * 验证账户是否有效
     */
    public function isAccountValid(Account $account): bool
    {
        return $account->getStatus() === AccountStatus::VALID;
    }

    /**
     * 生成账户私钥
     */
    private function generateAccountKey(int $bits = 2048): string
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        if (!$privateKey) {
            throw new AcmeClientException('Failed to generate private key');
        }

        $privateKeyPem = '';
        if (!openssl_pkey_export($privateKey, $privateKeyPem)) {
            throw new AcmeClientException('Failed to export private key');
        }

        return $privateKeyPem;
    }

    /**
     * 从私钥创建JWK
     */
    private function createJwkFromPrivateKey(string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new AcmeClientException('Invalid private key');
        }

        $keyDetails = openssl_pkey_get_details($privateKey);
        if (!$keyDetails || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new AcmeClientException('Only RSA keys are supported');
        }

        $jwk = [
            'kty' => 'RSA',
            'n' => rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '='),
        ];

        return json_encode($jwk);
    }

    /**
     * 从响应中获取 Location 头
     * 注意：这里需要修改 AcmeApiClient 来返回响应头信息
     */
    private function getLocationFromResponse(array $response): ?string
    {
        // 临时实现，实际应该从HTTP响应头获取
        return $response['location'] ?? null;
    }

    /**
     * 通过邮箱查找账户
     */
    public function findAccountByEmail(string $email, ?string $serverUrl = null): ?Account
    {
        $qb = $this->accountRepository->createQueryBuilder('a')
            ->where('JSON_SEARCH(a.contacts, \'one\', :email) IS NOT NULL')
            ->setParameter('email', "mailto:{$email}");

        if ($serverUrl !== null) {
            $qb->andWhere('a.acmeServerUrl = :serverUrl')
                ->setParameter('serverUrl', $serverUrl);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * 注册账户（简化版本，接受邮箱地址）
     */
    public function registerAccountByEmail(
        string $email,
        string $serverUrl,
        int $keySize = 2048,
        bool $termsOfServiceAgreed = true
    ): Account {
        $contacts = ["mailto:{$email}"];

        // 临时更新ACME服务器URL用于注册
        $originalServerUrl = $this->acmeServerUrl;

        // 通过反射修改私有属性
        $reflection = new \ReflectionClass($this);
        $property = $reflection->getProperty('acmeServerUrl');
        $property->setAccessible(true);
        $property->setValue($this, $serverUrl);

        try {
            $account = $this->registerAccount($contacts, $termsOfServiceAgreed);
            return $account;
        } finally {
            // 恢复原始服务器URL
            $property->setValue($this, $originalServerUrl);
        }
    }

    /**
     * 从账户的联系信息中提取第一个邮箱地址
     */
    public function getEmailFromAccount(Account $account): ?string
    {
        $contacts = $account->getContacts();
        if ($contacts === null || $contacts === []) {
            return null;
        }

        foreach ($contacts as $contact) {
            if (is_string($contact) && str_starts_with($contact, 'mailto:')) {
                return substr($contact, 7); // 移除 "mailto:" 前缀
            }
        }

        return null;
    }

    /**
     * 通过ID获取账户
     */
    public function getAccountById(int $id): ?Account
    {
        return $this->accountRepository->find($id);
    }
}
