<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Repository\ChallengeRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 质询实体
 *
 * 存储质询信息，只实现 DNS-01 类型
 */
#[ORM\Entity(repositoryClass: ChallengeRepository::class)]
#[ORM\Table(name: 'acme_challenges')]
#[ORM\Index(columns: ['type'], name: 'idx_challenge_type')]
#[ORM\Index(columns: ['status'], name: 'idx_challenge_status')]
#[ORM\Index(columns: ['token'], name: 'idx_challenge_token')]
class Challenge implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Authorization::class, inversedBy: 'challenges')]
    #[ORM\JoinColumn(nullable: false)]
    #[IndexColumn]
    private ?Authorization $authorization = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '质询在 ACME 服务器上的 URL'])]
    #[IndexColumn]
    private string $challengeUrl;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ChallengeType::class, options: ['comment' => '质询类型'])]
    #[IndexColumn]
    private ChallengeType $type = ChallengeType::DNS_01;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ChallengeStatus::class, options: ['comment' => '质询状态'])]
    #[IndexColumn]
    private ChallengeStatus $status = ChallengeStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '质询 token'])]
    private string $token;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '质询响应内容'])]
    private string $keyAuthorization;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'DNS 记录名称'])]
    private ?string $dnsRecordName = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => 'DNS 记录值'])]
    private ?string $dnsRecordValue = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '质询验证时间'])]
    private ?\DateTimeInterface $validatedTime = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '质询错误信息'])]
    private ?array $error = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '质询是否有效'])]
    private bool $valid = false;

    public function __toString(): string
    {
        return sprintf('Challenge #%d (%s)', $this->id ?? 0, $this->type->value);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthorization(): ?Authorization
    {
        return $this->authorization;
    }

    public function setAuthorization(?Authorization $authorization): static
    {
        $this->authorization = $authorization;
        return $this;
    }

    public function getChallengeUrl(): string
    {
        return $this->challengeUrl;
    }

    public function setChallengeUrl(string $challengeUrl): static
    {
        $this->challengeUrl = $challengeUrl;
        return $this;
    }

    public function getType(): ChallengeType
    {
        return $this->type;
    }

    public function setType(ChallengeType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): ChallengeStatus
    {
        return $this->status;
    }

    public function setStatus(ChallengeStatus $status): static
    {
        $this->status = $status;
        $this->valid = ($status === ChallengeStatus::VALID);
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getKeyAuthorization(): string
    {
        return $this->keyAuthorization;
    }

    public function setKeyAuthorization(string $keyAuthorization): static
    {
        $this->keyAuthorization = $keyAuthorization;
        return $this;
    }

    public function getDnsRecordName(): ?string
    {
        return $this->dnsRecordName;
    }

    public function setDnsRecordName(?string $dnsRecordName): static
    {
        $this->dnsRecordName = $dnsRecordName;
        return $this;
    }

    public function getDnsRecordValue(): ?string
    {
        return $this->dnsRecordValue;
    }

    public function setDnsRecordValue(?string $dnsRecordValue): static
    {
        $this->dnsRecordValue = $dnsRecordValue;
        return $this;
    }

    public function getValidatedTime(): ?\DateTimeInterface
    {
        return $this->validatedTime;
    }

    public function setValidatedTime(?\DateTimeInterface $validatedTime): static
    {
        $this->validatedTime = $validatedTime;
        return $this;
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    public function setError(?array $error): static
    {
        $this->error = $error;
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

    /**
     * 检查质询是否失败
     */
    public function isInvalid(): bool
    {
        return $this->status === ChallengeStatus::INVALID;
    }

    /**
     * 检查质询是否正在处理
     */
    public function isProcessing(): bool
    {
        return $this->status === ChallengeStatus::PROCESSING;
    }

    /**
     * 检查是否为 DNS-01 质询
     */
    public function isDns01(): bool
    {
        return $this->type === ChallengeType::DNS_01;
    }

    /**
     * 获取 DNS TXT 记录的完整名称
     * 例如：_acme-challenge.example.com
     */
    public function getFullDnsRecordName(): string
    {
        if ($this->dnsRecordName === null) {
            return '';
        }
        return $this->dnsRecordName;
    }

    /**
     * 计算 DNS 记录值（SHA256 + Base64URL）
     */
    public function calculateDnsRecordValue(): string
    {
        if (!isset($this->keyAuthorization)) {
            return '';
        }

        $hash = hash('sha256', $this->keyAuthorization, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }
}
