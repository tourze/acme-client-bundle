<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeApiClient::class)]
#[RunTestsInSeparateProcesses]
final class AcmeApiClientTest extends AbstractIntegrationTestCase
{
    private AcmeApiClient $client;

    /** @var HttpClientInterface&MockObject */
    private HttpClientInterface $httpClient;

    private string $directoryUrl = 'https://acme-staging-v02.api.letsencrypt.org/directory';

    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    private function initializeMocks(): void
    {
        /*
         * 使用接口 HttpClientInterface 的 Mock 对象
         * 原因：HttpClientInterface 是标准接口，有对应的接口抽象
         * 合理性：在测试中需要隔离外部 HTTP 调用，使用 Mock 是必要的测试实践
         */
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        self::getContainer()->set(HttpClientInterface::class, $this->httpClient);

        $this->client = self::getService(AcmeApiClient::class);
    }

    public function testConstructor(): void
    {
        $this->initializeMocks();
        $this->assertInstanceOf(AcmeApiClient::class, $this->client);
    }

    public function testGetDirectory(): void
    {
        $this->initializeMocks();
        $expectedDirectory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
            'newOrder' => 'https://acme-v02.api.letsencrypt.org/acme/new-order',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedDirectory)
        ;
        $response->expects($this->any())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->directoryUrl)
            ->willReturn($response)
        ;

        $result = $this->client->getDirectory();

        $this->assertEquals($expectedDirectory, $result);
    }

    public function testGetDirectoryCached(): void
    {
        $this->initializeMocks();
        $expectedDirectory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedDirectory)
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        // 第一次调用
        $result1 = $this->client->getDirectory();
        // 第二次调用应该使用缓存
        $result2 = $this->client->getDirectory();

        $this->assertEquals($expectedDirectory, $result1);
        $this->assertEquals($expectedDirectory, $result2);
    }

    public function testGetDirectoryFailure(): void
    {
        $this->initializeMocks();
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'))
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Network error');

        $this->client->getDirectory();
    }

    public function testGetNonce(): void
    {
        $this->initializeMocks();
        $expectedNonce = 'test-nonce-123';
        $directory = ['newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce'];

        // Mock directory response
        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        // Mock nonce response
        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => [$expectedNonce]])
        ;

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse)
        ;

        $result = $this->client->getNonce();

        $this->assertEquals($expectedNonce, $result);
    }

    public function testGetNonceNoNewNonceUrl(): void
    {
        $this->initializeMocks();
        $directory = []; // 没有 newNonce URL

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse)
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('newNonce URL not found in directory');

        $this->client->getNonce();
    }

    public function testGetNonceNoNonceInResponse(): void
    {
        $this->initializeMocks();
        $directory = ['newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce'];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn([]) // 没有 nonce
        ;

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse)
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('No nonce received from server');

        $this->client->getNonce();
    }

    public function testGet(): void
    {
        $this->initializeMocks();
        $url = 'https://example.com/test';
        $expectedData = ['status' => 'valid'];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedData)
        ;
        $response->expects($this->any())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $url)
            ->willReturn($response)
        ;

        $result = $this->client->get($url);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetFailure(): void
    {
        $this->initializeMocks();
        $url = 'https://example.com/test';

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('HTTP error'))
        ;

        $this->expectException(AbstractAcmeException::class);

        $this->client->get($url);
    }

    public function testPostWithInvalidUrl(): void
    {
        $this->initializeMocks();
        $directory = ['newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct'];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse)
        ;

        $this->expectException(AcmeValidationException::class);
        $this->expectExceptionMessage('Invalid URL: invalid-url');

        $this->client->post('invalid-url', [], 'fake-key');
    }

    public function testBusinessScenarioDirectoryAndNonce(): void
    {
        $this->initializeMocks();
        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];
        $nonce = 'business-nonce-123';

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => [$nonce]])
        ;

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse)
        ;

        // 获取目录
        $directoryResult = $this->client->getDirectory();
        $this->assertEquals($directory, $directoryResult);

        // 获取 nonce
        $nonceResult = $this->client->getNonce();
        $this->assertEquals($nonce, $nonceResult);
    }

    public function testEdgeCasesEmptyDirectory(): void
    {
        $this->initializeMocks();
        $emptyDirectory = [];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($emptyDirectory)
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse)
        ;

        $result = $this->client->getDirectory();

        $this->assertEquals($emptyDirectory, $result);
    }

    public function testEdgeCasesLargeDirectory(): void
    {
        $this->initializeMocks();
        $largeDirectory = array_fill_keys(
            array_map(fn ($i) => "endpoint_{$i}", range(1, 100)),
            'https://example.com/endpoint'
        );

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($largeDirectory)
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse)
        ;

        $result = $this->client->getDirectory();

        $this->assertEquals($largeDirectory, $result);
        $this->assertCount(100, $result);
    }

    public function testErrorHandlingNetworkTimeout(): void
    {
        $this->initializeMocks();
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Connection timeout'))
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Connection timeout');

        $this->client->getDirectory();
    }

    public function testErrorHandlingInvalidJson(): void
    {
        $this->initializeMocks();
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willThrowException(new \Exception('Invalid JSON'))
        ;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Invalid JSON');

        $this->client->getDirectory();
    }

    public function testCreateAccount(): void
    {
        $this->initializeMocks();
        $contacts = ['mailto:test@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();

        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];
        $nonce = 'test-nonce-create-account';
        $accountResponse = [
            'status' => 'valid',
            'contact' => $contacts,
            'orders' => 'https://acme-v02.api.letsencrypt.org/acme/acct/123/orders',
        ];

        // Mock directory response
        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        // Mock nonce response
        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => [$nonce]])
        ;

        // Mock post response
        $postResponse = $this->createMock(ResponseInterface::class);
        $postResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($accountResponse)
        ;
        $postResponse->expects($this->atLeastOnce())
            ->method('getStatusCode')
            ->willReturn(201)
        ;
        $postResponse->expects($this->atLeastOnce())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => ['next-nonce']])
        ;

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnCallback(function ($method, $url) use ($directoryResponse, $nonceResponse, $postResponse) {
                /** @var int $callCount */
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertEquals('GET', $method);

                    return $directoryResponse;
                }
                if (2 === $callCount) {
                    $this->assertEquals('HEAD', $method);

                    return $nonceResponse;
                }
                $this->assertEquals('POST', $method);

                return $postResponse;
            })
        ;

        $result = $this->client->createAccount($contacts, $privateKeyPem);

        $this->assertEquals($accountResponse, $result);
    }

    public function testCreateAccountWithInvalidPrivateKey(): void
    {
        $this->initializeMocks();
        $contacts = ['mailto:test@example.com'];
        $invalidPrivateKey = 'invalid-private-key';

        $this->expectException(AcmeValidationException::class);
        $this->expectExceptionMessage('Invalid private key provided');

        $this->client->createAccount($contacts, $invalidPrivateKey);
    }

    public function testCreateAccountWithMultipleContacts(): void
    {
        $this->initializeMocks();
        $contacts = ['mailto:admin@example.com', 'mailto:support@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();

        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];
        $accountResponse = [
            'status' => 'valid',
            'contact' => $contacts,
        ];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => ['test-nonce-multiple']])
        ;

        $postResponse = $this->createMock(ResponseInterface::class);
        $postResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($accountResponse)
        ;
        $postResponse->expects($this->atLeastOnce())
            ->method('getStatusCode')
            ->willReturn(201)
        ;
        $postResponse->expects($this->atLeastOnce())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => ['next-nonce']])
        ;

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse, $postResponse)
        ;

        $result = $this->client->createAccount($contacts, $privateKeyPem);

        $this->assertEquals($accountResponse, $result);
        $this->assertEquals($contacts, $result['contact']);
    }

    public function testCreateAccountWithEmptyContacts(): void
    {
        $this->initializeMocks();
        $contacts = [];
        $privateKeyPem = $this->generateTestPrivateKey();

        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];
        $accountResponse = [
            'status' => 'valid',
            'contact' => [],
        ];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => ['test-nonce-empty']])
        ;

        $postResponse = $this->createMock(ResponseInterface::class);
        $postResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($accountResponse)
        ;
        $postResponse->expects($this->atLeastOnce())
            ->method('getStatusCode')
            ->willReturn(201)
        ;
        $postResponse->expects($this->atLeastOnce())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => ['next-nonce']])
        ;

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse, $postResponse)
        ;

        $result = $this->client->createAccount($contacts, $privateKeyPem);

        $this->assertEquals($accountResponse, $result);
        $this->assertEmpty($result['contact']);
    }

    public function testCreateAccountWithServerError(): void
    {
        $this->initializeMocks();
        $contacts = ['mailto:test@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();

        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory)
        ;

        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => ['test-nonce-error']])
        ;

        $postResponse = $this->createMock(ResponseInterface::class);
        $postResponse->expects($this->atLeastOnce())
            ->method('getStatusCode')
            ->willReturn(500)
        ;
        $postResponse->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('{"type":"urn:ietf:params:acme:error:serverInternal","detail":"Internal server error"}')
        ;
        $postResponse->expects($this->atLeastOnce())
            ->method('getHeaders')
            ->willReturn([])
        ;

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse, $postResponse)
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Internal server error');

        $this->client->createAccount($contacts, $privateKeyPem);
    }

    private function generateTestPrivateKey(): string
    {
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (false === $privateKey) {
            throw new CertificateGenerationException('Failed to generate private key');
        }

        $privateKeyPem = '';
        $result = openssl_pkey_export($privateKey, $privateKeyPem);
        if (!$result || !is_string($privateKeyPem)) {
            throw new CertificateGenerationException('Failed to export private key');
        }

        return $privateKeyPem;
    }
}
