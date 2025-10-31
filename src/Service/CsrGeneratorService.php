<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Tourze\ACMEClientBundle\Exception\AcmeOperationException;

/**
 * CSR生成服务
 *
 * 负责生成证书签名请求(Certificate Signing Request)
 */
readonly class CsrGeneratorService
{
    /**
     * 生成证书签名请求(CSR)
     *
     * @param array<string> $domains
     * @param array<string, string> $extraInfo
     */
    public function generateCsr(array $domains, string $privateKeyPem, array $extraInfo = []): string
    {
        $dn = $this->buildDistinguishedName($domains, $extraInfo);
        $privateKey = $this->validatePrivateKey($privateKeyPem);
        $csr = $this->createCsr($dn, $privateKey, $domains);

        return $this->exportCsr($csr);
    }

    /**
     * 构建证书的专有名称(DN)
     *
     * @param array<string> $domains
     * @param array<string, string> $extraInfo
     * @return array<string, string>
     */
    private function buildDistinguishedName(array $domains, array $extraInfo): array
    {
        return array_merge(['CN' => $domains[0]], $extraInfo);
    }

    /**
     * @return \OpenSSLAsymmetricKey
     */
    private function validatePrivateKey(string $privateKeyPem)
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (false === $privateKey) {
            $error = false !== openssl_error_string() ? openssl_error_string() : 'Unknown OpenSSL error';
            throw new AcmeOperationException('Invalid private key for CSR generation: ' . $error);
        }

        return $privateKey;
    }

    /**
     * @param array<string, string> $dn
     * @param \OpenSSLAsymmetricKey $privateKey
     * @return \OpenSSLCertificateSigningRequest|false
     */
    private function generateSimpleCsr(array $dn, $privateKey)
    {
        $config = ['digest_alg' => 'sha256'];

        $result = openssl_csr_new($dn, $privateKey, $config);

        return true === $result ? false : $result;
    }

    /**
     * 创建CSR（单域名或多域名）
     *
     * @param array<string, string> $dn
     * @param \OpenSSLAsymmetricKey $privateKey
     * @param array<string> $domains
     * @return \OpenSSLCertificateSigningRequest|false
     */
    private function createCsr(array $dn, $privateKey, array $domains)
    {
        if (1 === count($domains)) {
            return $this->generateSimpleCsr($dn, $privateKey);
        }

        return $this->generateMultiDomainCsr($dn, $privateKey, $domains);
    }

    /**
     * @param array<string, string> $dn
     * @param \OpenSSLAsymmetricKey $privateKey
     * @param array<string> $domains
     * @return \OpenSSLCertificateSigningRequest|false
     */
    private function generateMultiDomainCsr(array $dn, $privateKey, array $domains)
    {
        $config = $this->createMultiDomainConfig($domains);
        $csr = openssl_csr_new($dn, $privateKey, $config);
        $configFile = $config['config'] ?? null;
        if (is_string($configFile)) {
            $this->cleanupTempFile($configFile);
        }

        return true === $csr ? false : $csr;
    }

    /**
     * 创建多域名配置
     *
     * @param array<string> $domains
     * @return array<string, mixed>
     */
    private function createMultiDomainConfig(array $domains): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'req_extensions' => 'v3_req',
        ];

        $sanString = 'DNS:' . implode(',DNS:', $domains);
        $configText = "[req]\ndistinguished_name = req_distinguished_name\nreq_extensions = v3_req\n\n[req_distinguished_name]\n\n[v3_req]\nsubjectAltName = {$sanString}\n";

        $configFile = tempnam(sys_get_temp_dir(), 'openssl_');
        file_put_contents($configFile, $configText);
        $config['config'] = $configFile;

        return $config;
    }

    /**
     * @param \OpenSSLCertificateSigningRequest|false $csr
     */
    private function exportCsr($csr): string
    {
        if (false === $csr) {
            $error = false !== openssl_error_string() ? openssl_error_string() : 'Unknown OpenSSL error';
            throw new AcmeOperationException('Failed to generate CSR: ' . $error);
        }

        $csrPem = '';
        if (!openssl_csr_export($csr, $csrPem) || !is_string($csrPem)) {
            $error = false !== openssl_error_string() ? openssl_error_string() : 'Unknown OpenSSL error';
            throw new AcmeOperationException('Failed to export CSR: ' . $error);
        }

        return $csrPem;
    }

    private function cleanupTempFile(?string $configFile): void
    {
        if (null !== $configFile && file_exists($configFile)) {
            unlink($configFile);
        }
    }
}
