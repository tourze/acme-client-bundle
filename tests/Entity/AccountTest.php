<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * Account 实体测试类
 *
 * @internal
 */
#[CoversClass(Account::class)]
final class AccountTest extends AbstractEntityTestCase
{
    public function testStatusAutomaticValidFlag(): void
    {
        // 设置为 VALID 时自动设置 valid 标志为 true
        $account = $this->createEntity();
        $account->setStatus(AccountStatus::VALID);
        $this->assertTrue($account->isValid());

        // 设置为其他状态时 valid 标志为 false
        $account->setStatus(AccountStatus::PENDING);
        $this->assertFalse($account->isValid());

        $account->setStatus(AccountStatus::DEACTIVATED);
        $this->assertFalse($account->isValid());
    }

    public function testOrdersCollection(): void
    {
        $account = $this->createEntity();
        $orders = $account->getOrders();

        $this->assertInstanceOf(ArrayCollection::class, $orders);
        $this->assertTrue($orders->isEmpty());
        $this->assertSame(0, $orders->count());
    }

    public function testAddOrder(): void
    {
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试实体间关联关系
         * 2. 测试只需要验证集合操作，不涉及 Order 的具体行为
         * 3. 使用 mock 避免创建复杂的实体对象依赖
         */
        $account = $this->createEntity();
        $order = $this->createMock(Order::class);

        $account->addOrder($order);
        $this->assertTrue($account->getOrders()->contains($order));
        $this->assertSame(1, $account->getOrders()->count());
    }

    public function testAddOrderPreventDuplicates(): void
    {
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试重复添加的处理逻辑
         * 2. 测试关注集合的去重功能，不需要 Order 的实际实现
         * 3. Mock 对象足以验证集合的行为
         */
        $account = $this->createEntity();
        $order = $this->createMock(Order::class);

        // 添加同一个订单两次
        $account->addOrder($order);
        $account->addOrder($order);

        // 应该只有一个
        $this->assertSame(1, $account->getOrders()->count());
        $this->assertTrue($account->getOrders()->contains($order));
    }

    public function testRemoveOrder(): void
    {
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试从集合中移除的功能
         * 2. 测试只关注集合操作，不需要 Order 的具体实现
         * 3. Mock 简化了测试设置，专注于被测试的行为
         */
        $account = $this->createEntity();
        $order = $this->createMock(Order::class);

        // 先添加订单
        $account->getOrders()->add($order);
        $this->assertTrue($account->getOrders()->contains($order));

        // 然后移除
        $account->removeOrder($order);
        $this->assertFalse($account->getOrders()->contains($order));
        $this->assertSame(0, $account->getOrders()->count());
    }

    public function testRemoveOrderNotOwnedByAccount(): void
    {
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试移除不存在的订单的场景
         * 2. 需要两个不同的 mock 实例来区分不同的订单
         * 3. 测试集合的边界情况，mock 足以满足需求
         */
        $account = $this->createEntity();
        $order1 = $this->createMock(Order::class);
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试移除不存在的订单的场景
         * 2. 需要两个不同的 mock 实例来区分不同的订单
         * 3. 测试集合的边界情况，mock 足以满足需求
         */
        $order2 = $this->createMock(Order::class);

        $account->getOrders()->add($order1);
        $account->removeOrder($order2); // 移除不存在的订单

        // order1 应该仍然存在
        $this->assertTrue($account->getOrders()->contains($order1));
        $this->assertSame(1, $account->getOrders()->count());
    }

    public function testIsDeactivated(): void
    {
        $account = $this->createEntity();
        $this->assertFalse($account->isDeactivated());

        $account->setStatus(AccountStatus::PENDING);
        $this->assertFalse($account->isDeactivated());

        $account->setStatus(AccountStatus::VALID);
        $this->assertFalse($account->isDeactivated());

        $account->setStatus(AccountStatus::DEACTIVATED);
        $this->assertTrue($account->isDeactivated());
    }

    public function testToStringWithoutId(): void
    {
        $account = $this->createEntity();
        $account->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');

        $expected = 'Account #0 (https://acme-staging-v02.api.letsencrypt.org/directory)';
        $this->assertSame($expected, (string) $account);
    }

    public function testToStringWithEmptyValues(): void
    {
        $account = $this->createEntity();
        $account->setAcmeServerUrl('https://acme.example.com');

        $expected = 'Account #0 (https://acme.example.com)';
        $this->assertSame($expected, (string) $account);
    }

    public function testStringableInterface(): void
    {
        $account = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $account);
    }

    public function testFluentInterfaceChaining(): void
    {
        $account = $this->createEntity();
        $serverUrl = 'https://acme-v02.api.letsencrypt.org/directory';
        $accountUrl = 'https://acme-v02.api.letsencrypt.org/acme/acct/123456';
        $privateKey = '-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----';
        $publicKeyJwk = '{"kty":"RSA","n":"test","e":"AQAB"}';
        $contacts = ['mailto:test@example.com'];

        $account->setAcmeServerUrl($serverUrl);
        $account->setAccountUrl($accountUrl);
        $account->setPrivateKey($privateKey);
        $account->setPublicKeyJwk($publicKeyJwk);
        $account->setStatus(AccountStatus::VALID);
        $account->setContacts($contacts);
        $account->setTermsOfServiceAgreed(true);
        $this->assertSame($serverUrl, $account->getAcmeServerUrl());
        $this->assertSame($accountUrl, $account->getAccountUrl());
        $this->assertSame($privateKey, $account->getPrivateKey());
        $this->assertSame($publicKeyJwk, $account->getPublicKeyJwk());
        $this->assertSame(AccountStatus::VALID, $account->getStatus());
        $this->assertSame($contacts, $account->getContacts());
        $this->assertTrue($account->isTermsOfServiceAgreed());
        $this->assertTrue($account->isValid());
    }

    public function testBusinessScenarioAccountRegistration(): void
    {
        // 新注册的账户
        $account = $this->createEntity();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('-----BEGIN PRIVATE KEY-----\ntest_key\n-----END PRIVATE KEY-----');
        $account->setPublicKeyJwk('{"kty":"RSA","n":"test","e":"AQAB"}');
        $account->setContacts(['mailto:admin@example.com']);
        $account->setTermsOfServiceAgreed(true);
        $account->setStatus(AccountStatus::PENDING);

        $this->assertSame(AccountStatus::PENDING, $account->getStatus());
        $this->assertFalse($account->isValid());
        $this->assertTrue($account->isTermsOfServiceAgreed());
        $this->assertNotEmpty($account->getContacts());
    }

    public function testBusinessScenarioAccountActivation(): void
    {
        // 账户激活流程
        $account = $this->createEntity();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setStatus(AccountStatus::PENDING);

        $this->assertFalse($account->isValid());

        // 模拟 ACME 服务器验证后的状态更新
        $account->setAccountUrl('https://acme-v02.api.letsencrypt.org/acme/acct/123456');
        $account->setStatus(AccountStatus::VALID);

        $this->assertTrue($account->isValid());
        $this->assertSame(AccountStatus::VALID, $account->getStatus());
        $this->assertNotNull($account->getAccountUrl());
    }

    public function testBusinessScenarioAccountDeactivation(): void
    {
        // 已激活的账户
        $account = $this->createEntity();
        $account->setStatus(AccountStatus::VALID);
        $account->setValid(true);

        $this->assertTrue($account->isValid());
        $this->assertFalse($account->isDeactivated());

        // 停用账户
        $account->setStatus(AccountStatus::DEACTIVATED);

        $this->assertFalse($account->isValid());
        $this->assertTrue($account->isDeactivated());
        $this->assertSame(AccountStatus::DEACTIVATED, $account->getStatus());
    }

    public function testBusinessScenarioMultipleOrders(): void
    {
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试账户管理多个订单的业务场景
         * 2. 需要多个独立的 mock 实例来模拟真实的多订单场景
         * 3. 测试重点是集合管理，不需要 Order 的具体业务逻辑
         */
        $account = $this->createEntity();
        $order1 = $this->createMock(Order::class);
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试账户管理多个订单的业务场景
         * 2. 需要多个独立的 mock 实例来模拟真实的多订单场景
         * 3. 测试重点是集合管理，不需要 Order 的具体业务逻辑
         */
        $order2 = $this->createMock(Order::class);
        /*
         * 使用具体类 Order 进行 mock：
         * 1. Order 是实体类，用于测试账户管理多个订单的业务场景
         * 2. 需要多个独立的 mock 实例来模拟真实的多订单场景
         * 3. 测试重点是集合管理，不需要 Order 的具体业务逻辑
         */
        $order3 = $this->createMock(Order::class);

        $account->addOrder($order1);
        $account->addOrder($order2);
        $account->addOrder($order3);

        $this->assertSame(3, $account->getOrders()->count());
        $this->assertTrue($account->getOrders()->contains($order1));
        $this->assertTrue($account->getOrders()->contains($order2));
        $this->assertTrue($account->getOrders()->contains($order3));
    }

    public function testEdgeCasesEmptyContacts(): void
    {
        $account = $this->createEntity();
        $account->setContacts([]);
        $this->assertSame([], $account->getContacts());
    }

    public function testEdgeCasesLongUrls(): void
    {
        $account = $this->createEntity();
        $longUrl = 'https://acme-v02.api.letsencrypt.org/directory/' . str_repeat('a', 400);
        $account->setAcmeServerUrl($longUrl);

        $this->assertSame($longUrl, $account->getAcmeServerUrl());
    }

    public function testEdgeCasesSpecialCharactersInKey(): void
    {
        $account = $this->createEntity();
        $keyWithSpecialChars = "-----BEGIN PRIVATE KEY-----\n特殊字符\n中文\n-----END PRIVATE KEY-----";
        $account->setPrivateKey($keyWithSpecialChars);

        $this->assertSame($keyWithSpecialChars, $account->getPrivateKey());
    }

    protected function createEntity(): Account
    {
        return new Account();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'acmeServerUrl' => ['acmeServerUrl', 'https://acme.example.com'];
        yield 'accountUrl' => ['accountUrl', 'https://acme.example.com/acct/123'];
        yield 'privateKey' => ['privateKey', '-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----'];
        yield 'publicKeyJwk' => ['publicKeyJwk', '{"kty":"RSA","n":"test","e":"AQAB"}'];
        yield 'status' => ['status', AccountStatus::VALID];
        yield 'contacts' => ['contacts', ['mailto:test@example.com']];
        yield 'termsOfServiceAgreed' => ['termsOfServiceAgreed', true];
        yield 'valid' => ['valid', true];
    }
}
