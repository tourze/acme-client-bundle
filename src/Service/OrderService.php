<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;

/**
 * ACME 订单服务（Facade）
 *
 * 作为统一入口，将订单操作委托给专门的服务类
 */
#[Autoconfigure(public: true)]
readonly class OrderService
{
    public function __construct(
        private OrderCreationService $creationService,
        private OrderStatusService $statusService,
        private OrderFinalizationService $finalizationService,
        private OrderValidationService $validationService,
        private OrderQueryService $queryService,
        private CertificateDownloadService $certificateDownloadService,
    ) {
    }

    /**
     * 创建新的证书订单
     *
     * @param array<string> $domains
     */
    public function createOrder(Account $account, array $domains, ?\DateTimeInterface $notBefore = null, ?\DateTimeInterface $notAfter = null): Order
    {
        return $this->creationService->createOrder($account, $domains, $notBefore, $notAfter);
    }

    /**
     * 获取订单状态
     *
     * @return array<string, mixed>
     */
    public function getOrderStatus(Order $order): array
    {
        return $this->statusService->getOrderStatus($order);
    }

    /**
     * 刷新订单状态
     */
    public function refreshOrderStatus(Order $order): Order
    {
        return $this->statusService->refreshOrderStatus($order);
    }

    /**
     * 完成订单（提交CSR）
     */
    public function finalizeOrder(Order $order, string $csr): Order
    {
        return $this->finalizationService->finalizeOrder($order, $csr);
    }

    /**
     * 完成订单（使用自动生成的CSR）
     */
    public function finalizeOrderWithAutoCSR(Order $order): Order
    {
        return $this->finalizationService->finalizeOrderWithAutoCSR($order);
    }

    /**
     * 下载证书
     */
    public function downloadCertificate(Order $order): Certificate
    {
        return $this->certificateDownloadService->downloadCertificate($order);
    }

    /**
     * 查找账户的订单
     *
     * @return array<Order>
     */
    public function findOrdersByAccount(Account $account): array
    {
        return $this->queryService->findOrdersByAccount($account);
    }

    /**
     * 查找特定状态的订单
     *
     * @return array<Order>
     */
    public function findOrdersByStatus(OrderStatus $status): array
    {
        return $this->queryService->findOrdersByStatus($status);
    }

    /**
     * 获取订单的授权列表
     *
     * @return array<Authorization>
     */
    public function getOrderAuthorizations(Order $order): array
    {
        return $this->queryService->getOrderAuthorizations($order);
    }

    /**
     * 检查订单是否可以完成
     */
    public function isOrderReadyForFinalization(Order $order): bool
    {
        return $this->validationService->isOrderReadyForFinalization($order);
    }

    /**
     * 检查订单是否准备好进行最终确认
     */
    public function isOrderReady(Order $order): bool
    {
        return $this->validationService->isOrderReady($order);
    }

    /**
     * 检查订单是否已完成
     */
    public function isOrderValid(Order $order): bool
    {
        return $this->validationService->isOrderValid($order);
    }
}
