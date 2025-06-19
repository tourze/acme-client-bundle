<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Exception\AcmeRateLimitException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;

/**
 * ACME API 客户端
 * 
 * 负责与 ACME 服务器进行低级别的 HTTP 通信，处理 JWS 签名、nonce 管理等
 */
class AcmeApiClient
{
    private ?array $directory = null;
    private ?string $currentNonce = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $directoryUrl,
        private readonly int $maxRetries = 3,
        private readonly int $retryDelay = 1000 // 毫秒
    ) {}

    /**
     * 获取 ACME 目录信息
     */
    public function getDirectory(): array
    {
        if ($this->directory === null) {
            $startTime = microtime(true);

            try {
                $response = $this->httpClient->request('GET', $this->directoryUrl);
                $this->directory = $response->toArray();

                $this->logOperation(
                    'directory_fetch',
                    'Successfully fetched ACME directory',
                    LogLevel::INFO,
                    $startTime,
                    ['url' => $this->directoryUrl]
                );
            } catch (\Throwable $e) {
                $this->logException($e, 'directory_fetch', ['url' => $this->directoryUrl]);
                throw new AcmeClientException(
                    "Failed to fetch ACME directory: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $this->directory;
    }

    /**
     * 获取新的 nonce
     */
    public function getNonce(): string
    {
        if ($this->currentNonce === null) {
            $directory = $this->getDirectory();
            $newNonceUrl = $directory['newNonce'] ?? null;

            if (!$newNonceUrl) {
                throw new AcmeClientException('newNonce URL not found in directory');
            }

            $startTime = microtime(true);

            try {
                $response = $this->httpClient->request('HEAD', $newNonceUrl);
                $this->currentNonce = $response->getHeaders()['replay-nonce'][0] ?? null;

                if ($this->currentNonce === null) {
                    throw new AcmeClientException('No nonce received from server');
                }

                $this->logOperation(
                    'nonce_fetch',
                    'Successfully fetched new nonce',
                    LogLevel::DEBUG,
                    $startTime,
                    ['url' => $newNonceUrl]
                );
            } catch (\Throwable $e) {
                $this->logException($e, 'nonce_fetch', ['url' => $newNonceUrl]);
                throw new AcmeClientException(
                    "Failed to fetch nonce: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $this->currentNonce;
    }

    /**
     * 发起 ACME POST 请求
     */
    public function post(string $endpoint, array $payload, $accountKey, ?string $kid = null): array
    {
        $directory = $this->getDirectory();
        $url = $directory[$endpoint] ?? $endpoint;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new AcmeValidationException("Invalid URL: {$url}");
        }

        return $this->makeSignedRequest('POST', $url, $payload, $accountKey, $kid);
    }

    /**
     * 发起 ACME GET 请求
     */
    public function get(string $url): array
    {
        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            $this->logOperation(
                'acme_get',
                "GET request to {$url}",
                LogLevel::DEBUG,
                $startTime,
                ['url' => $url, 'status' => $response->getStatusCode()]
            );

            return $data;
        } catch (\Throwable $e) {
            $this->logException($e, 'acme_get', ['url' => $url]);
            throw $this->convertHttpException($e);
        }
    }

    /**
     * 发起已签名的请求
     */
    private function makeSignedRequest(string $method, string $url, array $payload, $accountKey, ?string $kid = null): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            $startTime = microtime(true);

            try {
                $nonce = $this->getNonce();
                $jws = $this->createJws($payload, $url, $accountKey, $nonce, $kid);

                $response = $this->httpClient->request($method, $url, [
                    'headers' => ['Content-Type' => 'application/jose+json'],
                    'body' => $jws,
                ]);

                // 更新 nonce
                $this->updateNonceFromResponse($response);

                // 处理响应
                $statusCode = $response->getStatusCode();
                $responseData = $this->parseResponse($response);

                $this->logOperation(
                    'acme_request',
                    "{$method} request to {$url}",
                    LogLevel::INFO,
                    $startTime,
                    [
                        'url' => $url,
                        'method' => $method,
                        'status' => $statusCode,
                        'attempt' => $attempt
                    ]
                );

                return $responseData;
            } catch (\Throwable $e) {
                $lastException = $e;

                // 如果是速率限制且还有重试次数，等待后重试
                if ($e instanceof AcmeRateLimitException && $attempt < $this->maxRetries) {
                    usleep($this->retryDelay * 1000);
                    continue;
                }

                // 如果是 nonce 错误，清除当前 nonce 并重试
                if ($this->isBadNonceError($e) && $attempt < $this->maxRetries) {
                    $this->currentNonce = null;
                    continue;
                }

                // 其他错误直接抛出
                break;
            }
        }

        $this->logException($lastException, 'acme_request', [
            'url' => $url,
            'method' => $method,
            'attempts' => $attempt
        ]);

        throw $lastException;
    }

    /**
     * 创建 JWS 签名
     */
    private function createJws(array $payload, string $url, $accountKey, string $nonce, ?string $kid = null): string
    {
        // 构建 protected header
        $protected = [
            'alg' => $this->getAlgorithm($accountKey),
            'nonce' => $nonce,
            'url' => $url,
        ];

        if ($kid !== null) {
            $protected['kid'] = $kid;
        } else {
            $protected['jwk'] = $this->createJwk($accountKey);
        }

        $protectedEncoded = $this->base64UrlEncode(json_encode($protected));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        // 创建签名
        $signingInput = "{$protectedEncoded}.{$payloadEncoded}";
        $signature = $this->sign($signingInput, $accountKey);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return json_encode([
            'protected' => $protectedEncoded,
            'payload' => $payloadEncoded,
            'signature' => $signatureEncoded,
        ]);
    }

    /**
     * 创建 JWK (JSON Web Key)
     */
    private function createJwk($publicKey): array
    {
        $keyDetails = openssl_pkey_get_details($publicKey);

        if ($keyDetails['type'] === OPENSSL_KEYTYPE_RSA) {
            return [
                'kty' => 'RSA',
                'n' => $this->base64UrlEncode($keyDetails['rsa']['n']),
                'e' => $this->base64UrlEncode($keyDetails['rsa']['e']),
            ];
        }

        if ($keyDetails['type'] === OPENSSL_KEYTYPE_EC) {
            return [
                'kty' => 'EC',
                'crv' => $this->getCurve($keyDetails['ec']['curve_name']),
                'x' => $this->base64UrlEncode($keyDetails['ec']['x']),
                'y' => $this->base64UrlEncode($keyDetails['ec']['y']),
            ];
        }

        throw new AcmeValidationException('Unsupported key type');
    }

    /**
     * 获取算法名称
     */
    private function getAlgorithm($key): string
    {
        $keyDetails = openssl_pkey_get_details($key);

        return match ($keyDetails['type']) {
            OPENSSL_KEYTYPE_RSA => 'RS256',
            OPENSSL_KEYTYPE_EC => 'ES256',
            default => throw new AcmeValidationException('Unsupported key type'),
        };
    }

    /**
     * 签名数据
     */
    private function sign(string $data, $privateKey): string
    {
        $keyDetails = openssl_pkey_get_details($privateKey);

        $algorithm = match ($keyDetails['type']) {
            OPENSSL_KEYTYPE_RSA => OPENSSL_ALGO_SHA256,
            OPENSSL_KEYTYPE_EC => OPENSSL_ALGO_SHA256,
            default => throw new AcmeValidationException('Unsupported key type'),
        };

        openssl_sign($data, $signature, $privateKey, $algorithm);

        return $signature;
    }

    /**
     * Base64 URL 编码
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * 获取椭圆曲线名称
     */
    private function getCurve(string $curveName): string
    {
        return match ($curveName) {
            'prime256v1' => 'P-256',
            'secp384r1' => 'P-384',
            'secp521r1' => 'P-521',
            default => throw new AcmeValidationException("Unsupported curve: {$curveName}"),
        };
    }

    /**
     * 从响应中更新 nonce
     */
    private function updateNonceFromResponse(ResponseInterface $response): void
    {
        $headers = $response->getHeaders();
        if (isset($headers['replay-nonce'][0])) {
            $this->currentNonce = $headers['replay-nonce'][0];
        }
    }

    /**
     * 解析响应
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $content = $response->getContent(false);
            $data = json_decode($content, true) ?? [];

            throw $this->createAcmeException($statusCode, $data);
        }

        return $response->toArray();
    }

    /**
     * 创建相应的 ACME 异常
     */
    private function createAcmeException(int $statusCode, array $errorData): AcmeClientException
    {
        $type = $errorData['type'] ?? 'unknown';
        $detail = $errorData['detail'] ?? 'Unknown error';

        return match ($statusCode) {
            429 => new AcmeRateLimitException($detail, $statusCode, null, $type, $errorData),
            400, 403 => new AcmeValidationException($detail, $statusCode, null, $type, $errorData),
            500, 502, 503 => new AcmeServerException($detail, $statusCode, null, $type, $errorData),
            default => new AcmeClientException($detail, $statusCode, null, $type, $errorData),
        };
    }

    /**
     * 转换 HTTP 异常
     */
    private function convertHttpException(\Throwable $e): AcmeClientException
    {
        if ($e instanceof AcmeClientException) {
            return $e;
        }

        return new AcmeClientException(
            "HTTP request failed: {$e->getMessage()}",
            $e->getCode(),
            $e
        );
    }

    /**
     * 检查是否是 nonce 错误
     */
    private function isBadNonceError(\Throwable $e): bool
    {
        if (!$e instanceof AcmeClientException) {
            return false;
        }

        return $e->getAcmeErrorType() === 'badNonce';
    }

    /**
     * 记录操作日志
     */
    private function logOperation(string $operation, string $message, LogLevel $level, float $startTime, array $context = []): void
    {
        $duration = (int)((microtime(true) - $startTime) * 1000);

        $log = new AcmeOperationLog();
        $log->setOperation($operation);
        $log->setMessage($message);
        $log->setLevel($level);
        $log->setDurationMs($duration);
        $log->setContext($context);
        $log->setSuccess(true);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->logger->log($level->value, $message, $context);
    }

    /**
     * 记录异常日志
     */
    private function logException(\Throwable $exception, string $operation, array $context = []): void
    {
        $log = AcmeExceptionLog::fromException($exception, null, null, array_merge($context, [
            'operation' => $operation,
        ]));

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'operation' => $operation,
            'context' => $context,
        ]);
    }
}
