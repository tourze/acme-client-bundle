<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;

/**
 * Challenge 实体测试类
 */
class ChallengeTest extends TestCase
{
    private Challenge $challenge;

    protected function setUp(): void
    {
        $this->challenge = new Challenge();
    }

    public function test_constructor_defaultValues(): void
    {
        $this->assertNull($this->challenge->getId());
        $this->assertNull($this->challenge->getAuthorization());
        $this->assertSame(ChallengeType::DNS_01, $this->challenge->getType());
        $this->assertSame(ChallengeStatus::PENDING, $this->challenge->getStatus());
        $this->assertNull($this->challenge->getDnsRecordName());
        $this->assertNull($this->challenge->getDnsRecordValue());
        $this->assertNull($this->challenge->getValidatedTime());
        $this->assertNull($this->challenge->getError());
        $this->assertFalse($this->challenge->isValid());
    }

    public function test_authorization_getterSetter(): void
    {
        $authorization = $this->createMock(Authorization::class);
        $result = $this->challenge->setAuthorization($authorization);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($authorization, $this->challenge->getAuthorization());
    }

    public function test_authorization_setToNull(): void
    {
        $authorization = $this->createMock(Authorization::class);
        $this->challenge->setAuthorization($authorization);

        $this->challenge->setAuthorization(null);
        $this->assertNull($this->challenge->getAuthorization());
    }

    public function test_challengeUrl_getterSetter(): void
    {
        $url = 'https://acme-v02.api.letsencrypt.org/acme/chall-v3/123456/abc';
        $result = $this->challenge->setChallengeUrl($url);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($url, $this->challenge->getChallengeUrl());
    }

    public function test_type_getterSetter(): void
    {
        $this->assertSame(ChallengeType::DNS_01, $this->challenge->getType());

        $result = $this->challenge->setType(ChallengeType::DNS_01);
        $this->assertSame($this->challenge, $result);
        $this->assertSame(ChallengeType::DNS_01, $this->challenge->getType());
    }

    public function test_status_getterSetter(): void
    {
        $this->assertSame(ChallengeStatus::PENDING, $this->challenge->getStatus());

        $result = $this->challenge->setStatus(ChallengeStatus::VALID);
        $this->assertSame($this->challenge, $result);
        $this->assertSame(ChallengeStatus::VALID, $this->challenge->getStatus());
    }

    public function test_status_automaticValidFlag(): void
    {
        // 设置为 VALID 时自动设置 valid 标志为 true
        $this->challenge->setStatus(ChallengeStatus::VALID);
        $this->assertTrue($this->challenge->isValid());

        // 设置为其他状态时 valid 标志为 false
        $this->challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertFalse($this->challenge->isValid());

        $this->challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertFalse($this->challenge->isValid());

        $this->challenge->setStatus(ChallengeStatus::INVALID);
        $this->assertFalse($this->challenge->isValid());
    }

    public function test_token_getterSetter(): void
    {
        $token = 'test_token_123456789';
        $result = $this->challenge->setToken($token);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($token, $this->challenge->getToken());
    }

    public function test_keyAuthorization_getterSetter(): void
    {
        $keyAuth = 'test_token_123456789.test_key_auth_content';
        $result = $this->challenge->setKeyAuthorization($keyAuth);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($keyAuth, $this->challenge->getKeyAuthorization());
    }

    public function test_dnsRecordName_getterSetter(): void
    {
        $this->assertNull($this->challenge->getDnsRecordName());

        $recordName = '_acme-challenge.example.com';
        $result = $this->challenge->setDnsRecordName($recordName);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($recordName, $this->challenge->getDnsRecordName());
    }

    public function test_dnsRecordName_setToNull(): void
    {
        $this->challenge->setDnsRecordName('_acme-challenge.example.com');
        $this->challenge->setDnsRecordName(null);

        $this->assertNull($this->challenge->getDnsRecordName());
    }

    public function test_dnsRecordValue_getterSetter(): void
    {
        $this->assertNull($this->challenge->getDnsRecordValue());

        $recordValue = 'test_dns_record_value_base64';
        $result = $this->challenge->setDnsRecordValue($recordValue);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($recordValue, $this->challenge->getDnsRecordValue());
    }

    public function test_dnsRecordValue_setToNull(): void
    {
        $this->challenge->setDnsRecordValue('test_value');
        $this->challenge->setDnsRecordValue(null);

        $this->assertNull($this->challenge->getDnsRecordValue());
    }

    public function test_validatedTime_getterSetter(): void
    {
        $this->assertNull($this->challenge->getValidatedTime());

        $validatedTime = new \DateTimeImmutable();
        $result = $this->challenge->setValidatedTime($validatedTime);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($validatedTime, $this->challenge->getValidatedTime());
    }

    public function test_validatedTime_setToNull(): void
    {
        $this->challenge->setValidatedTime(new \DateTimeImmutable());
        $this->challenge->setValidatedTime(null);

        $this->assertNull($this->challenge->getValidatedTime());
    }

    public function test_error_getterSetter(): void
    {
        $this->assertNull($this->challenge->getError());

        $error = [
            'type' => 'urn:ietf:params:acme:error:dns',
            'detail' => 'DNS record not found',
            'status' => 400
        ];
        $result = $this->challenge->setError($error);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($error, $this->challenge->getError());
    }

    public function test_error_setToNull(): void
    {
        $this->challenge->setError(['type' => 'test']);
        $this->challenge->setError(null);

        $this->assertNull($this->challenge->getError());
    }

    public function test_valid_getterSetter(): void
    {
        $this->assertFalse($this->challenge->isValid());

        $result = $this->challenge->setValid(true);
        $this->assertSame($this->challenge, $result);
        $this->assertTrue($this->challenge->isValid());

        $this->challenge->setValid(false);
        $this->assertFalse($this->challenge->isValid());
    }

    public function test_isInvalid(): void
    {
        $this->assertFalse($this->challenge->isInvalid());

        $this->challenge->setStatus(ChallengeStatus::INVALID);
        $this->assertTrue($this->challenge->isInvalid());

        $this->challenge->setStatus(ChallengeStatus::VALID);
        $this->assertFalse($this->challenge->isInvalid());
    }

    public function test_isProcessing(): void
    {
        $this->assertFalse($this->challenge->isProcessing());

        $this->challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($this->challenge->isProcessing());

        $this->challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertFalse($this->challenge->isProcessing());
    }

    public function test_isDns01(): void
    {
        // Challenge 默认就是 DNS-01 类型
        $this->assertTrue($this->challenge->isDns01());

        // 显式设置 DNS-01 类型
        $this->challenge->setType(ChallengeType::DNS_01);
        $this->assertTrue($this->challenge->isDns01());
    }

    public function test_getFullDnsRecordName_withValue(): void
    {
        $recordName = '_acme-challenge.example.com';
        $this->challenge->setDnsRecordName($recordName);

        $this->assertSame($recordName, $this->challenge->getFullDnsRecordName());
    }

    public function test_getFullDnsRecordName_withNull(): void
    {
        $this->challenge->setDnsRecordName(null);

        $this->assertSame('', $this->challenge->getFullDnsRecordName());
    }

    public function test_calculateDnsRecordValue_withKeyAuthorization(): void
    {
        $keyAuth = 'test_token.test_key_auth';
        $this->challenge->setKeyAuthorization($keyAuth);

        // 计算预期值：SHA256 + Base64URL
        $hash = hash('sha256', $keyAuth, true);
        $expectedValue = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        $this->assertSame($expectedValue, $this->challenge->calculateDnsRecordValue());
    }

    public function test_calculateDnsRecordValue_withEmptyKeyAuthorization(): void
    {
        // 没有设置 keyAuthorization 时应该返回空字符串
        $this->assertSame('', $this->challenge->calculateDnsRecordValue());
    }

    public function test_toString(): void
    {
        $this->challenge->setType(ChallengeType::DNS_01);

        $expected = 'Challenge #0 (dns-01)';
        $this->assertSame($expected, (string)$this->challenge);
    }

    public function test_stringableInterface(): void
    {
        $this->assertInstanceOf(\Stringable::class, $this->challenge);
    }

    public function test_fluentInterface_chaining(): void
    {
        $authorization = $this->createMock(Authorization::class);
        $url = 'https://acme-v02.api.letsencrypt.org/acme/chall-v3/123456/abc';
        $token = 'test_token_123';
        $keyAuth = 'test_token_123.test_key_auth';
        $dnsName = '_acme-challenge.example.com';
        $dnsValue = 'test_dns_value';
        $validatedTime = new \DateTimeImmutable();
        $error = ['type' => 'test', 'detail' => 'test error'];

        $result = $this->challenge
            ->setAuthorization($authorization)
            ->setChallengeUrl($url)
            ->setType(ChallengeType::DNS_01)
            ->setStatus(ChallengeStatus::VALID)
            ->setToken($token)
            ->setKeyAuthorization($keyAuth)
            ->setDnsRecordName($dnsName)
            ->setDnsRecordValue($dnsValue)
            ->setValidatedTime($validatedTime)
            ->setError($error)
            ->setValid(true);

        $this->assertSame($this->challenge, $result);
        $this->assertSame($authorization, $this->challenge->getAuthorization());
        $this->assertSame($url, $this->challenge->getChallengeUrl());
        $this->assertSame(ChallengeType::DNS_01, $this->challenge->getType());
        $this->assertSame(ChallengeStatus::VALID, $this->challenge->getStatus());
        $this->assertSame($token, $this->challenge->getToken());
        $this->assertSame($keyAuth, $this->challenge->getKeyAuthorization());
        $this->assertSame($dnsName, $this->challenge->getDnsRecordName());
        $this->assertSame($dnsValue, $this->challenge->getDnsRecordValue());
        $this->assertSame($validatedTime, $this->challenge->getValidatedTime());
        $this->assertSame($error, $this->challenge->getError());
        $this->assertTrue($this->challenge->isValid());
    }

    public function test_businessScenario_challengeCreation(): void
    {
        $authorization = $this->createMock(Authorization::class);

        $this->challenge
            ->setAuthorization($authorization)
            ->setChallengeUrl('https://acme-v02.api.letsencrypt.org/acme/chall-v3/123456/abc')
            ->setType(ChallengeType::DNS_01)
            ->setStatus(ChallengeStatus::PENDING)
            ->setToken('test_token_123456789')
            ->setKeyAuthorization('test_token_123456789.test_key_auth');

        $this->assertSame(ChallengeStatus::PENDING, $this->challenge->getStatus());
        $this->assertFalse($this->challenge->isValid());
        $this->assertFalse($this->challenge->isProcessing());
        $this->assertFalse($this->challenge->isInvalid());
        $this->assertTrue($this->challenge->isDns01());
    }

    public function test_businessScenario_dnsRecordSetup(): void
    {
        $token = 'test_token_123456789';
        $keyAuth = 'test_token_123456789.test_key_auth_content';

        $this->challenge
            ->setToken($token)
            ->setKeyAuthorization($keyAuth)
            ->setDnsRecordName('_acme-challenge.example.com');

        // DNS 记录名称应该正确设置
        $this->assertSame('_acme-challenge.example.com', $this->challenge->getFullDnsRecordName());

        // DNS 记录值应该根据 keyAuthorization 计算
        $calculatedValue = $this->challenge->calculateDnsRecordValue();
        $this->assertNotEmpty($calculatedValue);

        // 验证 Base64URL 编码特征（无填充，使用 - 和 _）
        $this->assertStringNotContainsString('+', $calculatedValue);
        $this->assertStringNotContainsString('/', $calculatedValue);
        $this->assertStringNotContainsString('=', $calculatedValue);
    }

    public function test_businessScenario_challengeProgression(): void
    {
        // 初始状态：等待中
        $this->challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertSame(ChallengeStatus::PENDING, $this->challenge->getStatus());
        $this->assertFalse($this->challenge->isValid());
        $this->assertFalse($this->challenge->isProcessing());

        // 处理中
        $this->challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($this->challenge->isProcessing());
        $this->assertFalse($this->challenge->isValid());

        // 验证成功
        $this->challenge
            ->setStatus(ChallengeStatus::VALID)
            ->setValidatedTime(new \DateTimeImmutable());

        $this->assertTrue($this->challenge->isValid());
        $this->assertFalse($this->challenge->isProcessing());
        $this->assertNotNull($this->challenge->getValidatedTime());
    }

    public function test_businessScenario_challengeFailure(): void
    {
        $error = [
            'type' => 'urn:ietf:params:acme:error:dns',
            'detail' => 'No TXT record found at _acme-challenge.example.com',
            'status' => 400
        ];

        $this->challenge
            ->setStatus(ChallengeStatus::INVALID)
            ->setError($error);

        $this->assertTrue($this->challenge->isInvalid());
        $this->assertFalse($this->challenge->isValid());
        $this->assertNotNull($this->challenge->getError());
        $this->assertSame('urn:ietf:params:acme:error:dns', $this->challenge->getError()['type']);
        $this->assertStringContainsString('TXT record', $this->challenge->getError()['detail']);
    }

    public function test_businessScenario_wildcardDomainChallenge(): void
    {
        $this->challenge
            ->setToken('wildcard_token_123')
            ->setKeyAuthorization('wildcard_token_123.wildcard_key_auth')
            ->setDnsRecordName('_acme-challenge.example.com') // 通配符域名的挑战记录
            ->setType(ChallengeType::DNS_01)
            ->setStatus(ChallengeStatus::VALID);

        $this->assertTrue($this->challenge->isDns01());
        $this->assertTrue($this->challenge->isValid());
        $this->assertSame('_acme-challenge.example.com', $this->challenge->getFullDnsRecordName());

        // 通配符域名的 DNS 记录值计算应该正常工作
        $dnsValue = $this->challenge->calculateDnsRecordValue();
        $this->assertNotEmpty($dnsValue);
    }

    public function test_edgeCases_emptyUrl(): void
    {
        $this->challenge->setChallengeUrl('');
        $this->assertSame('', $this->challenge->getChallengeUrl());
    }

    public function test_edgeCases_longToken(): void
    {
        $longToken = str_repeat('a', 200);
        $this->challenge->setToken($longToken);

        $this->assertSame($longToken, $this->challenge->getToken());
    }

    public function test_edgeCases_emptyError(): void
    {
        $this->challenge->setError([]);
        $this->assertSame([], $this->challenge->getError());
    }

    public function test_edgeCases_complexDnsRecordName(): void
    {
        $complexName = '_acme-challenge.sub.domain.example.com';
        $this->challenge->setDnsRecordName($complexName);

        $this->assertSame($complexName, $this->challenge->getFullDnsRecordName());
    }

    public function test_stateTransitions_pendingToProcessing(): void
    {
        $this->challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertSame(ChallengeStatus::PENDING, $this->challenge->getStatus());
        $this->assertFalse($this->challenge->isProcessing());

        $this->challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertSame(ChallengeStatus::PROCESSING, $this->challenge->getStatus());
        $this->assertTrue($this->challenge->isProcessing());
    }

    public function test_stateTransitions_processingToValid(): void
    {
        $this->challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($this->challenge->isProcessing());
        $this->assertFalse($this->challenge->isValid());

        $this->challenge->setStatus(ChallengeStatus::VALID);
        $this->assertFalse($this->challenge->isProcessing());
        $this->assertTrue($this->challenge->isValid());
    }

    public function test_stateTransitions_processingToInvalid(): void
    {
        $this->challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($this->challenge->isProcessing());
        $this->assertFalse($this->challenge->isInvalid());

        $this->challenge->setStatus(ChallengeStatus::INVALID);
        $this->assertFalse($this->challenge->isProcessing());
        $this->assertTrue($this->challenge->isInvalid());
    }

    public function test_cryptographicFunctions_sha256Calculation(): void
    {
        // 测试实际的 SHA256 + Base64URL 编码
        $keyAuth = 'test_token.test_key_fingerprint';
        $this->challenge->setKeyAuthorization($keyAuth);

        $result = $this->challenge->calculateDnsRecordValue();

        // 验证结果是有效的 Base64URL 编码
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $result);

        // 验证长度（SHA256 hash 编码后应该是43个字符，去掉填充）
        $this->assertSame(43, strlen($result));

        // 验证一致性：相同输入应该产生相同输出
        $this->assertSame($result, $this->challenge->calculateDnsRecordValue());
    }

    public function test_cryptographicFunctions_differentInputsDifferentOutputs(): void
    {
        $this->challenge->setKeyAuthorization('input1');
        $result1 = $this->challenge->calculateDnsRecordValue();

        $this->challenge->setKeyAuthorization('input2');
        $result2 = $this->challenge->calculateDnsRecordValue();

        $this->assertNotSame($result1, $result2);
    }
}
