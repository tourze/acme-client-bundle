<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Repository\OrderRepository;

/**
 * 订单查询服务
 *
 * 负责订单的查询操作
 */
readonly class OrderQueryService
{
    public function __construct(
        private OrderRepository $orderRepository,
    ) {
    }

    /**
     * 查找账户的订单
     *
     * @return array<Order>
     */
    public function findOrdersByAccount(Account $account): array
    {
        return $this->orderRepository->findBy(['account' => $account], ['createTime' => 'DESC']);
    }

    /**
     * 查找特定状态的订单
     *
     * @return array<Order>
     */
    public function findOrdersByStatus(OrderStatus $status): array
    {
        return $this->orderRepository->findBy(['status' => $status]);
    }

    /**
     * 获取订单的授权列表
     *
     * @return array<Authorization>
     */
    public function getOrderAuthorizations(Order $order): array
    {
        return $order->getAuthorizations()->toArray();
    }
}
