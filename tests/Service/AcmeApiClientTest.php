<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * AcmeApiClient 集成测试
 *
 * 使用 MockHttpClient 模拟 HTTP 响应，因为：
 * 1. 测试需要隔离外部 ACME 服务器调用
 * 2. Mock 网络请求是合理的测试实践
 *
 * @internal
 */
#[CoversClass(AcmeApiClient::class)]
#[RunTestsInSeparateProcesses]
final class AcmeApiClientTest extends AbstractIntegrationTestCase
{
    private string $directoryUrl = 'https://acme-staging-v02.api.letsencrypt.org/directory';
    private MockHttpClient $mockHttpClient;
    private AcmeApiClient $client;

    protected function onSetUp(): void
    {
        // 获取容器中的 MockHttpClient 实例（使用测试服务 ID）
        $mockHttpClient = self::getServiceById('Test.MockHttpClient');
        if (!$mockHttpClient instanceof MockHttpClient) {
            throw new \RuntimeException('Expected MockHttpClient instance');
        }
        $this->mockHttpClient = $mockHttpClient;

        // 获取使用该 MockHttpClient 的 AcmeApiClient 服务（使用测试服务 ID）
        $client = self::getServiceById('Test.AcmeApiClient');
        if (!$client instanceof AcmeApiClient) {
            throw new \RuntimeException('Expected AcmeApiClient instance');
        }
        $this->client = $client;
    }

    /**
     * 使用响应数组设置 MockHttpClient
     *
     * @param MockResponse[] $responses
     */
    private function setMockResponses(array $responses): void
    {
        $index = 0;
        $this->mockHttpClient->setResponseFactory(function () use ($responses, &$index) {
            if ($index < count($responses)) {
                return $responses[$index++];
            }

            return new MockResponse('', ['http_code' => 500]);
        });
    }

    /**
     * 使用回调函数设置 MockHttpClient
     *
     * @param callable $callback 接收 (string $method, string $url, array $options) 返回 MockResponse
     */
    private function setMockCallback(callable $callback): void
    {
        $this->mockHttpClient->setResponseFactory($callback);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(AcmeApiClient::class, $this->client);
    }

    public function testGetDirectory(): void
    {
        $expectedDirectory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
            'newOrder' => 'https://acme-v02.api.letsencrypt.org/acme/new-order',
        ];

        $this->setMockCallback(function (string $method, string $url) use ($expectedDirectory) {
            $this->assertEquals('GET', $method);
            $this->assertEquals($this->directoryUrl, $url);

            return new MockResponse(json_encode($expectedDirectory), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]);
        });

        $result = $this->client->getDirectory();

        $this->assertEquals($expectedDirectory, $result);
    }

    public function testGetDirectoryCached(): void
    {
        $expectedDirectory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
        ];

        $callCount = 0;
        $this->setMockCallback(function () use ($expectedDirectory, &$callCount) {
            ++$callCount;

            return new MockResponse(json_encode($expectedDirectory), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]);
        });

        // 第一次调用
        $result1 = $this->client->getDirectory();
        // 第二次调用应该使用缓存
        $result2 = $this->client->getDirectory();

        $this->assertEquals($expectedDirectory, $result1);
        $this->assertEquals($expectedDirectory, $result2);
        // 验证只调用了一次 HTTP 请求（缓存生效）
        $this->assertEquals(1, $callCount);
    }

    public function testGetDirectoryFailure(): void
    {
        $this->setMockCallback(function () {
            throw new \RuntimeException('Network error');
        });

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Network error');

        $this->client->getDirectory();
    }

    public function testGetNonce(): void
    {
        $expectedNonce = 'test-nonce-123';
        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];

        $callCount = 0;
        $this->setMockCallback(function (string $method, string $url) use ($directory, $expectedNonce, &$callCount) {
            ++$callCount;

            if (1 === $callCount) {
                // Directory request
                $this->assertEquals('GET', $method);

                return new MockResponse(json_encode($directory), [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ]);
            }

            // Nonce request (HEAD)
            $this->assertEquals('HEAD', $method);

            return new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['replay-nonce' => $expectedNonce],
            ]);
        });

        $result = $this->client->getNonce();

        $this->assertEquals($expectedNonce, $result);
    }

    public function testGetNonceNoNewNonceUrl(): void
    {
        $directory = []; // 没有 newNonce URL

        $this->setMockResponses([
            new MockResponse(json_encode($directory), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('newNonce URL not found in directory');

        $this->client->getNonce();
    }

    public function testGetNonceNoNonceInResponse(): void
    {
        $directory = ['newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce'];

        $this->setMockResponses([
            // Directory response
            new MockResponse(json_encode($directory), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
            // Nonce response without Replay-Nonce header
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ]);

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('No nonce received from server');

        $this->client->getNonce();
    }

    public function testGet(): void
    {
        $url = 'https://example.com/test';
        $expectedData = ['status' => 'valid'];

        $this->setMockCallback(function (string $method, string $requestUrl) use ($url, $expectedData) {
            $this->assertEquals('GET', $method);
            $this->assertEquals($url, $requestUrl);

            return new MockResponse(json_encode($expectedData), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]);
        });

        $result = $this->client->get($url);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetFailure(): void
    {
        $url = 'https://example.com/test';

        $this->setMockCallback(function () {
            throw new \RuntimeException('HTTP error');
        });

        $this->expectException(AbstractAcmeException::class);

        $this->client->get($url);
    }

    public function testPostWithInvalidUrl(): void
    {
        $directory = ['newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct'];

        $this->setMockResponses([
            new MockResponse(json_encode($directory), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

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

        $callCount = 0;
        $this->setMockCallback(function (string $method) use ($directory, $nonce, &$callCount) {
            ++$callCount;

            if (1 === $callCount) {
                // Directory request
                return new MockResponse(json_encode($directory), [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ]);
            }

            // Nonce request
            return new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['replay-nonce' => $nonce],
            ]);
        });

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

        $this->setMockResponses([
            new MockResponse(json_encode($emptyDirectory), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $result = $this->client->getDirectory();

        $this->assertEquals($emptyDirectory, $result);
    }

    public function testEdgeCasesLargeDirectory(): void
    {
        $largeDirectory = array_fill_keys(
            array_map(fn ($i) => "endpoint_{$i}", range(1, 100)),
            'https://example.com/endpoint'
        );

        $this->setMockResponses([
            new MockResponse(json_encode($largeDirectory), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $result = $this->client->getDirectory();

        $this->assertEquals($largeDirectory, $result);
        $this->assertCount(100, $result);
    }

    public function testErrorHandlingNetworkTimeout(): void
    {
        $this->setMockCallback(function () {
            throw new \Exception('Connection timeout');
        });

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Failed to fetch ACME directory: Connection timeout');

        $this->client->getDirectory();
    }

    public function testErrorHandlingInvalidJson(): void
    {
        $this->setMockResponses([
            new MockResponse('invalid json {', [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $this->expectException(AbstractAcmeException::class);

        $this->client->getDirectory();
    }

    public function testCreateAccount(): void
    {
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

        $callCount = 0;
        $this->setMockCallback(function (string $method) use ($directory, $nonce, $accountResponse, &$callCount) {
            ++$callCount;

            if (1 === $callCount) {
                // Directory request
                $this->assertEquals('GET', $method);

                return new MockResponse(json_encode($directory), [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ]);
            }
            if (2 === $callCount) {
                // Nonce request
                $this->assertEquals('HEAD', $method);

                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => ['replay-nonce' => $nonce],
                ]);
            }

            // Account creation request
            $this->assertEquals('POST', $method);

            return new MockResponse(json_encode($accountResponse), [
                'http_code' => 201,
                'response_headers' => [
                    'Content-Type' => 'application/json',
                    'replay-nonce' => 'next-nonce',
                ],
            ]);
        });

        $result = $this->client->createAccount($contacts, $privateKeyPem);

        $this->assertEquals($accountResponse, $result);
    }

    public function testCreateAccountWithInvalidPrivateKey(): void
    {
        $contacts = ['mailto:test@example.com'];
        $invalidPrivateKey = 'invalid-private-key';

        $this->setMockResponses([]);

        $this->expectException(AcmeValidationException::class);
        $this->expectExceptionMessage('Invalid private key provided');

        $this->client->createAccount($contacts, $invalidPrivateKey);
    }

    public function testCreateAccountWithMultipleContacts(): void
    {
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

        $callCount = 0;
        $this->setMockCallback(function () use ($directory, $accountResponse, &$callCount) {
            ++$callCount;

            if (1 === $callCount) {
                return new MockResponse(json_encode($directory), [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ]);
            }
            if (2 === $callCount) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => ['replay-nonce' => 'test-nonce-multiple'],
                ]);
            }

            return new MockResponse(json_encode($accountResponse), [
                'http_code' => 201,
                'response_headers' => [
                    'Content-Type' => 'application/json',
                    'replay-nonce' => 'next-nonce',
                ],
            ]);
        });

        $result = $this->client->createAccount($contacts, $privateKeyPem);

        $this->assertEquals($accountResponse, $result);
        $this->assertEquals($contacts, $result['contact']);
    }

    public function testCreateAccountWithEmptyContacts(): void
    {
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

        $callCount = 0;
        $this->setMockCallback(function () use ($directory, $accountResponse, &$callCount) {
            ++$callCount;

            if (1 === $callCount) {
                return new MockResponse(json_encode($directory), [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ]);
            }
            if (2 === $callCount) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => ['replay-nonce' => 'test-nonce-empty'],
                ]);
            }

            return new MockResponse(json_encode($accountResponse), [
                'http_code' => 201,
                'response_headers' => [
                    'Content-Type' => 'application/json',
                    'replay-nonce' => 'next-nonce',
                ],
            ]);
        });

        $result = $this->client->createAccount($contacts, $privateKeyPem);

        $this->assertEquals($accountResponse, $result);
        $this->assertEmpty($result['contact']);
    }

    public function testCreateAccountWithServerError(): void
    {
        $contacts = ['mailto:test@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();

        $directory = [
            'newAccount' => 'https://acme-v02.api.letsencrypt.org/acme/new-acct',
            'newNonce' => 'https://acme-v02.api.letsencrypt.org/acme/new-nonce',
        ];

        $callCount = 0;
        $this->setMockCallback(function () use ($directory, &$callCount) {
            ++$callCount;

            if (1 === $callCount) {
                return new MockResponse(json_encode($directory), [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ]);
            }
            if (2 === $callCount) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => ['replay-nonce' => 'test-nonce-error'],
                ]);
            }

            return new MockResponse('{"type":"urn:ietf:params:acme:error:serverInternal","detail":"Internal server error"}', [
                'http_code' => 500,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]);
        });

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
