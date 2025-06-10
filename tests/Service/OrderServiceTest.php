<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\ACMEClientBundle\Service\OrderService;

/**
 * OrderService 测试
 */
class OrderServiceTest extends TestCase
{
    private OrderService $service;

    /** @var AcmeApiClient */
    private $apiClient;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var OrderRepository */
    private $orderRepository;

    /** @var LoggerInterface */
    private $logger;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(AcmeApiClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OrderService(
            $this->apiClient,
            $this->entityManager,
            $this->orderRepository,
            $this->logger
        );
    }

    public function testConstructor(): void
    {
        $service = new OrderService(
            $this->apiClient,
            $this->entityManager,
            $this->orderRepository,
            $this->logger
        );
        $this->assertInstanceOf(OrderService::class, $service);
    }

    public function testCreateOrderValidation(): void
    {
        $account = new Account();
        $account->setStatus(\Tourze\ACMEClientBundle\Enum\AccountStatus::DEACTIVATED);
        $domains = ['example.com'];

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Account is not valid');

        $this->service->createOrder($account, $domains);
    }

    public function testGetOrderStatusValidation(): void
    {
        $order = new Order();
        $order->setOrderUrl('https://example.com/order/123');
        // 没有设置account，应该抛出异常

        $this->expectException(\Error::class);

        $this->service->getOrderStatus($order);
    }

    public function testFindOrdersByAccount(): void
    {
        $account = new Account();
        $expectedOrders = [new Order(), new Order()];

        $this->orderRepository->expects($this->once())
            ->method('findBy')
            ->with(['account' => $account])
            ->willReturn($expectedOrders);

        $result = $this->service->findOrdersByAccount($account);

        $this->assertEquals($expectedOrders, $result);
    }

    public function testFindOrdersByStatus(): void
    {
        $status = OrderStatus::PENDING;
        $expectedOrders = [new Order()];

        $this->orderRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => $status])
            ->willReturn($expectedOrders);

        $result = $this->service->findOrdersByStatus($status);

        $this->assertEquals($expectedOrders, $result);
    }

    public function testMethodExistence(): void
    {
        $this->assertTrue(method_exists($this->service, 'createOrder'));
        $this->assertTrue(method_exists($this->service, 'getOrderStatus'));
        $this->assertTrue(method_exists($this->service, 'findOrdersByAccount'));
        $this->assertTrue(method_exists($this->service, 'findOrdersByStatus'));
    }
}
