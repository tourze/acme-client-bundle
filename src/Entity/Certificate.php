<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Repository\CertificateRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 证书实体
 *
 * 存储签发的证书信息，包括证书内容、私钥、证书链等
 */
#[ORM\Entity(repositoryClass: CertificateRepository::class)]
#[ORM\Table(name: 'acme_certificates', options: ['comment' => 'ACME 证书表，存储签发的证书信息'])]
#[ORM\Index(columns: ['not_before_time'], name: 'idx_certificate_not_before')]
#[ORM\Index(columns: ['not_after_time'], name: 'idx_certificate_not_after')]
#[ORM\Index(columns: ['serial_number'], name: 'idx_certificate_serial')]
class Certificate implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Order::class, inversedBy: 'certificate')]
    #[ORM\JoinColumn(nullable: false)]
    #[IndexColumn]
    private ?Order $order = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: CertificateStatus::class, options: ['comment' => '证书状态'])]
    #[IndexColumn]
    private CertificateStatus $status = CertificateStatus::VALID;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '证书内容（PEM 格式）'])]
    private string $certificatePem;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '证书链（PEM 格式）'])]
    private ?string $certificateChainPem = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '私钥（PEM 格式）'])]
    private ?string $privateKeyPem = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '证书序列号'])]
    #[IndexColumn]
    private ?string $serialNumber = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '证书指纹（SHA256）'])]
    private ?string $fingerprint = null;


    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '颁发机构'])]
    private ?string $issuer = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书生效时间'])]
    private ?\DateTimeImmutable $notBeforeTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书过期时间'])]
    #[IndexColumn]
    private ?\DateTimeImmutable $notAfterTime = null;

    #[ORM\Column(type: Types::JSON, options: ['comment' => '证书包含的域名列表'])]
    private array $domains = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '证书是否有效'])]
    private bool $valid = true;


    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书撤销时间'])]
    private ?\DateTimeImmutable $revokedTime = null;

    public function __toString(): string
    {
        return sprintf('Certificate #%d (%s)', $this->id ?? 0, $this->serialNumber ?? 'Unknown');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getStatus(): CertificateStatus
    {
        return $this->status;
    }

    public function setStatus(CertificateStatus $status): static
    {
        $this->status = $status;
        $this->valid = ($status === CertificateStatus::VALID);
        return $this;
    }

    public function getCertificatePem(): string
    {
        return $this->certificatePem;
    }

    public function setCertificatePem(string $certificatePem): static
    {
        $this->certificatePem = $certificatePem;
        return $this;
    }

    public function getCertificateChainPem(): ?string
    {
        return $this->certificateChainPem;
    }

    public function setCertificateChainPem(?string $certificateChainPem): static
    {
        $this->certificateChainPem = $certificateChainPem;
        return $this;
    }

    public function getPrivateKeyPem(): ?string
    {
        return $this->privateKeyPem;
    }

    public function setPrivateKeyPem(?string $privateKeyPem): static
    {
        $this->privateKeyPem = $privateKeyPem;
        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): static
    {
        $this->serialNumber = $serialNumber;
        return $this;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): static
    {
        $this->fingerprint = $fingerprint;
        return $this;
    }

    public function getDomains(): array
    {
        return $this->domains;
    }

    public function setDomains(array $domains): static
    {
        $this->domains = $domains;
        return $this;
    }

    public function getNotBeforeTime(): ?\DateTimeImmutable
    {
        return $this->notBeforeTime;
    }

    public function setNotBeforeTime(?\DateTimeImmutable $notBeforeTime): static
    {
        $this->notBeforeTime = $notBeforeTime;
        return $this;
    }

    public function getNotAfterTime(): ?\DateTimeImmutable
    {
        return $this->notAfterTime;
    }

    public function setNotAfterTime(?\DateTimeImmutable $notAfterTime): static
    {
        $this->notAfterTime = $notAfterTime;
        return $this;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function setIssuer(?string $issuer): static
    {
        $this->issuer = $issuer;
        return $this;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): static
    {
        $this->valid = $valid;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->notAfterTime !== null && $this->notAfterTime < new \DateTime();
    }

    public function isRevoked(): bool
    {
        return $this->status === CertificateStatus::REVOKED;
    }

    public function getRevokedTime(): ?\DateTimeImmutable
    {
        return $this->revokedTime;
    }

    public function setRevokedTime(?\DateTimeImmutable $revokedTime): static
    {
        $this->revokedTime = $revokedTime;
        return $this;
    }

    /**
     * 检查证书是否即将过期（默认30天内）
     */
    public function isExpiringWithin(int $days = 30): bool
    {
        if ($this->notAfterTime === null) {
            return false;
        }

        $threshold = new \DateTimeImmutable("+{$days} days");
        return $this->notAfterTime <= $threshold;
    }

    /**
     * 获取证书剩余有效天数
     */
    public function getDaysUntilExpiry(): ?int
    {
        if ($this->notAfterTime === null) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->notAfterTime);

        return $interval->invert === 1 ? -$interval->days : $interval->days;
    }

    /**
     * 获取完整的证书链（证书 + 中间证书）
     */
    public function getFullChainPem(): string
    {
        if ($this->certificateChainPem === null) {
            return $this->certificatePem;
        }

        return $this->certificatePem . "\n" . $this->certificateChainPem;
    }

    /**
     * 检查域名是否在证书中
     */
    public function containsDomain(string $domain): bool
    {
        return in_array($domain, $this->domains, true);
    }
}
