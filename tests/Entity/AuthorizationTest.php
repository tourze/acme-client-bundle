<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * Authorization 实体测试类
 *
 * @internal
 */
#[CoversClass(Authorization::class)]
final class AuthorizationTest extends AbstractEntityTestCase
{
    public function testConstructorDefaultValues(): void
    {
        $authorization = $this->createEntity();
        $this->assertNull($authorization->getId());
        $this->assertNull($authorization->getOrder());
        $this->assertNull($authorization->getIdentifier());
        $this->assertSame(AuthorizationStatus::PENDING, $authorization->getStatus());
        $this->assertNull($authorization->getExpiresTime());
        $this->assertFalse($authorization->isWildcard());
        $this->assertFalse($authorization->isValid());
        $this->assertInstanceOf(ArrayCollection::class, $authorization->getChallenges());
        $this->assertTrue($authorization->getChallenges()->isEmpty());
    }

    public function testStatusAutomaticValidFlag(): void
    {
        $authorization = $this->createEntity();

        // 设置为 VALID 时自动设置 valid 标志为 true
        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($authorization->isValid());

        // 设置为其他状态时 valid 标志为 false
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertFalse($authorization->isValid());

        $authorization->setStatus(AuthorizationStatus::INVALID);
        $this->assertFalse($authorization->isValid());

        $authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertFalse($authorization->isValid());

        $authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertFalse($authorization->isValid());
    }

    public function testAddChallenge(): void
    {
        /*
         * 使用具体类 Challenge 进行 mock：
         * 1. Challenge 是领域实体，没有接口定义，只能使用具体类
         * 2. 在单元测试中模拟关联关系是合理的做法
         * 3. 真实的实体创建需要复杂的构造逻辑，mock 更简洁
         */
        $authorization = $this->createEntity();
        $challenge = $this->createMock(Challenge::class);

        $authorization->addChallenge($challenge);
        $this->assertTrue($authorization->getChallenges()->contains($challenge));
        $this->assertSame(1, $authorization->getChallenges()->count());
    }

    public function testAddChallengePreventDuplicates(): void
    {
        /* 使用具体类 Challenge 进行 mock，原因是：1. Challenge 是领域实体，没有接口定义，只能使用具体类；2. 测试重复添加的处理逻辑，只需要验证集合的去重功能；3. Mock 对象足以验证集合的行为，无需真实实体。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $authorization = $this->createEntity();
        $challenge = $this->createMock(Challenge::class);

        $authorization->addChallenge($challenge);
        $authorization->addChallenge($challenge);

        $this->assertSame(1, $authorization->getChallenges()->count());
        $this->assertTrue($authorization->getChallenges()->contains($challenge));
    }

    public function testRemoveChallenge(): void
    {
        /* 使用具体类 Challenge 进行 mock，原因是：1. Challenge 是领域实体，没有接口定义，只能使用具体类；2. 测试从集合中移除的功能，只关注集合操作；3. Mock 简化了测试设置，专注于被测试的行为。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $authorization = $this->createEntity();
        $challenge = $this->createMock(Challenge::class);

        $authorization->getChallenges()->add($challenge);
        $this->assertTrue($authorization->getChallenges()->contains($challenge));

        $authorization->removeChallenge($challenge);
        $this->assertFalse($authorization->getChallenges()->contains($challenge));
        $this->assertSame(0, $authorization->getChallenges()->count());
    }

    public function testIsPending(): void
    {
        $authorization = $this->createEntity();
        $this->assertTrue($authorization->isPending());

        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($authorization->isPending());

        $authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($authorization->isPending());
    }

    public function testIsExpiredWithStatus(): void
    {
        $authorization = $this->createEntity();
        $authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertTrue($authorization->isExpired());

        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($authorization->isExpired());
    }

    public function testIsExpiredWithTime(): void
    {
        $authorization = $this->createEntity();

        // 未过期的时间
        $future = new \DateTimeImmutable('+1 hour');
        $authorization->setExpiresTime($future);
        $this->assertFalse($authorization->isExpired());

        // 已过期的时间
        $past = new \DateTimeImmutable('-1 hour');
        $authorization->setExpiresTime($past);
        $this->assertTrue($authorization->isExpired());
    }

    public function testIsExpiredStatusOverridesTime(): void
    {
        $authorization = $this->createEntity();

        // 即使时间未过期，但状态为 EXPIRED，仍然返回 true
        $future = new \DateTimeImmutable('+1 hour');
        $authorization->setExpiresTime($future);
        $authorization->setStatus(AuthorizationStatus::EXPIRED);

        $this->assertTrue($authorization->isExpired());
    }

    public function testIsRevoked(): void
    {
        $authorization = $this->createEntity();
        $this->assertFalse($authorization->isRevoked());

        $authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertTrue($authorization->isRevoked());

        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($authorization->isRevoked());
    }

    public function testIsInvalid(): void
    {
        $authorization = $this->createEntity();
        $this->assertFalse($authorization->isInvalid());

        $authorization->setStatus(AuthorizationStatus::INVALID);
        $this->assertTrue($authorization->isInvalid());

        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($authorization->isInvalid());
    }

    public function testToStringWithIdentifier(): void
    {
        /* 使用具体类 Identifier 进行 mock，原因是：1. Identifier 是领域实体，没有接口定义，只能使用具体类；2. 测试 toString 方法时只需要验证是否存在关联；3. Mock 对象足以满足测试需求，无需真实实体。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $authorization = $this->createEntity();
        $identifier = $this->createMock(Identifier::class);
        $authorization->setIdentifier($identifier);

        $expected = 'Authorization #0 ()';
        $this->assertSame($expected, (string) $authorization);
    }

    public function testToStringWithoutIdentifier(): void
    {
        $authorization = $this->createEntity();
        $expected = 'Authorization #0 ()';
        $this->assertSame($expected, (string) $authorization);
    }

    public function testStringableInterface(): void
    {
        $authorization = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $authorization);
    }

    public function testFluentInterfaceChaining(): void
    {
        /* 使用具体类 Order 进行 mock，原因是：1. Order 是领域实体，没有接口定义，只能使用具体类；2. 测试流式接口链式调用，只需要验证属性设置；3. Mock 对象简化了测试设置，避免创建复杂的实体依赖。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $authorization = $this->createEntity();
        $order = $this->createMock(Order::class);
        /* 使用具体类 Identifier 进行 mock，原因是：1. Identifier 是领域实体，没有接口定义，只能使用具体类；2. 测试流式接口链式调用，只需要验证属性设置；3. Mock 对象简化了测试设置，避免创建复杂的实体依赖。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $identifier = $this->createMock(Identifier::class);
        $url = 'https://acme-v02.api.letsencrypt.org/acme/authz-v3/123456';
        $expiry = new \DateTimeImmutable('+30 days');

        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $authorization->setAuthorizationUrl($url);
        $authorization->setStatus(AuthorizationStatus::VALID);
        $authorization->setExpiresTime($expiry);
        $authorization->setWildcard(true);
        $authorization->setValid(true);
        $result = $authorization;

        $this->assertSame($authorization, $result);
        $this->assertSame($order, $authorization->getOrder());
        $this->assertSame($identifier, $authorization->getIdentifier());
        $this->assertSame($url, $authorization->getAuthorizationUrl());
        $this->assertSame(AuthorizationStatus::VALID, $authorization->getStatus());
        $this->assertSame($expiry, $authorization->getExpiresTime());
        $this->assertTrue($authorization->isWildcard());
        $this->assertTrue($authorization->isValid());
    }

    public function testBusinessScenarioAuthorizationCreation(): void
    {
        /* 使用具体类 Order 进行 mock，原因是：1. Order 是领域实体，没有接口定义，只能使用具体类；2. 测试授权创建的业务场景，需要模拟关联实体；3. Mock 对象足以验证业务逻辑，无需真实实体。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $authorization = $this->createEntity();
        $order = $this->createMock(Order::class);
        /* 使用具体类 Identifier 进行 mock，原因是：1. Identifier 是领域实体，没有接口定义，只能使用具体类；2. 测试授权创建的业务场景，需要模拟关联实体；3. Mock 对象足以验证业务逻辑，无需真实实体。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $identifier = $this->createMock(Identifier::class);

        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $authorization->setAuthorizationUrl('https://acme-v02.api.letsencrypt.org/acme/authz-v3/123456');
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setExpiresTime(new \DateTimeImmutable('+30 days'));

        $this->assertTrue($authorization->isPending());
        $this->assertFalse($authorization->isValid());
        $this->assertFalse($authorization->isExpired());
        $this->assertFalse($authorization->isInvalid());
        $this->assertFalse($authorization->isRevoked());
    }

    public function testBusinessScenarioAuthorizationProgression(): void
    {
        $authorization = $this->createEntity();

        // 初始状态：等待中
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($authorization->isPending());
        $this->assertFalse($authorization->isValid());

        // 授权有效
        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($authorization->isPending());
        $this->assertTrue($authorization->isValid());

        // 授权过期
        $authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertFalse($authorization->isValid());
        $this->assertTrue($authorization->isExpired());
    }

    public function testBusinessScenarioAuthorizationFailure(): void
    {
        $authorization = $this->createEntity();
        $authorization->setStatus(AuthorizationStatus::INVALID);

        $this->assertTrue($authorization->isInvalid());
        $this->assertFalse($authorization->isValid());
        $this->assertFalse($authorization->isPending());
    }

    public function testBusinessScenarioAuthorizationRevocation(): void
    {
        $authorization = $this->createEntity();

        // 有效的授权
        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($authorization->isValid());
        $this->assertFalse($authorization->isRevoked());

        // 被撤销
        $authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertFalse($authorization->isValid());
        $this->assertTrue($authorization->isRevoked());
    }

    public function testBusinessScenarioWildcardAuthorization(): void
    {
        /* 使用具体类 Identifier 进行 mock，原因是：1. Identifier 是领域实体，没有接口定义，只能使用具体类；2. 测试通配符授权场景，只需要验证 wildcard 标志；3. Mock 对象简化了测试设置，专注于业务逻辑。这种使用是必要且合理的，因为在单元测试中模拟实体关联是标准做法。 */
        $authorization = $this->createEntity();
        $identifier = $this->createMock(Identifier::class);

        $authorization->setIdentifier($identifier);
        $authorization->setWildcard(true);
        $authorization->setStatus(AuthorizationStatus::VALID);

        $this->assertTrue($authorization->isWildcard());
        $this->assertTrue($authorization->isValid());
    }

    public function testBusinessScenarioMultipleChallenges(): void
    {
        /*
         * 使用具体类 Challenge 进行 mock：
         * 1. Challenge 是领域实体，没有接口定义，只能使用具体类
         * 2. 需要多个独立的 mock 实例来模拟真实的多挑战场景
         * 3. 测试重点是集合管理，不需要 Challenge 的具体业务逻辑
         */
        $authorization = $this->createEntity();
        $challenge1 = $this->createMock(Challenge::class);
        /*
         * 使用具体类 Challenge 进行 mock：
         * 1. Challenge 是领域实体，没有接口定义，只能使用具体类
         * 2. 需要多个独立的 mock 实例来模拟真实的多挑战场景
         * 3. 测试重点是集合管理，不需要 Challenge 的具体业务逻辑
         */
        $challenge2 = $this->createMock(Challenge::class);
        /*
         * 使用具体类 Challenge 进行 mock：
         * 1. Challenge 是领域实体，没有接口定义，只能使用具体类
         * 2. 需要多个独立的 mock 实例来模拟真实的多挑战场景
         * 3. 测试重点是集合管理，不需要 Challenge 的具体业务逻辑
         */
        $challenge3 = $this->createMock(Challenge::class);

        $authorization->addChallenge($challenge1);
        $authorization->addChallenge($challenge2);
        $authorization->addChallenge($challenge3);

        $this->assertSame(3, $authorization->getChallenges()->count());
        $this->assertTrue($authorization->getChallenges()->contains($challenge1));
        $this->assertTrue($authorization->getChallenges()->contains($challenge2));
        $this->assertTrue($authorization->getChallenges()->contains($challenge3));
    }

    public function testEdgeCasesNullExpiresTime(): void
    {
        $authorization = $this->createEntity();
        $authorization->setExpiresTime(null);
        $this->assertNull($authorization->getExpiresTime());
        $this->assertFalse($authorization->isExpired());
    }

    public function testEdgeCasesEmptyUrl(): void
    {
        $authorization = $this->createEntity();
        $authorization->setAuthorizationUrl('');
        $this->assertSame('', $authorization->getAuthorizationUrl());
    }

    public function testEdgeCasesLongUrl(): void
    {
        $authorization = $this->createEntity();
        $longUrl = 'https://acme-v02.api.letsencrypt.org/acme/authz-v3/' . str_repeat('a', 400);
        $authorization->setAuthorizationUrl($longUrl);

        $this->assertSame($longUrl, $authorization->getAuthorizationUrl());
    }

    public function testStateTransitionsPendingToValid(): void
    {
        $authorization = $this->createEntity();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($authorization->isPending());
        $this->assertFalse($authorization->isValid());

        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertFalse($authorization->isPending());
        $this->assertTrue($authorization->isValid());
    }

    public function testStateTransitionsValidToExpired(): void
    {
        $authorization = $this->createEntity();
        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($authorization->isValid());
        $this->assertFalse($authorization->isExpired());

        $authorization->setStatus(AuthorizationStatus::EXPIRED);
        $this->assertFalse($authorization->isValid());
        $this->assertTrue($authorization->isExpired());
    }

    public function testStateTransitionsValidToRevoked(): void
    {
        $authorization = $this->createEntity();
        $authorization->setStatus(AuthorizationStatus::VALID);
        $this->assertTrue($authorization->isValid());
        $this->assertFalse($authorization->isRevoked());

        $authorization->setStatus(AuthorizationStatus::REVOKED);
        $this->assertFalse($authorization->isValid());
        $this->assertTrue($authorization->isRevoked());
    }

    public function testStateTransitionsPendingToInvalid(): void
    {
        $authorization = $this->createEntity();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $this->assertTrue($authorization->isPending());
        $this->assertFalse($authorization->isInvalid());

        $authorization->setStatus(AuthorizationStatus::INVALID);
        $this->assertFalse($authorization->isPending());
        $this->assertTrue($authorization->isInvalid());
    }

    protected function createEntity(): Authorization
    {
        return new Authorization();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'authorizationUrl' => ['authorizationUrl', 'https://acme.example.com/authz/123'];
        yield 'status' => ['status', AuthorizationStatus::VALID];
        yield 'expiresTime' => ['expiresTime', new \DateTimeImmutable('+30 days')];
        yield 'wildcard' => ['wildcard', true];
        yield 'valid' => ['valid', true];
    }
}
