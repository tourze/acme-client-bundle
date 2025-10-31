<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;

/**
 * 订单状态服务
 *
 * 负责订单状态的查询和更新
 */
readonly class OrderStatusService
{
    public function __construct(
        private AcmeApiClient $apiClient,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 获取订单状态
     *
     * @return array<string, mixed>
     */
    public function getOrderStatus(Order $order): array
    {
        $this->validateOrderForStatusCheck($order);

        try {
            $response = $this->fetchOrderStatusFromServer($order);
            $this->updateOrderFromStatusResponse($order, $response);
            $this->saveOrderStatusUpdate($order);

            return $response;
        } catch (\Throwable $e) {
            throw new AcmeOperationException("Failed to get order status: {$e->getMessage()}", 0, $e);
        }
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
     * 验证订单可以进行状态检查
     */
    private function validateOrderForStatusCheck(Order $order): void
    {
        $account = $order->getAccount();
        if (null === $account) {
            throw new AcmeOperationException('Order has no associated account');
        }

        if ('' === $order->getOrderUrl()) {
            throw new AcmeOperationException('Invalid order URL');
        }
    }

    /**
     * 从服务器获取订单状态
     *
     * @return array<string, mixed>
     */
    private function fetchOrderStatusFromServer(Order $order): array
    {
        return $this->apiClient->get($order->getOrderUrl());
    }

    /**
     * 从状态响应更新订单
     *
     * @param array<string, mixed> $response
     */
    private function updateOrderFromStatusResponse(Order $order, array $response): void
    {
        $this->updateOrderStatus($order, $response);
        $this->updateOrderExpiresTime($order, $response);
        $this->updateOrderCertificateUrl($order, $response);
    }

    /**
     * 更新订单状态
     *
     * @param array<string, mixed> $response
     */
    private function updateOrderStatus(Order $order, array $response): void
    {
        if (isset($response['status']) && is_string($response['status'])) {
            $orderStatus = OrderStatus::from($response['status']);
            $order->setStatus($orderStatus);
        }
    }

    /**
     * 更新订单过期时间
     *
     * @param array<string, mixed> $response
     */
    private function updateOrderExpiresTime(Order $order, array $response): void
    {
        if (isset($response['expires']) && is_string($response['expires'])) {
            $order->setExpiresTime(new \DateTimeImmutable($response['expires']));
        }
    }

    /**
     * 更新订单证书URL
     *
     * @param array<string, mixed> $response
     */
    private function updateOrderCertificateUrl(Order $order, array $response): void
    {
        if (isset($response['certificate']) && is_string($response['certificate'])) {
            $order->setCertificateUrl($response['certificate']);
        }
    }

    /**
     * 保存订单状态更新
     */
    private function saveOrderStatusUpdate(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }
}
