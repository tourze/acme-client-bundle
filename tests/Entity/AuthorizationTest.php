<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;

/**
 * Authorization 实体测试类
 */
class AuthorizationTest extends TestCase
{
    private Authorization $authorization;

    protected function setUp(): void
    {
        $this->authorization = new Authorization();
    }

    public function test_constructor_defaultValues(): void
    {
        $this->assertNull($this->authorization->getId());
        $this->assertNull($this->authorization->getOrder());
        $this->assertNull($this->authorization->getIdentifier());
        $this->assertSame(AuthorizationStatus::PENDING, $this->authorization->getStatus());
        $this->assertNull($this->authorization->getExpiresTime());
        $this->assertFalse($this->authorization->isWildcard());
        $this->assertFalse($this->authorization->isValid());
        $this->assertInstanceOf(ArrayCollection::class, $this->authorization->getChallenges());
        $this->assertTrue($this->authorization->getChallenges()->isEmpty());
    }

    public function test_order_getterSetter(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);
        $result = $this->authorization->setOrder($order);

        $this->assertSame($this->authorization, $result);
        $this->assertSame($order, $this->authorization->getOrder());
    }

    public function test_order_setToNull(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);
        $this->authorization->setOrder($order);

        $this->authorization->setOrder(null);
        $this->assertNull($this->authorization->getOrder());
    }

    public function test_identifier_getterSetter(): void
    {
        /** @var Identifier $identifier */
        $identifier = $this->createMock(Identifier::class);
        $result = $this->authorization->setIdentifier($identifier);

        $this->assertSame($this->authorization, $result);
        $this->assertSame($identifier, $this->authorization->getIdentifier());
    }

    public function test_identifier_setToNull(): void
    {
        /** @var Identifier $identifier */
        $identifier = $this->createMock(Identifier::class);
        $this->authorization->setIdentifier($identifier);

        $this->authorization->setIdentifier(null);
        $this->assertNull($this->authorization->getIdentifier());
    }

    public function test_authorizationUrl_getterSetter(): void
    {
        $url = 'https://acme-v02.api.letsencrypt.org/acme/authz-v3/123456';
        $result = $this->authorization->setAuthorizationUrl($url);

        $this->assertSame($this->authorization, $result);
        $this->assertSame($url, $this->authorization->getAuthorizationUrl());
    }

    public function test_status_getterSetter(): void
    {
        $this->assertSame(AuthorizationStatus::PENDING, $this->authorization->getStatus());

        $result = $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertSame($this->authorization, $result);
        $this->assertSame(AuthorizationStatus::VALID, $this->authorization->getStatus());
    }

    public function test_status_automaticValidFlag(): void
    {
        // 设置为 VALID 时自动设置 valid 标志为 true
        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($this->authorization->isValid());

        // 设置为其他状态时 valid 标志为 false
        $this->authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertFalse($this->authorization->isValid());

        $this->authorization->setStatus(AuthorizationStatus::INVALID);
        $this->assertFalse($this->authorization->isValid());

        $this->authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertFalse($this->authorization->isValid());

        $this->authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertFalse($this->authorization->isValid());
    }

    public function test_expiresTime_getterSetter(): void
    {
        $this->assertNull($this->authorization->getExpiresTime());

        $expiry = new \DateTimeImmutable('+30 days');
        $result = $this->authorization->setExpiresTime($expiry);

        $this->assertSame($this->authorization, $result);
        $this->assertSame($expiry, $this->authorization->getExpiresTime());
    }

    public function test_expiresTime_setToNull(): void
    {
        $this->authorization->setExpiresTime(new \DateTimeImmutable());
        $this->authorization->setExpiresTime(null);

        $this->assertNull($this->authorization->getExpiresTime());
    }

    public function test_wildcard_getterSetter(): void
    {
        $this->assertFalse($this->authorization->isWildcard());

        $result = $this->authorization->setWildcard(true);
        $this->assertSame($this->authorization, $result);
        $this->assertTrue($this->authorization->isWildcard());

        $this->authorization->setWildcard(false);
        $this->assertFalse($this->authorization->isWildcard());
    }

    public function test_valid_getterSetter(): void
    {
        $this->assertFalse($this->authorization->isValid());

        $result = $this->authorization->setValid(true);
        $this->assertSame($this->authorization, $result);
        $this->assertTrue($this->authorization->isValid());

        $this->authorization->setValid(false);
        $this->assertFalse($this->authorization->isValid());
    }

    public function test_addChallenge(): void
    {
        /** @var Challenge $challenge */
        $challenge = $this->createMock(Challenge::class);

        $result = $this->authorization->addChallenge($challenge);

        $this->assertSame($this->authorization, $result);
        $this->assertTrue($this->authorization->getChallenges()->contains($challenge));
        $this->assertSame(1, $this->authorization->getChallenges()->count());
    }

    public function test_addChallenge_preventDuplicates(): void
    {
        /** @var Challenge $challenge */
        $challenge = $this->createMock(Challenge::class);

        $this->authorization->addChallenge($challenge);
        $this->authorization->addChallenge($challenge);

        $this->assertSame(1, $this->authorization->getChallenges()->count());
        $this->assertTrue($this->authorization->getChallenges()->contains($challenge));
    }

    public function test_removeChallenge(): void
    {
        /** @var Challenge $challenge */
        $challenge = $this->createMock(Challenge::class);

        $this->authorization->getChallenges()->add($challenge);
        $this->assertTrue($this->authorization->getChallenges()->contains($challenge));

        $result = $this->authorization->removeChallenge($challenge);

        $this->assertSame($this->authorization, $result);
        $this->assertFalse($this->authorization->getChallenges()->contains($challenge));
        $this->assertSame(0, $this->authorization->getChallenges()->count());
    }

    public function test_isPending(): void
    {
        $this->assertTrue($this->authorization->isPending());

        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($this->authorization->isPending());

        $this->authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($this->authorization->isPending());
    }

    public function test_isExpired_withStatus(): void
    {
        $this->authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertTrue($this->authorization->isExpired());

        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($this->authorization->isExpired());
    }

    public function test_isExpired_withTime(): void
    {
        // 未过期的时间
        $future = new \DateTimeImmutable('+1 hour');
        $this->authorization->setExpiresTime($future);
        $this->assertFalse($this->authorization->isExpired());

        // 已过期的时间
        $past = new \DateTimeImmutable('-1 hour');
        $this->authorization->setExpiresTime($past);
        $this->assertTrue($this->authorization->isExpired());
    }

    public function test_isExpired_statusOverridesTime(): void
    {
        // 即使时间未过期，但状态为 EXPIRED，仍然返回 true
        $future = new \DateTimeImmutable('+1 hour');
        $this->authorization
            ->setExpiresTime($future)
            ->setStatus(AuthorizationStatus::EXPIRED);

        $this->assertTrue($this->authorization->isExpired());
    }

    public function test_isRevoked(): void
    {
        $this->assertFalse($this->authorization->isRevoked());

        $this->authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertTrue($this->authorization->isRevoked());

        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($this->authorization->isRevoked());
    }

    public function test_isInvalid(): void
    {
        $this->assertFalse($this->authorization->isInvalid());

        $this->authorization->setStatus(AuthorizationStatus::INVALID);
        $this->assertTrue($this->authorization->isInvalid());

        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($this->authorization->isInvalid());
    }

    public function test_toString_withIdentifier(): void
    {
        /** @var Identifier $identifier */
        $identifier = $this->createMock(Identifier::class);
        $this->authorization->setIdentifier($identifier);

        $expected = 'Authorization #0 ()';
        $this->assertSame($expected, (string) $this->authorization);
    }

    public function test_toString_withoutIdentifier(): void
    {
        $expected = 'Authorization #0 ()';
        $this->assertSame($expected, (string) $this->authorization);
    }

    public function test_stringableInterface(): void
    {
        $this->assertInstanceOf(\Stringable::class, $this->authorization);
    }

    public function test_fluentInterface_chaining(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);
        /** @var Identifier $identifier */
        $identifier = $this->createMock(Identifier::class);
        $url = 'https://acme-v02.api.letsencrypt.org/acme/authz-v3/123456';
        $expiry = new \DateTimeImmutable('+30 days');

        $result = $this->authorization
            ->setOrder($order)
            ->setIdentifier($identifier)
            ->setAuthorizationUrl($url)
            ->setStatus(AuthorizationStatus::VALID)
            ->setExpiresTime($expiry)
            ->setWildcard(true)
            ->setValid(true);

        $this->assertSame($this->authorization, $result);
        $this->assertSame($order, $this->authorization->getOrder());
        $this->assertSame($identifier, $this->authorization->getIdentifier());
        $this->assertSame($url, $this->authorization->getAuthorizationUrl());
        $this->assertSame(AuthorizationStatus::VALID, $this->authorization->getStatus());
        $this->assertSame($expiry, $this->authorization->getExpiresTime());
        $this->assertTrue($this->authorization->isWildcard());
        $this->assertTrue($this->authorization->isValid());
    }

    public function test_businessScenario_authorizationCreation(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);
        /** @var Identifier $identifier */
        $identifier = $this->createMock(Identifier::class);

        $this->authorization
            ->setOrder($order)
            ->setIdentifier($identifier)
            ->setAuthorizationUrl('https://acme-v02.api.letsencrypt.org/acme/authz-v3/123456')
            ->setStatus(AuthorizationStatus::PENDING)
            ->setExpiresTime(new \DateTimeImmutable('+30 days'));

        $this->assertTrue($this->authorization->isPending());
        $this->assertFalse($this->authorization->isValid());
        $this->assertFalse($this->authorization->isExpired());
        $this->assertFalse($this->authorization->isInvalid());
        $this->assertFalse($this->authorization->isRevoked());
    }

    public function test_businessScenario_authorizationProgression(): void
    {
        // 初始状态：等待中
        $this->authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($this->authorization->isPending());
        $this->assertFalse($this->authorization->isValid());

        // 授权有效
        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($this->authorization->isPending());
        $this->assertTrue($this->authorization->isValid());

        // 授权过期
        $this->authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertFalse($this->authorization->isValid());
        $this->assertTrue($this->authorization->isExpired());
    }

    public function test_businessScenario_authorizationFailure(): void
    {
        $this->authorization->setStatus(AuthorizationStatus::INVALID);

        $this->assertTrue($this->authorization->isInvalid());
        $this->assertFalse($this->authorization->isValid());
        $this->assertFalse($this->authorization->isPending());
    }

    public function test_businessScenario_authorizationRevocation(): void
    {
        // 有效的授权
        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($this->authorization->isValid());
        $this->assertFalse($this->authorization->isRevoked());

        // 被撤销
        $this->authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertFalse($this->authorization->isValid());
        $this->assertTrue($this->authorization->isRevoked());
    }

    public function test_businessScenario_wildcardAuthorization(): void
    {
        /** @var Identifier $identifier */
        $identifier = $this->createMock(Identifier::class);

        $this->authorization
            ->setIdentifier($identifier)
            ->setWildcard(true)
            ->setStatus(AuthorizationStatus::VALID);

        $this->assertTrue($this->authorization->isWildcard());
        $this->assertTrue($this->authorization->isValid());
    }

    public function test_businessScenario_multipleChallenges(): void
    {
        /** @var Challenge $challenge1 */
        $challenge1 = $this->createMock(Challenge::class);
        /** @var Challenge $challenge2 */
        $challenge2 = $this->createMock(Challenge::class);
        /** @var Challenge $challenge3 */
        $challenge3 = $this->createMock(Challenge::class);

        $this->authorization
            ->addChallenge($challenge1)
            ->addChallenge($challenge2)
            ->addChallenge($challenge3);

        $this->assertSame(3, $this->authorization->getChallenges()->count());
        $this->assertTrue($this->authorization->getChallenges()->contains($challenge1));
        $this->assertTrue($this->authorization->getChallenges()->contains($challenge2));
        $this->assertTrue($this->authorization->getChallenges()->contains($challenge3));
    }

    public function test_edgeCases_nullExpiresTime(): void
    {
        $this->authorization->setExpiresTime(null);
        $this->assertNull($this->authorization->getExpiresTime());
        $this->assertFalse($this->authorization->isExpired());
    }

    public function test_edgeCases_emptyUrl(): void
    {
        $this->authorization->setAuthorizationUrl('');
        $this->assertSame('', $this->authorization->getAuthorizationUrl());
    }

    public function test_edgeCases_longUrl(): void
    {
        $longUrl = 'https://acme-v02.api.letsencrypt.org/acme/authz-v3/' . str_repeat('a', 400);
        $this->authorization->setAuthorizationUrl($longUrl);

        $this->assertSame($longUrl, $this->authorization->getAuthorizationUrl());
    }

    public function test_stateTransitions_pendingToValid(): void
    {
        $this->authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($this->authorization->isPending());
        $this->assertFalse($this->authorization->isValid());

        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($this->authorization->isPending());
        $this->assertTrue($this->authorization->isValid());
    }

    public function test_stateTransitions_validToExpired(): void
    {
        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($this->authorization->isValid());
        $this->assertFalse($this->authorization->isExpired());

        $this->authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertFalse($this->authorization->isValid());
        $this->assertTrue($this->authorization->isExpired());
    }

    public function test_stateTransitions_validToRevoked(): void
    {
        $this->authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($this->authorization->isValid());
        $this->assertFalse($this->authorization->isRevoked());

        $this->authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertFalse($this->authorization->isValid());
        $this->assertTrue($this->authorization->isRevoked());
    }

    public function test_stateTransitions_pendingToInvalid(): void
    {
        $this->authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($this->authorization->isPending());
        $this->assertFalse($this->authorization->isInvalid());

        $this->authorization->setStatus(AuthorizationStatus::INVALID);
        $this->assertFalse($this->authorization->isPending());
        $this->assertTrue($this->authorization->isInvalid());
    }
}
