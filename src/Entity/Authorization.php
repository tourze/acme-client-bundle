<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
#[ORM\Index(columns: ['status'], name: 'idx_authorization_status')]
#[ORM\Index(columns: ['expires_time'], name: 'idx_authorization_expires')]
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
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderAuthorizations')]
    #[ORM\JoinColumn(nullable: false)]
    #[IndexColumn]
    private ?Order $order = null;

    /**
     * 关联的标识符
     */
    #[ORM\ManyToOne(targetEntity: Identifier::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[IndexColumn]
    private ?Identifier $identifier = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '授权在 ACME 服务器上的 URL'])]
    private string $authorizationUrl;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AuthorizationStatus::class, options: ['comment' => '授权状态'])]
    #[IndexColumn]
    private AuthorizationStatus $status = AuthorizationStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '授权过期时间'])]
    #[IndexColumn]
    private ?\DateTimeImmutable $expiresTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '授权是否为通配符'])]
    private bool $wildcard = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '授权是否有效'])]
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

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getIdentifier(): ?Identifier
    {
        return $this->identifier;
    }

    public function setIdentifier(?Identifier $identifier): static
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function setAuthorizationUrl(string $authorizationUrl): static
    {
        $this->authorizationUrl = $authorizationUrl;
        return $this;
    }

    public function getStatus(): AuthorizationStatus
    {
        return $this->status;
    }

    public function setStatus(AuthorizationStatus $status): static
    {
        $this->status = $status;
        $this->valid = ($status === AuthorizationStatus::VALID);
        return $this;
    }

    public function getExpiresTime(): ?\DateTimeImmutable
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(?\DateTimeImmutable $expiresTime): static
    {
        $this->expiresTime = $expiresTime;
        return $this;
    }

    public function isWildcard(): bool
    {
        return $this->wildcard;
    }

    public function setWildcard(bool $wildcard): static
    {
        $this->wildcard = $wildcard;
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
     * @return Collection<int, Challenge>
     */
    public function getChallenges(): Collection
    {
        return $this->challenges;
    }

    public function addChallenge(Challenge $challenge): static
    {
        if (!$this->challenges->contains($challenge)) {
            $this->challenges->add($challenge);
            $challenge->setAuthorization($this);
        }

        return $this;
    }

    public function removeChallenge(Challenge $challenge): static
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
        return $this->status === AuthorizationStatus::PENDING;
    }

    public function isExpired(): bool
    {
        return $this->status === AuthorizationStatus::EXPIRED ||
            ($this->expiresTime !== null && $this->expiresTime < new \DateTimeImmutable());
    }

    public function isRevoked(): bool
    {
        return $this->status === AuthorizationStatus::REVOKED;
    }

    public function isInvalid(): bool
    {
        return $this->status === AuthorizationStatus::INVALID;
    }

    /**
     * 获取标识符值
     */
    public function getIdentifierValue(): ?string
    {
        return $this->identifier?->getValue();
    }
}
