<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * Challenge 实体测试类
 *
 * @internal
 */
#[CoversClass(Challenge::class)]
final class ChallengeTest extends AbstractEntityTestCase
{
    public function testConstructorDefaultValues(): void
    {
        $challenge = $this->createEntity();
        $this->assertNull($challenge->getId());
        $this->assertNull($challenge->getAuthorization());
        $this->assertSame(ChallengeType::DNS_01, $challenge->getType());
        $this->assertSame(ChallengeStatus::PENDING, $challenge->getStatus());
        $this->assertNull($challenge->getDnsRecordName());
        $this->assertNull($challenge->getDnsRecordValue());
        $this->assertNull($challenge->getValidatedTime());
        $this->assertNull($challenge->getError());
        $this->assertFalse($challenge->isValid());
    }

    public function testStatusAutomaticValidFlag(): void
    {
        $challenge = $this->createEntity();

        // 设置为 VALID 时自动设置 valid 标志为 true
        $challenge->setStatus(ChallengeStatus::VALID);
        $this->assertTrue($challenge->isValid());

        // 设置为其他状态时 valid 标志为 false
        $challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertFalse($challenge->isValid());

        $challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertFalse($challenge->isValid());

        $challenge->setStatus(ChallengeStatus::INVALID);
        $this->assertFalse($challenge->isValid());
    }

    public function testIsInvalid(): void
    {
        $challenge = $this->createEntity();
        $this->assertFalse($challenge->isInvalid());

        $challenge->setStatus(ChallengeStatus::INVALID);
        $this->assertTrue($challenge->isInvalid());

        $challenge->setStatus(ChallengeStatus::VALID);
        $this->assertFalse($challenge->isInvalid());
    }

    public function testIsProcessing(): void
    {
        $challenge = $this->createEntity();
        $this->assertFalse($challenge->isProcessing());

        $challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($challenge->isProcessing());

        $challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertFalse($challenge->isProcessing());
    }

    public function testIsDns01(): void
    {
        $challenge = $this->createEntity();

        // Challenge 默认就是 DNS-01 类型
        $this->assertTrue($challenge->isDns01());

        // 显式设置 DNS-01 类型
        $challenge->setType(ChallengeType::DNS_01);
        $this->assertTrue($challenge->isDns01());
    }

    public function testGetFullDnsRecordNameWithValue(): void
    {
        $challenge = $this->createEntity();
        $recordName = '_acme-challenge.example.com';
        $challenge->setDnsRecordName($recordName);

        $this->assertSame($recordName, $challenge->getFullDnsRecordName());
    }

    public function testGetFullDnsRecordNameWithNull(): void
    {
        $challenge = $this->createEntity();
        $challenge->setDnsRecordName(null);

        $this->assertSame('', $challenge->getFullDnsRecordName());
    }

    public function testCalculateDnsRecordValueWithKeyAuthorization(): void
    {
        $challenge = $this->createEntity();
        $keyAuth = 'test_token.test_key_auth';
        $challenge->setKeyAuthorization($keyAuth);

        // 计算预期值：SHA256 + Base64URL
        $hash = hash('sha256', $keyAuth, true);
        $expectedValue = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        $this->assertSame($expectedValue, $challenge->calculateDnsRecordValue());
    }

    public function testCalculateDnsRecordValueWithEmptyKeyAuthorization(): void
    {
        $challenge = $this->createEntity();

        // 没有设置 keyAuthorization 时应该返回空字符串
        $this->assertSame('', $challenge->calculateDnsRecordValue());
    }

    public function testToString(): void
    {
        $challenge = $this->createEntity();
        $challenge->setType(ChallengeType::DNS_01);

        $expected = 'Challenge #0 (dns-01)';
        $this->assertSame($expected, (string) $challenge);
    }

    public function testStringableInterface(): void
    {
        $challenge = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $challenge);
    }

    public function testFluentInterfaceChaining(): void
    {
        $challenge = $this->createEntity();
        $authorization = $this->createMock(Authorization::class);
        $url = 'https://acme-v02.api.letsencrypt.org/acme/chall-v3/123456/abc';
        $token = 'test_token_123';
        $keyAuth = 'test_token_123.test_key_auth';
        $dnsName = '_acme-challenge.example.com';
        $dnsValue = 'test_dns_value';
        $validatedTime = new \DateTimeImmutable();
        $error = ['type' => 'test', 'detail' => 'test error'];

        $challenge->setAuthorization($authorization);
        $challenge->setChallengeUrl($url);
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setStatus(ChallengeStatus::VALID);
        $challenge->setToken($token);
        $challenge->setKeyAuthorization($keyAuth);
        $challenge->setDnsRecordName($dnsName);
        $challenge->setDnsRecordValue($dnsValue);
        $challenge->setValidatedTime($validatedTime);
        $challenge->setError($error);
        $challenge->setValid(true);
        $result = $challenge;

        $this->assertSame($challenge, $result);
        $this->assertSame($authorization, $challenge->getAuthorization());
        $this->assertSame($url, $challenge->getChallengeUrl());
        $this->assertSame(ChallengeType::DNS_01, $challenge->getType());
        $this->assertSame(ChallengeStatus::VALID, $challenge->getStatus());
        $this->assertSame($token, $challenge->getToken());
        $this->assertSame($keyAuth, $challenge->getKeyAuthorization());
        $this->assertSame($dnsName, $challenge->getDnsRecordName());
        $this->assertSame($dnsValue, $challenge->getDnsRecordValue());
        $this->assertSame($validatedTime, $challenge->getValidatedTime());
        $this->assertSame($error, $challenge->getError());
        $this->assertTrue($challenge->isValid());
    }

    public function testBusinessScenarioChallengeCreation(): void
    {
        $challenge = $this->createEntity();
        $authorization = $this->createMock(Authorization::class);

        $challenge->setAuthorization($authorization);
        $challenge->setChallengeUrl('https://acme-v02.api.letsencrypt.org/acme/chall-v3/123456/abc');
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setStatus(ChallengeStatus::PENDING);
        $challenge->setToken('test_token_123456789');
        $challenge->setKeyAuthorization('test_token_123456789.test_key_auth');

        $this->assertSame(ChallengeStatus::PENDING, $challenge->getStatus());
        $this->assertFalse($challenge->isValid());
        $this->assertFalse($challenge->isProcessing());
        $this->assertFalse($challenge->isInvalid());
        $this->assertTrue($challenge->isDns01());
    }

    public function testBusinessScenarioDnsRecordSetup(): void
    {
        $challenge = $this->createEntity();
        $token = 'test_token_123456789';
        $keyAuth = 'test_token_123456789.test_key_auth_content';

        $challenge->setToken($token);
        $challenge->setKeyAuthorization($keyAuth);
        $challenge->setDnsRecordName('_acme-challenge.example.com');

        // DNS 记录名称应该正确设置
        $this->assertSame('_acme-challenge.example.com', $challenge->getFullDnsRecordName());

        // DNS 记录值应该根据 keyAuthorization 计算
        $calculatedValue = $challenge->calculateDnsRecordValue();
        $this->assertNotEmpty($calculatedValue);

        // 验证 Base64URL 编码特征（无填充，使用 - 和 _）
        $this->assertStringNotContainsString('+', $calculatedValue);
        $this->assertStringNotContainsString('/', $calculatedValue);
        $this->assertStringNotContainsString('=', $calculatedValue);
    }

    public function testBusinessScenarioChallengeProgression(): void
    {
        $challenge = $this->createEntity();

        // 初始状态：等待中
        $challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertSame(ChallengeStatus::PENDING, $challenge->getStatus());
        $this->assertFalse($challenge->isValid());
        $this->assertFalse($challenge->isProcessing());

        // 处理中
        $challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($challenge->isProcessing());
        $this->assertFalse($challenge->isValid());

        // 验证成功
        $challenge->setStatus(ChallengeStatus::VALID);
        $challenge->setValidatedTime(new \DateTimeImmutable());

        $this->assertTrue($challenge->isValid());
        $this->assertFalse($challenge->isProcessing());
        $this->assertNotNull($challenge->getValidatedTime());
    }

    public function testBusinessScenarioChallengeFailure(): void
    {
        $challenge = $this->createEntity();
        $error = [
            'type' => 'urn:ietf:params:acme:error:dns',
            'detail' => 'No TXT record found at _acme-challenge.example.com',
            'status' => 400,
        ];

        $challenge->setStatus(ChallengeStatus::INVALID);
        $challenge->setError($error);

        $this->assertTrue($challenge->isInvalid());
        $this->assertFalse($challenge->isValid());
        $error = $challenge->getError();
        $this->assertIsArray($error);
        $this->assertSame('urn:ietf:params:acme:error:dns', $error['type']);
        $this->assertIsString($error['detail']);
        $this->assertStringContainsString('TXT record', $error['detail']);
    }

    public function testBusinessScenarioWildcardDomainChallenge(): void
    {
        $challenge = $this->createEntity();

        $challenge->setToken('wildcard_token_123');
        $challenge->setKeyAuthorization('wildcard_token_123.wildcard_key_auth');
        $challenge->setDnsRecordName('_acme-challenge.example.com'); // 通配符域名的挑战记录
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setStatus(ChallengeStatus::VALID);

        $this->assertTrue($challenge->isDns01());
        $this->assertTrue($challenge->isValid());
        $this->assertSame('_acme-challenge.example.com', $challenge->getFullDnsRecordName());

        // 通配符域名的 DNS 记录值计算应该正常工作
        $dnsValue = $challenge->calculateDnsRecordValue();
        $this->assertNotEmpty($dnsValue);
    }

    public function testEdgeCasesEmptyUrl(): void
    {
        $challenge = $this->createEntity();
        $challenge->setChallengeUrl('');
        $this->assertSame('', $challenge->getChallengeUrl());
    }

    public function testEdgeCasesLongToken(): void
    {
        $challenge = $this->createEntity();
        $longToken = str_repeat('a', 200);
        $challenge->setToken($longToken);

        $this->assertSame($longToken, $challenge->getToken());
    }

    public function testEdgeCasesEmptyError(): void
    {
        $challenge = $this->createEntity();
        $challenge->setError([]);
        $this->assertSame([], $challenge->getError());
    }

    public function testEdgeCasesComplexDnsRecordName(): void
    {
        $challenge = $this->createEntity();
        $complexName = '_acme-challenge.sub.domain.example.com';
        $challenge->setDnsRecordName($complexName);

        $this->assertSame($complexName, $challenge->getFullDnsRecordName());
    }

    public function testStateTransitionsPendingToProcessing(): void
    {
        $challenge = $this->createEntity();

        $challenge->setStatus(ChallengeStatus::PENDING);
        $this->assertSame(ChallengeStatus::PENDING, $challenge->getStatus());
        $this->assertFalse($challenge->isProcessing());

        $challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertSame(ChallengeStatus::PROCESSING, $challenge->getStatus());
        $this->assertTrue($challenge->isProcessing());
    }

    public function testStateTransitionsProcessingToValid(): void
    {
        $challenge = $this->createEntity();

        $challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($challenge->isProcessing());
        $this->assertFalse($challenge->isValid());

        $challenge->setStatus(ChallengeStatus::VALID);
        $this->assertFalse($challenge->isProcessing());
        $this->assertTrue($challenge->isValid());
    }

    public function testStateTransitionsProcessingToInvalid(): void
    {
        $challenge = $this->createEntity();

        $challenge->setStatus(ChallengeStatus::PROCESSING);
        $this->assertTrue($challenge->isProcessing());
        $this->assertFalse($challenge->isInvalid());

        $challenge->setStatus(ChallengeStatus::INVALID);
        $this->assertFalse($challenge->isProcessing());
        $this->assertTrue($challenge->isInvalid());
    }

    public function testCryptographicFunctionsSha256Calculation(): void
    {
        $challenge = $this->createEntity();

        // 测试实际的 SHA256 + Base64URL 编码
        $keyAuth = 'test_token.test_key_fingerprint';
        $challenge->setKeyAuthorization($keyAuth);

        $result = $challenge->calculateDnsRecordValue();

        // 验证结果是有效的 Base64URL 编码
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $result);

        // 验证长度（SHA256 hash 编码后应该是43个字符，去掉填充）
        $this->assertSame(43, strlen($result));

        // 验证一致性：相同输入应该产生相同输出
        $this->assertSame($result, $challenge->calculateDnsRecordValue());
    }

    public function testCryptographicFunctionsDifferentInputsDifferentOutputs(): void
    {
        $challenge = $this->createEntity();

        $challenge->setKeyAuthorization('input1');
        $result1 = $challenge->calculateDnsRecordValue();

        $challenge->setKeyAuthorization('input2');
        $result2 = $challenge->calculateDnsRecordValue();

        $this->assertNotSame($result1, $result2);
    }

    protected function createEntity(): Challenge
    {
        return new Challenge();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'challengeUrl' => ['challengeUrl', 'https://acme.example.com/challenge/123'];
        yield 'type' => ['type', ChallengeType::DNS_01];
        yield 'status' => ['status', ChallengeStatus::VALID];
        yield 'token' => ['token', 'test_token_123'];
        yield 'keyAuthorization' => ['keyAuthorization', 'test_token_123.test_key_auth'];
        yield 'dnsRecordName' => ['dnsRecordName', '_acme-challenge.example.com'];
        yield 'dnsRecordValue' => ['dnsRecordValue', 'test_dns_value'];
        yield 'validatedTime' => ['validatedTime', new \DateTimeImmutable()];
        yield 'error' => ['error', ['type' => 'test', 'detail' => 'test error']];
        yield 'valid' => ['valid', true];
    }
}
