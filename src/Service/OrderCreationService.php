<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;

/**
 * 订单创建服务
 *
 * 负责创建新的ACME证书订单
 */
#[WithMonologChannel(channel: 'acme_client')]
readonly class OrderCreationService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 创建新的证书订单
     *
     * @param array<string> $domains
     */
    public function createOrder(Account $account, array $domains, ?\DateTimeInterface $notBefore = null, ?\DateTimeInterface $notAfter = null): Order
    {
        $this->validateOrderPreconditions($account);
        $privateKey = $this->getAccountPrivateKey($account);
        $payload = $this->buildOrderPayload($domains, $notBefore, $notAfter);

        try {
            $response = $this->submitNewOrderRequest($account, $payload, $privateKey);
            $order = $this->buildOrderFromResponse($account, $response, $domains);
            $this->persistOrder();
            $this->logOrderCreationSuccess($order, $account, $domains);

            return $order;
        } catch (\Throwable $e) {
            $this->logOrderCreationError($account, $domains, $e);
            throw new AcmeOperationException("Order creation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 验证账户是否有效
     */
    private function validateOrderPreconditions(Account $account): void
    {
        if (!$this->isAccountValid($account)) {
            throw new AcmeOperationException('Account is not valid');
        }
    }

    /**
     * 检查账户是否有效
     */
    private function isAccountValid(Account $account): bool
    {
        return $account->isValid() && !$account->isDeactivated();
    }

    /**
     * 获取账户私钥
     *
     * @return \OpenSSLAsymmetricKey
     */
    private function getAccountPrivateKey(Account $account): \OpenSSLAsymmetricKey
    {
        $privateKey = openssl_pkey_get_private($account->getPrivateKey());
        if (false === $privateKey) {
            throw new AcmeOperationException('Invalid account private key');
        }

        return $privateKey;
    }

    /**
     * 构建订单请求负载
     *
     * @param array<string> $domains
     * @return array<string, mixed>
     */
    private function buildOrderPayload(array $domains, ?\DateTimeInterface $notBefore, ?\DateTimeInterface $notAfter): array
    {
        $identifiers = [];
        foreach ($domains as $domain) {
            $identifiers[] = [
                'type' => 'dns',
                'value' => $domain,
            ];
        }

        $payload = ['identifiers' => $identifiers];

        if (null !== $notBefore) {
            $payload['notBefore'] = $notBefore->format(\DateTimeInterface::RFC3339);
        }

        if (null !== $notAfter) {
            $payload['notAfter'] = $notAfter->format(\DateTimeInterface::RFC3339);
        }

        return $payload;
    }

    /**
     * 提交新订单请求到ACME服务器
     *
     * @param array<string, mixed> $payload
     * @param \OpenSSLAsymmetricKey $privateKey
     * @return array<string, mixed>
     */
    private function submitNewOrderRequest(Account $account, array $payload, \OpenSSLAsymmetricKey $privateKey): array
    {
        return $this->apiClient->post('newOrder', $payload, $privateKey, $account->getAccountUrl());
    }

    /**
     * 从响应构建订单实体
     *
     * @param array<string, mixed> $response
     * @param array<string> $domains
     */
    private function buildOrderFromResponse(Account $account, array $response, array $domains): Order
    {
        $order = $this->createOrderEntity($account, $response);
        $this->createOrderIdentifiers($order, $domains);
        $this->createOrderAuthorizations($order, $response);

        return $order;
    }

    /**
     * 创建订单实体
     *
     * @param array<string, mixed> $response
     */
    private function createOrderEntity(Account $account, array $response): Order
    {
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $orderUrl = $this->getLocationFromResponse($response);
        if (null !== $orderUrl) {
            $order->setOrderUrl($orderUrl);
        }

        $finalizeUrl = $response['finalize'] ?? null;
        if (is_string($finalizeUrl)) {
            $order->setFinalizeUrl($finalizeUrl);
        }

        if (isset($response['expires']) && is_string($response['expires'])) {
            $order->setExpiresTime(new \DateTimeImmutable($response['expires']));
        }

        $this->entityManager->persist($order);

        return $order;
    }

    /**
     * 创建订单标识符
     *
     * @param array<string> $domains
     */
    private function createOrderIdentifiers(Order $order, array $domains): void
    {
        foreach ($domains as $domain) {
            $identifier = new Identifier();
            $identifier->setOrder($order);
            $identifier->setType('dns');
            $identifier->setValue($domain);

            $this->entityManager->persist($identifier);
            $order->addOrderIdentifier($identifier);
        }
    }

    /**
     * 创建订单授权
     *
     * @param array<string, mixed> $response
     */
    private function createOrderAuthorizations(Order $order, array $response): void
    {
        if (isset($response['authorizations']) && is_array($response['authorizations'])) {
            $identifiers = $order->getOrderIdentifiers()->toArray();

            foreach ($response['authorizations'] as $index => $authUrl) {
                if (!is_string($authUrl)) {
                    continue;
                }

                $authorization = new Authorization();
                $authorization->setOrder($order);
                $authorization->setAuthorizationUrl($authUrl);
                $authorization->setStatus(AuthorizationStatus::PENDING);

                // 将授权与对应的标识符关联
                if (isset($identifiers[$index])) {
                    $authorization->setIdentifier($identifiers[$index]);
                }

                $this->entityManager->persist($authorization);
                $order->addAuthorization($authorization);
            }
        }
    }

    /**
     * 从响应中获取 Location 头
     *
     * @param array<string, mixed> $response
     * @return string|null
     */
    private function getLocationFromResponse(array $response): ?string
    {
        $location = $response['location'] ?? null;

        return is_string($location) ? $location : null;
    }

    /**
     * 持久化订单
     */
    private function persistOrder(): void
    {
        $this->entityManager->flush();
    }

    /**
     * 记录订单创建成功日志
     *
     * @param array<string> $domains
     */
    private function logOrderCreationSuccess(Order $order, Account $account, array $domains): void
    {
        $this->logger->info('ACME order created successfully', [
            'order_id' => $order->getId(),
            'account_id' => $account->getId(),
            'domains' => $domains,
            'order_url' => $order->getOrderUrl(),
        ]);
    }

    /**
     * 记录订单创建错误日志
     *
     * @param array<string> $domains
     */
    private function logOrderCreationError(Account $account, array $domains, \Throwable $e): void
    {
        $this->logger->error('Failed to create ACME order', [
            'account_id' => $account->getId(),
            'domains' => $domains,
            'error' => $e->getMessage(),
        ]);
    }
}
