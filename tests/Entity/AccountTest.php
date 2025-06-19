<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AccountStatus;

/**
 * Account 实体测试类
 */
class AccountTest extends TestCase
{
    private Account $account;

    protected function setUp(): void
    {
        $this->account = new Account();
    }

    public function test_constructor_defaultValues(): void
    {
        $this->assertNull($this->account->getId());
        $this->assertNull($this->account->getAccountUrl());
        $this->assertSame(AccountStatus::PENDING, $this->account->getStatus());
        $this->assertNull($this->account->getContacts());
        $this->assertFalse($this->account->isTermsOfServiceAgreed());
        $this->assertFalse($this->account->isValid());
        $this->assertInstanceOf(ArrayCollection::class, $this->account->getOrders());
        $this->assertTrue($this->account->getOrders()->isEmpty());
    }

    public function test_acmeServerUrl_getterSetter(): void
    {
        $url = 'https://acme-v02.api.letsencrypt.org/directory';
        $result = $this->account->setAcmeServerUrl($url);

        $this->assertSame($this->account, $result);
        $this->assertSame($url, $this->account->getAcmeServerUrl());
    }

    public function test_accountUrl_getterSetter(): void
    {
        $url = 'https://acme-v02.api.letsencrypt.org/acme/acct/123456';
        $result = $this->account->setAccountUrl($url);

        $this->assertSame($this->account, $result);
        $this->assertSame($url, $this->account->getAccountUrl());
    }

    public function test_accountUrl_setToNull(): void
    {
        $this->account->setAccountUrl('https://example.com/acct/123');
        $this->account->setAccountUrl(null);

        $this->assertNull($this->account->getAccountUrl());
    }

    public function test_privateKey_getterSetter(): void
    {
        $privateKey = '-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC7VJTUt9Us8cKBAC...\n-----END PRIVATE KEY-----';
        $result = $this->account->setPrivateKey($privateKey);

        $this->assertSame($this->account, $result);
        $this->assertSame($privateKey, $this->account->getPrivateKey());
    }

    public function test_publicKeyJwk_getterSetter(): void
    {
        $jwk = '{"kty":"RSA","n":"u1SU1LfVLPHCgQC...","e":"AQAB"}';
        $result = $this->account->setPublicKeyJwk($jwk);

        $this->assertSame($this->account, $result);
        $this->assertSame($jwk, $this->account->getPublicKeyJwk());
    }

    public function test_status_getterSetter(): void
    {
        $this->assertSame(AccountStatus::PENDING, $this->account->getStatus());

        $result = $this->account->setStatus(AccountStatus::VALID);
        $this->assertSame($this->account, $result);
        $this->assertSame(AccountStatus::VALID, $this->account->getStatus());
    }

    public function test_status_automaticValidFlag(): void
    {
        // 设置为 VALID 时自动设置 valid 标志为 true
        $this->account->setStatus(AccountStatus::VALID);
        $this->assertTrue($this->account->isValid());

        // 设置为其他状态时 valid 标志为 false
        $this->account->setStatus(AccountStatus::PENDING);
        $this->assertFalse($this->account->isValid());

        $this->account->setStatus(AccountStatus::DEACTIVATED);
        $this->assertFalse($this->account->isValid());
    }

    public function test_contacts_getterSetter(): void
    {
        $contacts = ['mailto:admin@example.com', 'mailto:support@example.com'];
        $result = $this->account->setContacts($contacts);

        $this->assertSame($this->account, $result);
        $this->assertSame($contacts, $this->account->getContacts());
    }

    public function test_contacts_setToNull(): void
    {
        $this->account->setContacts(['mailto:test@example.com']);
        $this->account->setContacts(null);

        $this->assertNull($this->account->getContacts());
    }

    public function test_termsOfServiceAgreed_getterSetter(): void
    {
        $this->assertFalse($this->account->isTermsOfServiceAgreed());

        $result = $this->account->setTermsOfServiceAgreed(true);
        $this->assertSame($this->account, $result);
        $this->assertTrue($this->account->isTermsOfServiceAgreed());

        $this->account->setTermsOfServiceAgreed(false);
        $this->assertFalse($this->account->isTermsOfServiceAgreed());
    }

    public function test_valid_getterSetter(): void
    {
        $this->assertFalse($this->account->isValid());

        $result = $this->account->setValid(true);
        $this->assertSame($this->account, $result);
        $this->assertTrue($this->account->isValid());

        $this->account->setValid(false);
        $this->assertFalse($this->account->isValid());
    }

    public function test_orders_collection(): void
    {
        $orders = $this->account->getOrders();

        $this->assertInstanceOf(ArrayCollection::class, $orders);
        $this->assertTrue($orders->isEmpty());
        $this->assertSame(0, $orders->count());
    }

    public function test_addOrder(): void
    {        $order = $this->createMock(Order::class);

        $result = $this->account->addOrder($order);

        $this->assertSame($this->account, $result);
        $this->assertTrue($this->account->getOrders()->contains($order));
        $this->assertSame(1, $this->account->getOrders()->count());
    }

    public function test_addOrder_preventDuplicates(): void
    {        $order = $this->createMock(Order::class);

        // 添加同一个订单两次
        $this->account->addOrder($order);
        $this->account->addOrder($order);

        // 应该只有一个
        $this->assertSame(1, $this->account->getOrders()->count());
        $this->assertTrue($this->account->getOrders()->contains($order));
    }

    public function test_removeOrder(): void
    {        $order = $this->createMock(Order::class);

        // 先添加订单
        $this->account->getOrders()->add($order);
        $this->assertTrue($this->account->getOrders()->contains($order));

        // 然后移除
        $result = $this->account->removeOrder($order);

        $this->assertSame($this->account, $result);
        $this->assertFalse($this->account->getOrders()->contains($order));
        $this->assertSame(0, $this->account->getOrders()->count());
    }

    public function test_removeOrder_notOwnedByAccount(): void
    {        $order1 = $this->createMock(Order::class);        $order2 = $this->createMock(Order::class);

        $this->account->getOrders()->add($order1);
        $this->account->removeOrder($order2); // 移除不存在的订单

        // order1 应该仍然存在
        $this->assertTrue($this->account->getOrders()->contains($order1));
        $this->assertSame(1, $this->account->getOrders()->count());
    }

    public function test_isDeactivated(): void
    {
        $this->assertFalse($this->account->isDeactivated());

        $this->account->setStatus(AccountStatus::PENDING);
        $this->assertFalse($this->account->isDeactivated());

        $this->account->setStatus(AccountStatus::VALID);
        $this->assertFalse($this->account->isDeactivated());

        $this->account->setStatus(AccountStatus::DEACTIVATED);
        $this->assertTrue($this->account->isDeactivated());
    }

    public function test_toString_withoutId(): void
    {
        $this->account->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');

        $expected = 'Account #0 (https://acme-staging-v02.api.letsencrypt.org/directory)';
        $this->assertSame($expected, (string) $this->account);
    }

    public function test_toString_withEmptyValues(): void
    {
        $expected = 'Account #0 ()';
        $this->assertSame($expected, (string) $this->account);
    }

    public function test_stringableInterface(): void
    {
        $this->assertInstanceOf(\Stringable::class, $this->account);
    }

    public function test_fluentInterface_chaining(): void
    {
        $serverUrl = 'https://acme-v02.api.letsencrypt.org/directory';
        $accountUrl = 'https://acme-v02.api.letsencrypt.org/acme/acct/123456';
        $privateKey = '-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----';
        $publicKeyJwk = '{"kty":"RSA","n":"test","e":"AQAB"}';
        $contacts = ['mailto:test@example.com'];

        $result = $this->account
            ->setAcmeServerUrl($serverUrl)
            ->setAccountUrl($accountUrl)
            ->setPrivateKey($privateKey)
            ->setPublicKeyJwk($publicKeyJwk)
            ->setStatus(AccountStatus::VALID)
            ->setContacts($contacts)
            ->setTermsOfServiceAgreed(true);

        $this->assertSame($this->account, $result);
        $this->assertSame($serverUrl, $this->account->getAcmeServerUrl());
        $this->assertSame($accountUrl, $this->account->getAccountUrl());
        $this->assertSame($privateKey, $this->account->getPrivateKey());
        $this->assertSame($publicKeyJwk, $this->account->getPublicKeyJwk());
        $this->assertSame(AccountStatus::VALID, $this->account->getStatus());
        $this->assertSame($contacts, $this->account->getContacts());
        $this->assertTrue($this->account->isTermsOfServiceAgreed());
        $this->assertTrue($this->account->isValid());
    }

    public function test_businessScenario_accountRegistration(): void
    {
        // 新注册的账户
        $this->account
            ->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory')
            ->setPrivateKey('-----BEGIN PRIVATE KEY-----\ntest_key\n-----END PRIVATE KEY-----')
            ->setPublicKeyJwk('{"kty":"RSA","n":"test","e":"AQAB"}')
            ->setContacts(['mailto:admin@example.com'])
            ->setTermsOfServiceAgreed(true)
            ->setStatus(AccountStatus::PENDING);

        $this->assertSame(AccountStatus::PENDING, $this->account->getStatus());
        $this->assertFalse($this->account->isValid());
        $this->assertTrue($this->account->isTermsOfServiceAgreed());
        $this->assertNotEmpty($this->account->getContacts());
    }

    public function test_businessScenario_accountActivation(): void
    {
        // 账户激活流程
        $this->account
            ->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory')
            ->setStatus(AccountStatus::PENDING);

        $this->assertFalse($this->account->isValid());

        // 模拟 ACME 服务器验证后的状态更新
        $this->account
            ->setAccountUrl('https://acme-v02.api.letsencrypt.org/acme/acct/123456')
            ->setStatus(AccountStatus::VALID);

        $this->assertTrue($this->account->isValid());
        $this->assertSame(AccountStatus::VALID, $this->account->getStatus());
        $this->assertNotNull($this->account->getAccountUrl());
    }

    public function test_businessScenario_accountDeactivation(): void
    {
        // 已激活的账户
        $this->account
            ->setStatus(AccountStatus::VALID)
            ->setValid(true);

        $this->assertTrue($this->account->isValid());
        $this->assertFalse($this->account->isDeactivated());

        // 停用账户
        $this->account->setStatus(AccountStatus::DEACTIVATED);

        $this->assertFalse($this->account->isValid());
        $this->assertTrue($this->account->isDeactivated());
        $this->assertSame(AccountStatus::DEACTIVATED, $this->account->getStatus());
    }

    public function test_businessScenario_multipleOrders(): void
    {        $order1 = $this->createMock(Order::class);        $order2 = $this->createMock(Order::class);        $order3 = $this->createMock(Order::class);

        $this->account
            ->addOrder($order1)
            ->addOrder($order2)
            ->addOrder($order3);

        $this->assertSame(3, $this->account->getOrders()->count());
        $this->assertTrue($this->account->getOrders()->contains($order1));
        $this->assertTrue($this->account->getOrders()->contains($order2));
        $this->assertTrue($this->account->getOrders()->contains($order3));
    }

    public function test_edgeCases_emptyContacts(): void
    {
        $this->account->setContacts([]);
        $this->assertSame([], $this->account->getContacts());
    }

    public function test_edgeCases_longUrls(): void
    {
        $longUrl = 'https://acme-v02.api.letsencrypt.org/directory/' . str_repeat('a', 400);
        $this->account->setAcmeServerUrl($longUrl);

        $this->assertSame($longUrl, $this->account->getAcmeServerUrl());
    }

    public function test_edgeCases_specialCharactersInKey(): void
    {
        $keyWithSpecialChars = "-----BEGIN PRIVATE KEY-----\n特殊字符\n中文\n-----END PRIVATE KEY-----";
        $this->account->setPrivateKey($keyWithSpecialChars);

        $this->assertSame($keyWithSpecialChars, $this->account->getPrivateKey());
    }
}
