<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
#[ORM\Table(name: 'acme_challenges', options: ['comment' => 'ACME 质询表，存储质询信息'])]
class Challenge implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Authorization::class, inversedBy: 'challenges')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Authorization $authorization = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '质询在 ACME 服务器上的 URL'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private string $challengeUrl;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ChallengeType::class, options: ['comment' => '质询类型'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ChallengeType::class, 'cases'], message: '请选择有效的质询类型')]
    private ChallengeType $type = ChallengeType::DNS_01;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ChallengeStatus::class, options: ['comment' => '质询状态'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ChallengeStatus::class, 'cases'], message: '请选择有效的质询状态')]
    private ChallengeStatus $status = ChallengeStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '质询 token'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $token;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '质询响应内容'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $keyAuthorization;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'DNS 记录名称'])]
    #[Assert\Length(max: 255)]
    private ?string $dnsRecordName = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => 'DNS 记录值'])]
    #[Assert\Length(max: 500)]
    private ?string $dnsRecordValue = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '质询验证时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '验证时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $validatedTime = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '质询错误信息'])]
    #[Assert\Type(type: 'array', message: '错误信息必须是数组格式')]
    private ?array $error = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '质询是否有效'])]
    #[Assert\Type(type: 'bool', message: '有效状态必须是布尔值')]
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

    public function setAuthorization(?Authorization $authorization): void
    {
        $this->authorization = $authorization;
    }

    public function getChallengeUrl(): string
    {
        return $this->challengeUrl;
    }

    public function setChallengeUrl(string $challengeUrl): void
    {
        $this->challengeUrl = $challengeUrl;
    }

    public function getType(): ChallengeType
    {
        return $this->type;
    }

    public function setType(ChallengeType $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): ChallengeStatus
    {
        return $this->status;
    }

    public function setStatus(ChallengeStatus $status): void
    {
        $this->status = $status;
        $this->valid = (ChallengeStatus::VALID === $status);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getKeyAuthorization(): string
    {
        return $this->keyAuthorization;
    }

    public function setKeyAuthorization(string $keyAuthorization): void
    {
        $this->keyAuthorization = $keyAuthorization;
    }

    public function getDnsRecordName(): ?string
    {
        return $this->dnsRecordName;
    }

    public function setDnsRecordName(?string $dnsRecordName): void
    {
        $this->dnsRecordName = $dnsRecordName;
    }

    public function getDnsRecordValue(): ?string
    {
        return $this->dnsRecordValue;
    }

    public function setDnsRecordValue(?string $dnsRecordValue): void
    {
        $this->dnsRecordValue = $dnsRecordValue;
    }

    public function getValidatedTime(): ?\DateTimeImmutable
    {
        return $this->validatedTime;
    }

    public function setValidatedTime(?\DateTimeImmutable $validatedTime): void
    {
        $this->validatedTime = $validatedTime;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getError(): ?array
    {
        return $this->error;
    }

    /**
     * @param array<string, mixed>|null $error
     */
    public function setError(?array $error): void
    {
        $this->error = $error;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    /**
     * 检查质询是否失败
     */
    public function isInvalid(): bool
    {
        return ChallengeStatus::INVALID === $this->status;
    }

    /**
     * 检查质询是否正在处理
     */
    public function isProcessing(): bool
    {
        return ChallengeStatus::PROCESSING === $this->status;
    }

    /**
     * 检查是否为 DNS-01 质询
     */
    public function isDns01(): bool
    {
        return match ($this->type) {
            ChallengeType::DNS_01 => true,
        };
    }

    /**
     * 获取 DNS TXT 记录的完整名称
     * 例如：_acme-challenge.example.com
     */
    public function getFullDnsRecordName(): string
    {
        if (null === $this->dnsRecordName) {
            return '';
        }

        return $this->dnsRecordName;
    }

    /**
     * setChallengeUrl的别名，用于保持向后兼容性
     */
    public function setUrl(string $url): void
    {
        $this->setChallengeUrl($url);
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
