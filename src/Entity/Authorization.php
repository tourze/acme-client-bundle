<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 授权实体
 *
 * 存储域名授权信息，包括状态、过期时间、关联的质询等
 */
#[ORM\Entity(repositoryClass: AuthorizationRepository::class)]
#[ORM\Table(name: 'acme_authorizations', options: ['comment' => 'ACME 授权表，存储域名授权信息'])]
class Authorization implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    /**
     * 关联的订单
     */
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderAuthorizations', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Order $order = null;

    /**
     * 关联的标识符
     */
    #[ORM\ManyToOne(targetEntity: Identifier::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Identifier $identifier = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '授权在 ACME 服务器上的 URL'])]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private string $authorizationUrl;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AuthorizationStatus::class, options: ['comment' => '授权状态'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [AuthorizationStatus::class, 'cases'], message: '请选择有效的授权状态')]
    private AuthorizationStatus $status = AuthorizationStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '授权过期时间'])]
    #[IndexColumn]
    #[Assert\Type(type: '\DateTimeImmutable', message: '过期时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $expiresTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '授权是否为通配符'])]
    #[Assert\Type(type: 'bool', message: '通配符状态必须是布尔值')]
    private bool $wildcard = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '授权是否有效'])]
    #[Assert\Type(type: 'bool', message: '有效状态必须是布尔值')]
    private bool $valid = false;

    /**
     * 关联的质询
     *
     * @var Collection<int, Challenge>
     */
    #[ORM\OneToMany(mappedBy: 'authorization', targetEntity: Challenge::class, cascade: ['persist', 'remove'])]
    private Collection $challenges;

    public function __construct()
    {
        $this->challenges = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('Authorization #%d (%s)', $this->id ?? 0, $this->identifier?->getValue() ?? '');
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

    public function getIdentifier(): ?Identifier
    {
        return $this->identifier;
    }

    public function setIdentifier(?Identifier $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function setAuthorizationUrl(string $authorizationUrl): void
    {
        $this->authorizationUrl = $authorizationUrl;
    }

    public function getStatus(): AuthorizationStatus
    {
        return $this->status;
    }

    public function setStatus(AuthorizationStatus $status): void
    {
        $this->status = $status;
        $this->valid = (AuthorizationStatus::VALID === $status);
    }

    public function getExpiresTime(): ?\DateTimeImmutable
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(?\DateTimeImmutable $expiresTime): void
    {
        $this->expiresTime = $expiresTime;
    }

    public function isWildcard(): bool
    {
        return $this->wildcard;
    }

    public function setWildcard(bool $wildcard): void
    {
        $this->wildcard = $wildcard;
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
     * @return Collection<int, Challenge>
     */
    public function getChallenges(): Collection
    {
        return $this->challenges;
    }

    public function addChallenge(Challenge $challenge): self
    {
        if (!$this->challenges->contains($challenge)) {
            $this->challenges->add($challenge);
            $challenge->setAuthorization($this);
        }

        return $this;
    }

    public function removeChallenge(Challenge $challenge): self
    {
        if ($this->challenges->removeElement($challenge)) {
            if ($challenge->getAuthorization() === $this) {
                $challenge->setAuthorization(null);
            }
        }

        return $this;
    }

    public function isPending(): bool
    {
        return AuthorizationStatus::PENDING === $this->status;
    }

    public function isExpired(): bool
    {
        return AuthorizationStatus::EXPIRED === $this->status
            || (null !== $this->expiresTime && $this->expiresTime < new \DateTimeImmutable());
    }

    public function isRevoked(): bool
    {
        return AuthorizationStatus::REVOKED === $this->status;
    }

    public function isInvalid(): bool
    {
        return AuthorizationStatus::INVALID === $this->status;
    }

    /**
     * 获取标识符值
     */
    public function getIdentifierValue(): ?string
    {
        return $this->identifier?->getValue();
    }
}
