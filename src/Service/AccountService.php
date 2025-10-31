<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\ACMEClientBundle\Repository\AccountRepository;

/**
 * ACME 账户服务
 *
 * 负责 ACME 账户的注册、密钥管理、状态同步等操作
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'acme_client')]
readonly class AccountService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
        private AccountRepository $accountRepository,
        private LoggerInterface $logger,
        private string $acmeServerUrl,
    ) {
    }

    /**
     * 注册新的 ACME 账户
     *
     * @param array<string> $contacts
     */
    public function registerAccount(
        array $contacts,
        bool $termsOfServiceAgreed = true,
        ?string $privateKeyPem = null,
    ): Account {
        // 生成或使用提供的私钥
        if (null === $privateKeyPem) {
            $privateKeyPem = $this->generateAccountKey();
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid private key provided');
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

            throw new AcmeOperationException("Account registration failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 获取现有账户信息
     *
     * @return array<string, mixed>
     */
    public function getAccountInfo(Account $account): array
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid account private key');
        }

        if (null === $account->getAccountUrl()) {
            throw new AcmeOperationException('Account URL not found');
        }

        try {
            // 发送获取账户信息的请求
            return $this->apiClient->post(
                $account->getAccountUrl(),
                [],
                $privateKey,
                $account->getAccountUrl()
            );
        } catch (\Throwable $e) {
            throw new AcmeOperationException("Failed to get account info: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 更新账户信息
     *
     * @param array<string> $contacts
     */
    public function updateAccount(Account $account, array $contacts): Account
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid account private key');
        }

        if (null === $account->getAccountUrl()) {
            throw new AcmeOperationException('Account URL not found');
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
            throw new AcmeOperationException("Account update failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 停用账户
     */
    public function deactivateAccount(Account $account): Account
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid account private key');
        }

        if (null === $account->getAccountUrl()) {
            throw new AcmeOperationException('Account URL not found');
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
            throw new AcmeOperationException("Account deactivation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 根据服务器URL查找账户
     *
     * @return array<Account>
     */
    public function findAccountsByServerUrl(string $serverUrl): array
    {
        return $this->accountRepository->findBy(['acmeServerUrl' => $serverUrl]);
    }

    /**
     * 根据状态查找账户
     *
     * @return array<Account>
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
        return AccountStatus::VALID === $account->getStatus();
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
        if (false === $privateKey) {
            throw new AcmeOperationException('Failed to generate private key');
        }

        $privateKeyPem = '';
        $exportResult = openssl_pkey_export($privateKey, $privateKeyPem);
        if (false === $exportResult || !is_string($privateKeyPem) || '' === $privateKeyPem) {
            throw new AcmeOperationException('Failed to export private key');
        }

        return $privateKeyPem;
    }

    /**
     * 从私钥创建JWK
     */
    private function createJwkFromPrivateKey(string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid private key');
        }

        $keyDetails = openssl_pkey_get_details($privateKey);
        if (false === $keyDetails || OPENSSL_KEYTYPE_RSA !== $keyDetails['type']) {
            throw new AcmeOperationException('Only RSA keys are supported');
        }

        if (!isset($keyDetails['rsa']) || !is_array($keyDetails['rsa'])) {
            throw new AcmeOperationException('Missing RSA key details');
        }

        $rsaDetails = $keyDetails['rsa'];
        if (!isset($rsaDetails['n'], $rsaDetails['e']) || !is_string($rsaDetails['n']) || !is_string($rsaDetails['e'])) {
            throw new AcmeOperationException('Invalid RSA key details');
        }

        $jwk = [
            'kty' => 'RSA',
            'n' => rtrim(strtr(base64_encode($rsaDetails['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($rsaDetails['e']), '+/', '-_'), '='),
        ];

        $result = json_encode($jwk);
        if (false === $result) {
            throw new AcmeOperationException('Failed to encode JWK as JSON');
        }

        return $result;
    }

    /**
     * 从响应中获取 Location 头
     * 注意：这里需要修改 AcmeApiClient 来返回响应头信息
     *
     * @param array<string, mixed> $response
     * @return string|null
     */
    private function getLocationFromResponse(array $response): ?string
    {
        // 临时实现，实际应该从HTTP响应头获取
        $location = $response['location'] ?? null;

        return is_string($location) ? $location : null;
    }

    /**
     * 更新账户联系方式
     *
     * @param array<string> $contacts
     */
    public function updateAccountContacts(Account $account, array $contacts): Account
    {
        return $this->updateAccount($account, $contacts);
    }

    /**
     * 通过邮箱查找账户
     */
    public function findAccountByEmail(string $email, ?string $serverUrl = null): ?Account
    {
        $qb = $this->accountRepository->createQueryBuilder('a')
            ->where('a.contacts LIKE :email')
            ->setParameter('email', "%mailto:{$email}%")
        ;

        if (null !== $serverUrl) {
            $qb->andWhere('a.acmeServerUrl = :serverUrl')
                ->setParameter('serverUrl', $serverUrl)
            ;
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof Account ? $result : null;
    }

    /**
     * 注册账户（简化版本，接受邮箱地址）
     */
    public function registerAccountByEmail(
        string $email,
        string $serverUrl,
        int $keySize = 2048,
        bool $termsOfServiceAgreed = true,
    ): Account {
        $contacts = ["mailto:{$email}"];

        // 生成私钥
        $privateKeyPem = $this->generateAccountKey($keySize);

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid private key provided');
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
            $account->setAcmeServerUrl($serverUrl); // 使用传入的 serverUrl
            $account->setContacts($contacts);
            $account->setPrivateKey($privateKeyPem);
            $account->setPublicKeyJwk($this->createJwkFromPrivateKey($privateKeyPem));
            $account->setStatus(AccountStatus::VALID);
            $account->setAccountUrl($this->getLocationFromResponse($response));
            $account->setTermsOfServiceAgreed($termsOfServiceAgreed);

            $this->entityManager->persist($account);
            $this->entityManager->flush();

            $this->logger->info('ACME account registered successfully by email', [
                'account_id' => $account->getId(),
                'email' => $email,
                'server_url' => $serverUrl,
            ]);

            return $account;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register ACME account by email', [
                'email' => $email,
                'server_url' => $serverUrl,
                'error' => $e->getMessage(),
            ]);

            throw new AcmeOperationException("Account registration failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 从账户的联系信息中提取第一个邮箱地址
     */
    public function getEmailFromAccount(Account $account): ?string
    {
        $contacts = $account->getContacts();
        if (null === $contacts || [] === $contacts) {
            return null;
        }

        foreach ($contacts as $contact) {
            if (str_starts_with($contact, 'mailto:')) {
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

    /**
     * 通过联系邮箱查找账户（别名方法）
     *
     * @deprecated 使用 findAccountByEmail() 替代
     */
    public function findAccountByContactEmail(string $email, ?string $serverUrl = null): ?Account
    {
        return $this->findAccountByEmail($email, $serverUrl);
    }
}
