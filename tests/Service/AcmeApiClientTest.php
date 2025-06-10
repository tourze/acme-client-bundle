<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;

/**
 * AcmeApiClient 测试
 */
class AcmeApiClientTest extends TestCase
{
    private AcmeApiClient $client;
    
    /** @var HttpClientInterface */
    private $httpClient;
    
    /** @var EntityManagerInterface */
    private $entityManager;
    
    /** @var LoggerInterface */
    private $logger;
    
    private string $directoryUrl = 'https://acme-v02.api.letsencrypt.org/directory';

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->client = new AcmeApiClient(
            $this->httpClient,
            $this->entityManager,
            $this->logger,
            $this->directoryUrl,
            3,
            1000
        );
    }

    public function testConstructor(): void
    {
        $client = new AcmeApiClient(
            $this->httpClient,
            $this->entityManager,
            $this->logger,
            $this->directoryUrl
        );
        $this->assertInstanceOf(AcmeApiClient::class, $client);
    }

    public function testGetDirectory(): void
    {
        $expectedDirectory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
            'newOrder' => 'https://acme-v02.api.letsencrypt.org/acme/new-order',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedDirectory);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->directoryUrl)
            ->willReturn($response);

        $result = $this->client->getDirectory();

        $this->assertEquals($expectedDirectory, $result);
    }

    public function testGetDirectoryCached(): void
    {
        $expectedDirectory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedDirectory);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        // 第一次调用
        $result1 = $this->client->getDirectory();
        // 第二次调用应该使用缓存
        $result2 = $this->client->getDirectory();

        $this->assertEquals($expectedDirectory, $result1);
        $this->assertEquals($expectedDirectory, $result2);
    }

    public function testGetDirectoryFailure(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Network error');

        $this->client->getDirectory();
    }

    public function testGetNonce(): void
    {
        $expectedNonce = 'test-nonce-123';
        $directory = ['newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce'];

        // Mock directory response
        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory);

        // Mock nonce response
        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => [$expectedNonce]]);

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse);

        $result = $this->client->getNonce();

        $this->assertEquals($expectedNonce, $result);
    }

    public function testGetNonceNoNewNonceUrl(): void
    {
        $directory = []; // 没有 newNonce URL

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse);

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('newNonce URL not found in directory');

        $this->client->getNonce();
    }

    public function testGetNonceNoNonceInResponse(): void
    {
        $directory = ['newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce'];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory);

        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn([]); // 没有 nonce

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse);

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('No nonce received from server');

        $this->client->getNonce();
    }

    public function testGet(): void
    {
        $url = 'https://example.com/test';
        $expectedData = ['status' => 'valid'];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedData);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $url)
            ->willReturn($response);

        $result = $this->client->get($url);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetFailure(): void
    {
        $url = 'https://example.com/test';

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('HTTP error'));

        $this->expectException(AcmeClientException::class);

        $this->client->get($url);
    }

    public function testPostWithInvalidUrl(): void
    {
        $directory = ['newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct'];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse);

        $this->expectException(AcmeValidationException::class);
        $this->expectExceptionMessage('Invalid URL: invalid-url');

        $this->client->post('invalid-url', [], 'fake-key');
    }

    public function testBusinessScenarioDirectoryAndNonce(): void
    {
        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];
        $nonce = 'business-nonce-123';

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($directory);

        $nonceResponse = $this->createMock(ResponseInterface::class);
        $nonceResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['replay-nonce' => [$nonce]]);

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($directoryResponse, $nonceResponse);

        // 获取目录
        $directoryResult = $this->client->getDirectory();
        $this->assertEquals($directory, $directoryResult);

        // 获取 nonce
        $nonceResult = $this->client->getNonce();
        $this->assertEquals($nonce, $nonceResult);
    }

    public function testEdgeCasesEmptyDirectory(): void
    {
        $emptyDirectory = [];

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($emptyDirectory);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse);

        $result = $this->client->getDirectory();

        $this->assertEquals($emptyDirectory, $result);
    }

    public function testEdgeCasesLargeDirectory(): void
    {
        $largeDirectory = array_fill_keys(
            array_map(fn($i) => "endpoint_$i", range(1, 100)),
            'https://example.com/endpoint'
        );

        $directoryResponse = $this->createMock(ResponseInterface::class);
        $directoryResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($largeDirectory);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($directoryResponse);

        $result = $this->client->getDirectory();

        $this->assertEquals($largeDirectory, $result);
        $this->assertCount(100, $result);
    }

    public function testErrorHandlingNetworkTimeout(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Connection timeout'));

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Connection timeout');

        $this->client->getDirectory();
    }

    public function testErrorHandlingInvalidJson(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willThrowException(new \Exception('Invalid JSON'));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Invalid JSON');

        $this->client->getDirectory();
    }

    public function testMethodExistence(): void
    {
        // 验证关键方法存在
        $this->assertTrue(method_exists($this->client, 'getDirectory'));
        $this->assertTrue(method_exists($this->client, 'getNonce'));
        $this->assertTrue(method_exists($this->client, 'post'));
        $this->assertTrue(method_exists($this->client, 'get'));
    }
}