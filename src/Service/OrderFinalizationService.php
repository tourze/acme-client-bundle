<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;

/**
 * 订单完成服务
 *
 * 负责订单的完成和CSR提交
 */
#[WithMonologChannel(channel: 'acme_client')]
readonly class OrderFinalizationService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 完成订单（提交CSR）
     */
    public function finalizeOrder(Order $order, string $csr): Order
    {
        $account = $order->getAccount();
        if (null === $account) {
            throw new AcmeOperationException('Order has no associated account');
        }

        $privateKey = openssl_pkey_get_private($account->getPrivateKey());

        if (false === $privateKey || null === $order->getFinalizeUrl()) {
            throw new AcmeOperationException('Invalid order or account data');
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

            $this->updateOrderFromFinalizeResponse($order, $response);
            $this->persistOrder($order);
            $this->logOrderFinalization($order);

            return $order;
        } catch (\Throwable $e) {
            throw new AcmeOperationException("Order finalization failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 完成订单（使用自动生成的CSR）
     */
    public function finalizeOrderWithAutoCSR(Order $order): Order
    {
        $domains = $this->extractDomainsFromOrder($order);
        $privateKey = $this->generateCertificatePrivateKey();
        $csrData = $this->generateCSR($domains, $privateKey);

        return $this->finalizeOrder($order, $csrData);
    }

    /**
     * 从完成响应更新订单
     *
     * @param array<string, mixed> $response
     */
    private function updateOrderFromFinalizeResponse(Order $order, array $response): void
    {
        // 更新订单状态
        if (isset($response['status']) && is_string($response['status'])) {
            $orderStatus = OrderStatus::from($response['status']);
            $order->setStatus($orderStatus);
        }

        if (isset($response['certificate']) && is_string($response['certificate'])) {
            $order->setCertificateUrl($response['certificate']);
        }
    }

    /**
     * 持久化订单
     */
    private function persistOrder(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }

    /**
     * 记录订单完成日志
     */
    private function logOrderFinalization(Order $order): void
    {
        $this->logger->info('ACME order finalized successfully', [
            'order_id' => $order->getId(),
            'status' => $order->getStatus()->value,
        ]);
    }

    /**
     * 从订单提取域名
     *
     * @return array<string>
     */
    private function extractDomainsFromOrder(Order $order): array
    {
        $domains = [];
        foreach ($order->getOrderIdentifiers() as $identifier) {
            $domains[] = $identifier->getValue();
        }

        return $domains;
    }

    /**
     * 生成证书私钥
     *
     * @return \OpenSSLAsymmetricKey
     */
    private function generateCertificatePrivateKey(): \OpenSSLAsymmetricKey
    {
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (false === $privateKey) {
            throw new AcmeOperationException('Failed to generate private key for certificate');
        }

        return $privateKey;
    }

    /**
     * 生成CSR
     *
     * @param array<string> $domains
     * @param \OpenSSLAsymmetricKey $privateKey
     */
    private function generateCSR(array $domains, \OpenSSLAsymmetricKey $privateKey): string
    {
        $dn = $this->buildCSRDistinguishedName($domains);
        $config = $this->buildCSRConfig($domains);
        $csr = $this->createCSRResource($dn, $privateKey, $config);

        return $this->exportCSRData($csr);
    }

    /**
     * 构建CSR专有名称
     *
     * @param array<string> $domains
     * @return array<string, string>
     */
    private function buildCSRDistinguishedName(array $domains): array
    {
        return ['CN' => $domains[0]];
    }

    /**
     * 构建CSR配置
     *
     * @param array<string> $domains
     * @return array<string, mixed>
     */
    private function buildCSRConfig(array $domains): array
    {
        // 生成 SAN 字符串
        $sanString = implode(',', array_map(fn ($domain) => "DNS:{$domain}", $domains));

        // 创建临时配置文件
        $configContent = "
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req

[req_distinguished_name]

[v3_req]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
subjectAltName = {$sanString}
";

        $configFile = tempnam(sys_get_temp_dir(), 'openssl_csr_');
        file_put_contents($configFile, $configContent);

        return [
            'digest_alg' => 'sha256',
            'config' => $configFile,
            'req_extensions' => 'v3_req',
        ];
    }

    /**
     * 创建CSR资源
     *
     * @param array<string, string> $dn
     * @param \OpenSSLAsymmetricKey $privateKey
     * @param array<string, mixed> $config
     * @return \OpenSSLCertificateSigningRequest
     */
    private function createCSRResource(array $dn, \OpenSSLAsymmetricKey $privateKey, array $config): \OpenSSLCertificateSigningRequest
    {
        $csr = openssl_csr_new($dn, $privateKey, $config);
        if (false === $csr || true === $csr) {
            throw new AcmeOperationException('Failed to generate CSR');
        }

        return $csr;
    }

    /**
     * 导出CSR数据
     *
     * @param \OpenSSLCertificateSigningRequest $csr
     */
    private function exportCSRData(\OpenSSLCertificateSigningRequest $csr): string
    {
        $csrData = '';
        $exportResult = openssl_csr_export($csr, $csrData);
        if (false === $exportResult || !is_string($csrData) || '' === $csrData) {
            throw new AcmeOperationException('Failed to export CSR');
        }

        return $csrData;
    }
}
