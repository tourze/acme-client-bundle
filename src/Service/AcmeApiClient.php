<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\ACMEClientBundle\Exception\AcmeRateLimitException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;

/**
 * ACME API 客户端
 *
 * 负责与 ACME 服务器进行低级别的 HTTP 通信，处理 JWS 签名、nonce 管理等
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'acme_client')]
class AcmeApiClient
{
    /** @var array<string, mixed>|null */
    private ?array $directory = null;

    private ?string $currentNonce = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $directoryUrl,
        private readonly int $maxRetries = 3,
        private readonly int $retryDelay = 1000, // 毫秒
    ) {
    }

    /**
     * 获取 ACME 目录信息
     *
     * @return array<string, mixed>
     * @throws AbstractAcmeException
     */
    public function getDirectory(): array
    {
        if (null === $this->directory) {
            $startTime = microtime(true);

            try {
                $response = $this->httpClient->request('GET', $this->directoryUrl);
                /** @var array<string, mixed> $directoryData */
                $directoryData = $response->toArray();

                $this->directory = $directoryData;

                $this->logOperation(
                    'directory_fetch',
                    'Successfully fetched ACME directory',
                    LogLevel::INFO,
                    $startTime,
                    ['url' => $this->directoryUrl]
                );
            } catch (\Throwable $e) {
                $this->logException($e, 'directory_fetch', ['url' => $this->directoryUrl]);
                throw new AcmeOperationException("Failed to fetch ACME directory: {$e->getMessage()}", 0, $e);
            }
        }

        if (null === $this->directory) {
            throw new AcmeOperationException('Directory is null after fetch attempt');
        }

        return $this->directory;
    }

    /**
     * 获取新的 nonce
     *
     * @throws AbstractAcmeException
     */
    public function getNonce(): string
    {
        if (null === $this->currentNonce) {
            $directory = $this->getDirectory();
            $newNonceUrl = $directory['newNonce'] ?? null;

            if (!is_string($newNonceUrl) || '' === $newNonceUrl) {
                throw new AcmeOperationException('newNonce URL not found in directory');
            }

            $startTime = microtime(true);

            try {
                $response = $this->httpClient->request('HEAD', $newNonceUrl);
                $headers = $response->getHeaders();
                $nonce = $headers['replay-nonce'][0] ?? null;

                if (!is_string($nonce) || '' === $nonce) {
                    throw new AcmeOperationException('No nonce received from server');
                }

                $this->currentNonce = $nonce;

                $this->logOperation(
                    'nonce_fetch',
                    'Successfully fetched new nonce',
                    LogLevel::DEBUG,
                    $startTime,
                    ['url' => $newNonceUrl]
                );
            } catch (\Throwable $e) {
                $this->logException($e, 'nonce_fetch', ['url' => $newNonceUrl]);
                throw new AcmeOperationException("Failed to fetch nonce: {$e->getMessage()}", 0, $e);
            }
        }

        if (null === $this->currentNonce) {
            throw new AcmeOperationException('Nonce is null after fetch attempt');
        }

        return $this->currentNonce;
    }

    /**
     * 发起 ACME POST 请求
     *
     * @param array<string, mixed> $payload
     * @param \OpenSSLAsymmetricKey|mixed $accountKey
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $payload, $accountKey, ?string $kid = null): array
    {
        $directory = $this->getDirectory();
        $url = $directory[$endpoint] ?? $endpoint;

        if (!is_string($url)) {
            throw new AcmeValidationException("Invalid URL from directory: endpoint {$endpoint}");
        }

        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new AcmeValidationException("Invalid URL: {$url}");
        }

        if (!$accountKey instanceof \OpenSSLAsymmetricKey) {
            throw new AcmeValidationException('Invalid account key provided');
        }

        return $this->makeSignedRequest('POST', $url, $payload, $accountKey, $kid);
    }

    /**
     * 创建 ACME 账户
     *
     * @param array<string> $contacts
     * @return array<string, mixed>
     */
    public function createAccount(array $contacts, string $privateKeyPem): array
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (false === $privateKey) {
            throw new AcmeValidationException('Invalid private key provided');
        }

        $directory = $this->getDirectory();
        $newAccountUrl = $directory['newAccount'] ?? null;

        if (!is_string($newAccountUrl)) {
            throw new AcmeValidationException('newAccount URL not found in directory');
        }

        $payload = [
            'contact' => $contacts,
            'termsOfServiceAgreed' => true,
        ];

        return $this->post($newAccountUrl, $payload, $privateKey);
    }

    /**
     * 发起 ACME GET 请求
     *
     * @return array<string, mixed>
     */
    public function get(string $url): array
    {
        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request('GET', $url);
            /** @var array<string, mixed> $data */
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
     * @param array<string, mixed> $payload
     * @param \OpenSSLAsymmetricKey $accountKey
     * @return array<string, mixed>
     */
    private function makeSignedRequest(string $method, string $url, array $payload, \OpenSSLAsymmetricKey $accountKey, ?string $kid = null): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            ++$attempt;

            try {
                return $this->attemptSignedRequest($method, $url, $payload, $accountKey, $kid, $attempt);
            } catch (\Throwable $e) {
                $lastException = $e;

                if (!$this->shouldRetry($e, $attempt)) {
                    break;
                }

                $this->handleRetryableError($e);
            }
        }

        if (null !== $lastException) {
            $this->logFailedRequest($url, $method, $attempt, $lastException);
            throw $lastException;
        }

        throw new AcmeOperationException('All retry attempts failed');
    }

    /**
     * @param array<string, mixed> $payload
     * @param \OpenSSLAsymmetricKey $accountKey
     * @return array<string, mixed>
     */
    private function attemptSignedRequest(string $method, string $url, array $payload, \OpenSSLAsymmetricKey $accountKey, ?string $kid, int $attempt): array
    {
        $startTime = microtime(true);
        $nonce = $this->getNonce();
        $jws = $this->createJws($payload, $url, $accountKey, $nonce, $kid);

        $response = $this->httpClient->request($method, $url, [
            'headers' => ['Content-Type' => 'application/jose+json'],
            'body' => $jws,
        ]);

        $this->updateNonceFromResponse($response);
        $responseData = $this->parseResponse($response);

        $this->logOperation(
            'acme_request',
            "{$method} request to {$url}",
            LogLevel::INFO,
            $startTime,
            [
                'url' => $url,
                'method' => $method,
                'status' => $response->getStatusCode(),
                'attempt' => $attempt,
            ]
        );

        return $responseData;
    }

    private function shouldRetry(\Throwable $e, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        return $e instanceof AcmeRateLimitException || $this->isBadNonceError($e);
    }

    private function handleRetryableError(\Throwable $e): void
    {
        if ($e instanceof AcmeRateLimitException) {
            usleep($this->retryDelay * 1000);
        } elseif ($this->isBadNonceError($e)) {
            $this->currentNonce = null;
        }
    }

    private function logFailedRequest(string $url, string $method, int $attempts, \Throwable $exception): void
    {
        $this->logException($exception, 'acme_request', [
            'url' => $url,
            'method' => $method,
            'attempts' => $attempts,
        ]);
    }

    /**
     * 创建 JWS 签名
     * @param array<string, mixed> $payload
     * @param \OpenSSLAsymmetricKey $accountKey
     */
    private function createJws(array $payload, string $url, \OpenSSLAsymmetricKey $accountKey, string $nonce, ?string $kid = null): string
    {
        // 构建 protected header
        $protected = [
            'alg' => $this->getAlgorithm($accountKey),
            'nonce' => $nonce,
            'url' => $url,
        ];

        if (null !== $kid) {
            $protected['kid'] = $kid;
        } else {
            $protected['jwk'] = $this->createJwk($accountKey);
        }

        $protectedJson = json_encode($protected);
        $payloadJson = json_encode($payload);

        if (false === $protectedJson || false === $payloadJson) {
            throw new AcmeOperationException('Failed to encode JWS components to JSON');
        }

        $protectedEncoded = $this->base64UrlEncode($protectedJson);
        $payloadEncoded = $this->base64UrlEncode($payloadJson);

        // 创建签名
        $signingInput = "{$protectedEncoded}.{$payloadEncoded}";
        $signature = $this->sign($signingInput, $accountKey);
        $signatureEncoded = $this->base64UrlEncode($signature);

        $jwsJson = json_encode([
            'protected' => $protectedEncoded,
            'payload' => $payloadEncoded,
            'signature' => $signatureEncoded,
        ]);

        if (false === $jwsJson) {
            throw new AcmeOperationException('Failed to encode JWS to JSON');
        }

        return $jwsJson;
    }

    /**
     * 创建 JWK (JSON Web Key)
     * @param \OpenSSLAsymmetricKey $publicKey
     * @return array<string, mixed>
     */
    private function createJwk(\OpenSSLAsymmetricKey $publicKey): array
    {
        $keyDetails = openssl_pkey_get_details($publicKey);

        if (false === $keyDetails) {
            throw new AcmeValidationException('Failed to get key details');
        }

        if (OPENSSL_KEYTYPE_RSA === $keyDetails['type']) {
            if (!isset($keyDetails['rsa']) || !is_array($keyDetails['rsa'])) {
                throw new AcmeValidationException('Missing RSA key details');
            }

            $rsaDetails = $keyDetails['rsa'];
            if (!isset($rsaDetails['n'], $rsaDetails['e']) || !is_string($rsaDetails['n']) || !is_string($rsaDetails['e'])) {
                throw new AcmeValidationException('Invalid RSA key details');
            }

            return [
                'kty' => 'RSA',
                'n' => $this->base64UrlEncode($rsaDetails['n']),
                'e' => $this->base64UrlEncode($rsaDetails['e']),
            ];
        }

        if (OPENSSL_KEYTYPE_EC === $keyDetails['type']) {
            if (!isset($keyDetails['ec']) || !is_array($keyDetails['ec'])) {
                throw new AcmeValidationException('Missing EC key details');
            }

            $ecDetails = $keyDetails['ec'];
            if (!isset($ecDetails['curve_name'], $ecDetails['x'], $ecDetails['y']) || !is_string($ecDetails['curve_name']) || !is_string($ecDetails['x']) || !is_string($ecDetails['y'])) {
                throw new AcmeValidationException('Invalid EC key details');
            }

            return [
                'kty' => 'EC',
                'crv' => $this->getCurve($ecDetails['curve_name']),
                'x' => $this->base64UrlEncode($ecDetails['x']),
                'y' => $this->base64UrlEncode($ecDetails['y']),
            ];
        }

        throw new AcmeValidationException('Unsupported key type');
    }

    /**
     * 获取算法名称
     * @param \OpenSSLAsymmetricKey $key
     */
    private function getAlgorithm(\OpenSSLAsymmetricKey $key): string
    {
        $keyDetails = openssl_pkey_get_details($key);

        if (false === $keyDetails) {
            throw new AcmeValidationException('Failed to get key details for algorithm detection');
        }

        return match ($keyDetails['type']) {
            OPENSSL_KEYTYPE_RSA => 'RS256',
            OPENSSL_KEYTYPE_EC => 'ES256',
            default => throw new AcmeValidationException('Unsupported key type'),
        };
    }

    /**
     * 签名数据
     * @param \OpenSSLAsymmetricKey $privateKey
     */
    private function sign(string $data, \OpenSSLAsymmetricKey $privateKey): string
    {
        $keyDetails = openssl_pkey_get_details($privateKey);

        if (false === $keyDetails) {
            throw new AcmeValidationException('Failed to get private key details for signing');
        }

        $algorithm = match ($keyDetails['type']) {
            OPENSSL_KEYTYPE_RSA => OPENSSL_ALGO_SHA256,
            OPENSSL_KEYTYPE_EC => OPENSSL_ALGO_SHA256,
            default => throw new AcmeValidationException('Unsupported key type'),
        };

        $signature = '';
        $signResult = openssl_sign($data, $signature, $privateKey, $algorithm);

        if (false === $signResult || !is_string($signature)) {
            throw new AcmeValidationException('Failed to sign data');
        }

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
     * @return array<string, mixed>
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $content = $response->getContent(false);
            $decoded = json_decode($content, true);
            /** @var array<string, mixed> $data */
            $data = is_array($decoded) ? $decoded : [];

            throw $this->createAcmeException($statusCode, $data);
        }

        /** @var array<string, mixed> */
        return $response->toArray();
    }

    /**
     * 创建相应的 ACME 异常
     * @param array<string, mixed> $errorData
     */
    private function createAcmeException(int $statusCode, array $errorData): AbstractAcmeException
    {
        $type = $errorData['type'] ?? 'unknown';
        $detail = $errorData['detail'] ?? 'Unknown error';

        $typeString = is_string($type) ? $type : 'unknown';
        $detailString = is_string($detail) ? $detail : 'Unknown error';

        return match ($statusCode) {
            429 => new AcmeRateLimitException($detailString, $statusCode, null, $typeString, $errorData),
            400, 403 => new AcmeValidationException($detailString, $statusCode, null, $typeString, $errorData),
            500, 502, 503 => new AcmeServerException($detailString, $statusCode, null, $typeString, $errorData),
            default => new AcmeOperationException($detailString, $statusCode, null, $typeString, $errorData),
        };
    }

    /**
     * 转换 HTTP 异常
     */
    private function convertHttpException(\Throwable $e): AbstractAcmeException
    {
        if ($e instanceof AbstractAcmeException) {
            return $e;
        }

        return new AcmeOperationException(
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
        if (!$e instanceof AbstractAcmeException) {
            return false;
        }

        return 'badNonce' === $e->getAcmeErrorType();
    }

    /**
     * 记录操作日志
     * @param array<string, mixed> $context
     */
    private function logOperation(string $operation, string $message, LogLevel $level, float $startTime, array $context = []): void
    {
        $duration = (int) ((microtime(true) - $startTime) * 1000);

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
     * @param array<string, mixed> $context
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
