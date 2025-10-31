<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
class Certificate implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (Doctrine auto-assigns after persist)

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'certificates', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Order $order = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: CertificateStatus::class, options: ['comment' => '证书状态'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [CertificateStatus::class, 'cases'], message: '请选择有效的证书状态')]
    private CertificateStatus $status = CertificateStatus::VALID;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '证书内容（PEM 格式）'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 65535, maxMessage: '证书内容长度不能超过 {{ limit }} 个字符')]
    private string $certificatePem;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '证书链（PEM 格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '证书链长度不能超过 {{ limit }} 个字符')]
    private ?string $certificateChainPem = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '私钥（PEM 格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '私钥长度不能超过 {{ limit }} 个字符')]
    private ?string $privateKeyPem = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '证书序列号'])]
    #[IndexColumn]
    #[Assert\Length(max: 255)]
    private ?string $serialNumber = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '证书指纹（SHA256）'])]
    #[Assert\Length(max: 500)]
    private ?string $fingerprint = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '颁发机构'])]
    #[Assert\Length(max: 500)]
    private ?string $issuer = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书生效时间'])]
    #[IndexColumn]
    #[Assert\Type(type: '\DateTimeImmutable', message: '证书生效时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $notBeforeTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书过期时间'])]
    #[IndexColumn]
    #[Assert\Type(type: '\DateTimeImmutable', message: '证书过期时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $notAfterTime = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '证书包含的域名列表'])]
    #[Assert\NotBlank]
    #[Assert\Type(type: 'array')]
    private array $domains = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '证书是否有效'])]
    #[Assert\Type(type: 'bool', message: '有效状态必须是布尔值')]
    private bool $valid = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书撤销时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '撤销时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $revokedTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书过期时间（兼容旧代码）'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '过期时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $expiresTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '证书签发时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '签发时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $issuedTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '证书数据（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '证书数据长度不能超过 {{ limit }} 个字符')]
    private ?string $certificateData = null;

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

    public function setOrder(?Order $order): void
    {
        $this->order = $order;
    }

    public function getStatus(): CertificateStatus
    {
        return $this->status;
    }

    public function setStatus(CertificateStatus $status): void
    {
        $this->status = $status;
        $this->valid = (CertificateStatus::VALID === $status);
    }

    public function getCertificatePem(): string
    {
        return $this->certificatePem;
    }

    public function setCertificatePem(string $certificatePem): void
    {
        $this->certificatePem = $certificatePem;
    }

    public function getCertificateChainPem(): ?string
    {
        return $this->certificateChainPem;
    }

    public function setCertificateChainPem(?string $certificateChainPem): void
    {
        $this->certificateChainPem = $certificateChainPem;
    }

    public function getPrivateKeyPem(): ?string
    {
        return $this->privateKeyPem;
    }

    public function setPrivateKeyPem(?string $privateKeyPem): void
    {
        $this->privateKeyPem = $privateKeyPem;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): void
    {
        $this->serialNumber = $serialNumber;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    /**
     * @return array<string>
     */
    public function getDomains(): array
    {
        return $this->domains;
    }

    /**
     * @param array<string> $domains
     */
    public function setDomains(array $domains): void
    {
        $this->domains = $domains;
    }

    public function getNotBeforeTime(): ?\DateTimeImmutable
    {
        return $this->notBeforeTime;
    }

    public function setNotBeforeTime(?\DateTimeImmutable $notBeforeTime): void
    {
        $this->notBeforeTime = $notBeforeTime;
    }

    public function getNotAfterTime(): ?\DateTimeImmutable
    {
        return $this->notAfterTime;
    }

    public function setNotAfterTime(?\DateTimeImmutable $notAfterTime): void
    {
        $this->notAfterTime = $notAfterTime;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function setIssuer(?string $issuer): void
    {
        $this->issuer = $issuer;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function isExpired(): bool
    {
        return null !== $this->notAfterTime && $this->notAfterTime < new \DateTime();
    }

    public function isRevoked(): bool
    {
        return CertificateStatus::REVOKED === $this->status;
    }

    public function getRevokedTime(): ?\DateTimeImmutable
    {
        return $this->revokedTime;
    }

    public function setRevokedTime(?\DateTimeImmutable $revokedTime): void
    {
        $this->revokedTime = $revokedTime;
    }

    /**
     * 检查证书是否即将过期（默认30天内）
     */
    public function isExpiringWithin(int $days = 30): bool
    {
        if (null === $this->notAfterTime) {
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
        if (null === $this->notAfterTime) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->notAfterTime);
        $days = $interval->days;

        if (false === $days) {
            return null;
        }

        return 1 === $interval->invert ? -$days : $days;
    }

    /**
     * 获取完整的证书链（证书 + 中间证书）
     */
    public function getFullChainPem(): string
    {
        if (null === $this->certificateChainPem) {
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

    public function getExpiresTime(): ?\DateTimeImmutable
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(?\DateTimeImmutable $expiresTime): void
    {
        $this->expiresTime = $expiresTime;
        // 同步更新 notAfterTime
        if (null !== $expiresTime) {
            $this->notAfterTime = $expiresTime;
        }
    }

    public function getIssuedTime(): ?\DateTimeImmutable
    {
        return $this->issuedTime;
    }

    public function setIssuedTime(?\DateTimeImmutable $issuedTime): void
    {
        $this->issuedTime = $issuedTime;
        // 同步更新 notBeforeTime
        if (null !== $issuedTime) {
            $this->notBeforeTime = $issuedTime;
        }
    }

    public function getCertificateData(): ?string
    {
        return $this->certificateData;
    }

    public function setCertificateData(?string $certificateData): void
    {
        $this->certificateData = $certificateData;
    }
}
