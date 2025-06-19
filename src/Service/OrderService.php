<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\OrderRepository;

/**
 * ACME 订单服务
 *
 * 负责 ACME 证书订单的创建、状态流转、证书下载等操作
 */
class OrderService
{
    public function __construct(
        private readonly AcmeApiClient $apiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * 创建新的证书订单
     */
    public function createOrder(Account $account, array $domains, ?\DateTimeInterface $notBefore = null, ?\DateTimeInterface $notAfter = null): Order
    {
        if (!$this->isAccountValid($account)) {
            throw new AcmeClientException('Account is not valid');
        }

        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (!$privateKey) {
            throw new AcmeClientException('Invalid account private key');
        }

        // 构建标识符
        $identifiers = [];
        foreach ($domains as $domain) {
            $identifiers[] = [
                'type' => 'dns',
                'value' => $domain,
            ];
        }

        // 构建订单载荷
        $payload = [
            'identifiers' => $identifiers,
        ];

        if ($notBefore !== null) {
            $payload['notBefore'] = $notBefore->format(\DateTimeInterface::RFC3339);
        }

        if ($notAfter !== null) {
            $payload['notAfter'] = $notAfter->format(\DateTimeInterface::RFC3339);
        }

        try {
            // 发送创建订单请求
            $response = $this->apiClient->post('newOrder', $payload, $privateKey, $account->getAccountUrl());

            // 创建订单实体
            $order = new Order();
            $order->setAccount($account);
            $order->setStatus(OrderStatus::PENDING);
            $order->setOrderUrl($this->getLocationFromResponse($response));
            $order->setFinalizeUrl($response['finalize'] ?? '');

            if (isset($response['expires'])) {
                $order->setExpiresTime(new \DateTimeImmutable($response['expires']));
            }

            $this->entityManager->persist($order);

            // 创建标识符实体
            foreach ($domains as $domain) {
                $identifier = new Identifier();
                $identifier->setOrder($order);
                $identifier->setType('dns');
                $identifier->setValue($domain);

                $this->entityManager->persist($identifier);
                $order->addOrderIdentifier($identifier);
            }

            // 创建授权实体
            if (isset($response['authorizations'])) {
                foreach ($response['authorizations'] as $authUrl) {
                    $authorization = new Authorization();
                    $authorization->setOrder($order);
                    $authorization->setAuthorizationUrl($authUrl);
                    $authorization->setStatus(\Tourze\ACMEClientBundle\Enum\AuthorizationStatus::PENDING);

                    $this->entityManager->persist($authorization);
                    $order->addAuthorization($authorization);
                }
            }

            $this->entityManager->flush();

            $this->logger->info('ACME order created successfully', [
                'order_id' => $order->getId(),
                'account_id' => $account->getId(),
                'domains' => $domains,
                'order_url' => $order->getOrderUrl(),
            ]);

            return $order;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create ACME order', [
                'account_id' => $account->getId(),
                'domains' => $domains,
                'error' => $e->getMessage(),
            ]);

            throw new AcmeClientException(
                "Order creation failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 获取订单状态
     */
    public function getOrderStatus(Order $order): array
    {
        $account = $order->getAccount();
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if ($privateKey === false || $order->getOrderUrl() === '') {
            throw new AcmeClientException('Invalid order or account data');
        }

        try {
            $response = $this->apiClient->get($order->getOrderUrl());

            // 更新订单状态
            if (isset($response['status'])) {
                $orderStatus = OrderStatus::from($response['status']);
                $order->setStatus($orderStatus);
            }

            if (isset($response['expires'])) {
                $order->setExpiresTime(new \DateTimeImmutable($response['expires']));
            }

            if (isset($response['certificate'])) {
                $order->setCertificateUrl($response['certificate']);
            }

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $response;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Failed to get order status: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 完成订单（提交CSR）
     */
    public function finalizeOrder(Order $order, string $csr): Order
    {
        $account = $order->getAccount();
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if ($privateKey === false || $order->getFinalizeUrl() === null) {
            throw new AcmeClientException('Invalid order or account data');
        }

        $payload = [
            'csr' => rtrim(strtr(base64_encode($csr), '+/', '-_'), '='),
        ];

        try {
            $response = $this->apiClient->post(
                $order->getFinalizeUrl(),
                $payload,
                $privateKey,
                $account->getAccountUrl()
            );

            // 更新订单状态
            if (isset($response['status'])) {
                $orderStatus = OrderStatus::from($response['status']);
                $order->setStatus($orderStatus);
            }

            if (isset($response['certificate'])) {
                $order->setCertificateUrl($response['certificate']);
            }

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            $this->logger->info('ACME order finalized successfully', [
                'order_id' => $order->getId(),
                'status' => $order->getStatus()->value,
            ]);

            return $order;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Order finalization failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 下载证书
     */
    public function downloadCertificate(Order $order): Certificate
    {
        if ($order->getCertificateUrl() === null) {
            throw new AcmeClientException('Certificate URL not available');
        }

        try {
            $response = $this->apiClient->get($order->getCertificateUrl());

            // 假设响应是PEM格式的证书
            $certificatePem = $response['certificate'] ?? '';

            if (empty($certificatePem)) {
                throw new AcmeClientException('Empty certificate received');
            }

            // 创建证书实体
            $certificate = new Certificate();
            $certificate->setOrder($order);
            $certificate->setCertificatePem($certificatePem);
            $certificate->setStatus(CertificateStatus::VALID);

            // 解析证书信息
            $certInfo = openssl_x509_parse($certificatePem);
            if ($certInfo) {
                if (isset($certInfo['validFrom_time_t'])) {
                    $certificate->setNotBeforeTime(new \DateTimeImmutable('@' . $certInfo['validFrom_time_t']));
                }
                if (isset($certInfo['validTo_time_t'])) {
                    $certificate->setNotAfterTime(new \DateTimeImmutable('@' . $certInfo['validTo_time_t']));
                }
                if (isset($certInfo['serialNumber'])) {
                    $certificate->setSerialNumber($certInfo['serialNumber']);
                }
            }

            $this->entityManager->persist($certificate);
            $this->entityManager->flush();

            $this->logger->info('Certificate downloaded successfully', [
                'order_id' => $order->getId(),
                'certificate_id' => $certificate->getId(),
            ]);

            return $certificate;
        } catch (\Throwable $e) {
            throw new AcmeClientException(
                "Certificate download failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 查找账户的订单
     */
    public function findOrdersByAccount(Account $account): array
    {
        return $this->orderRepository->findBy(['account' => $account], ['createdTime' => 'DESC']);
    }

    /**
     * 查找特定状态的订单
     */
    public function findOrdersByStatus(OrderStatus $status): array
    {
        return $this->orderRepository->findBy(['status' => $status]);
    }

    /**
     * 检查订单是否可以完成
     */
    public function isOrderReadyForFinalization(Order $order): bool
    {
        return $order->getStatus() === OrderStatus::READY;
    }

    /**
     * 检查订单是否已完成
     */
    public function isOrderValid(Order $order): bool
    {
        return $order->getStatus() === OrderStatus::VALID;
    }

    /**
     * 验证账户是否有效
     */
    private function isAccountValid(Account $account): bool
    {
        return $account->isValid() && !$account->isDeactivated();
    }

    /**
     * 从响应中获取 Location 头
     */
    private function getLocationFromResponse(array $response): ?string
    {
        return $response['location'] ?? null;
    }

    /**
     * 获取订单的授权列表
     */
    public function getOrderAuthorizations(Order $order): array
    {
        return $order->getAuthorizations()->toArray();
    }

    /**
     * 刷新订单状态
     */
    public function refreshOrderStatus(Order $order): Order
    {
        $this->getOrderStatus($order);
        return $order;
    }

    /**
     * 完成订单（使用自动生成的CSR）
     */
    public function finalizeOrderWithAutoCSR(Order $order): Order
    {
        // 获取订单中的域名
        $domains = [];
        foreach ($order->getOrderIdentifiers() as $identifier) {
            $domains[] = $identifier->getValue();
        }

        // 生成私钥和CSR
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (!$privateKey) {
            throw new AcmeClientException('Failed to generate private key for certificate');
        }

        // 构建CSR
        $dn = [
            'CN' => $domains[0], // 主域名作为 Common Name
        ];

        // 为多域名证书添加SAN扩展
        $config = [
            'digest_alg' => 'sha256',
            'req_extensions' => 'v3_req',
            'x509_extensions' => 'v3_ca',
        ];

        if (count($domains) > 1) {
            $sanList = [];
            foreach ($domains as $domain) {
                $sanList[] = "DNS:{$domain}";
            }
            $config['req_extensions'] = 'SAN';
            $config['SAN'] = ['subjectAltName' => implode(',', $sanList)];
        }

        $csr = openssl_csr_new($dn, $privateKey, $config);
        if (!$csr) {
            throw new AcmeClientException('Failed to generate CSR');
        }

        $csrData = '';
        if (!openssl_csr_export($csr, $csrData)) {
            throw new AcmeClientException('Failed to export CSR');
        }

        // 转换为DER格式
        $csrDer = openssl_csr_get_public_key($csr);
        if (!$csrDer) {
            throw new AcmeClientException('Failed to get CSR public key');
        }

        // 调用原始的finalizeOrder方法
        return $this->finalizeOrder($order, $csrData);
    }
}
