<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\OrderStatus;

/**
 * 订单验证服务
 *
 * 负责订单的验证和就绪检查
 */
#[WithMonologChannel(channel: 'acme_client')]
readonly class OrderValidationService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 检查订单是否可以完成
     */
    public function isOrderReadyForFinalization(Order $order): bool
    {
        return OrderStatus::READY === $order->getStatus();
    }

    /**
     * 检查订单是否准备好进行最终确认
     */
    public function isOrderReady(Order $order): bool
    {
        $isReady = $this->performOrderReadinessCheck($order);

        if ($isReady) {
            $this->logOrderReadiness($order);
        }

        return $isReady;
    }

    /**
     * 检查订单是否已完成
     */
    public function isOrderValid(Order $order): bool
    {
        return OrderStatus::VALID === $order->getStatus();
    }

    /**
     * 执行订单就绪检查
     */
    private function performOrderReadinessCheck(Order $order): bool
    {
        $readinessChecks = [
            $this->isOrderStatusReady($order),
            $this->hasValidAuthorizations($order),
            $this->hasFinalizeUrl($order),
            !$this->isOrderExpired($order),
        ];

        return !in_array(false, $readinessChecks, true);
    }

    /**
     * 检查订单状态是否为READY
     */
    private function isOrderStatusReady(Order $order): bool
    {
        if (OrderStatus::READY !== $order->getStatus()) {
            $this->logger->debug('Order not ready: wrong status', [
                'order_id' => $order->getId(),
                'current_status' => $order->getStatus()->value,
                'expected_status' => OrderStatus::READY->value,
            ]);

            return false;
        }

        return true;
    }

    /**
     * 检查订单是否有有效的授权
     */
    private function hasValidAuthorizations(Order $order): bool
    {
        $authorizations = $order->getAuthorizations();
        if ($authorizations->isEmpty()) {
            $this->logger->warning('Order has no authorizations', [
                'order_id' => $order->getId(),
            ]);

            return false;
        }

        $validAuthorizations = array_filter(
            $authorizations->toArray(),
            fn (Authorization $auth) => $this->isAuthorizationValid($order, $auth)
        );

        return count($validAuthorizations) === count($authorizations);
    }

    /**
     * 检查授权是否有效
     */
    private function isAuthorizationValid(Order $order, Authorization $authorization): bool
    {
        if (AuthorizationStatus::VALID !== $authorization->getStatus()) {
            $this->logger->debug('Order not ready: authorization not valid', [
                'order_id' => $order->getId(),
                'authorization_id' => $authorization->getId(),
                'authorization_status' => $authorization->getStatus()->value,
            ]);

            return false;
        }

        return $this->hasValidChallenge($order, $authorization);
    }

    /**
     * 检查授权是否有有效的挑战
     */
    private function hasValidChallenge(Order $order, Authorization $authorization): bool
    {
        $validChallenges = array_filter(
            $authorization->getChallenges()->toArray(),
            fn ($challenge) => ChallengeStatus::VALID === $challenge->getStatus()
        );

        $hasValidChallenge = count($validChallenges) > 0;
        if (!$hasValidChallenge) {
            $this->logger->debug('Order not ready: no valid challenge in authorization', [
                'order_id' => $order->getId(),
                'authorization_id' => $authorization->getId(),
            ]);
        }

        return $hasValidChallenge;
    }

    /**
     * 检查订单是否有finalize URL
     */
    private function hasFinalizeUrl(Order $order): bool
    {
        $finalizeUrl = $order->getFinalizeUrl();
        if (null === $finalizeUrl || '' === $finalizeUrl) {
            $this->logger->warning('Order has no finalize URL', [
                'order_id' => $order->getId(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * 检查订单是否过期
     */
    private function isOrderExpired(Order $order): bool
    {
        $expiresTime = $order->getExpiresTime();
        if (null !== $expiresTime && $expiresTime < new \DateTimeImmutable()) {
            $this->logger->warning('Order has expired', [
                'order_id' => $order->getId(),
                'expires_time' => $expiresTime->format(\DateTimeInterface::RFC3339),
            ]);

            return true;
        }

        return false;
    }

    /**
     * 记录订单就绪日志
     */
    private function logOrderReadiness(Order $order): void
    {
        $this->logger->info('Order is ready for finalization', [
            'order_id' => $order->getId(),
            'authorizations_count' => count($order->getAuthorizations()),
        ]);
    }
}
