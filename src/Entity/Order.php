<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 订单实体
 *
 * 存储证书订单信息，包括域名列表、状态、有效期等
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'acme_orders', options: ['comment' => 'ACME 订单表，存储证书订单信息'])]
#[ORM\Index(columns: ['status'], name: 'idx_order_status')]
#[ORM\Index(columns: ['expires_time'], name: 'idx_order_expires')]
class Order implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    #[IndexColumn]
    private ?Account $account = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '订单在 ACME 服务器上的 URL'])]
    private string $orderUrl;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: OrderStatus::class, options: ['comment' => '订单状态'])]
    #[IndexColumn]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '订单过期时间'])]
    #[IndexColumn]
    private ?\DateTimeImmutable $expiresTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '订单错误信息'])]
    private ?string $error = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '终结页面URL'])]
    private ?string $finalizeUrl = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '证书下载URL'])]
    private ?string $certificateUrl = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '订单是否有效'])]
    private bool $valid = false;

    /**
     * 关联的域名标识
     *
     * @var Collection<int, Identifier>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: Identifier::class, cascade: ['persist', 'remove'])]
    private Collection $orderIdentifiers;

    /**
     * 关联的授权
     *
     * @var Collection<int, Authorization>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: Authorization::class, cascade: ['persist', 'remove'])]
    private Collection $authorizations;

    /**
     * 关联的证书
     */
    #[ORM\OneToOne(mappedBy: 'order', targetEntity: Certificate::class, cascade: ['persist', 'remove'])]
    private ?Certificate $certificate = null;

    public function __construct()
    {
        $this->orderIdentifiers = new ArrayCollection();
        $this->authorizations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('Order #%d (%s)', $this->id ?? 0, $this->status->value);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getOrderUrl(): string
    {
        return $this->orderUrl;
    }

    public function setOrderUrl(string $orderUrl): static
    {
        $this->orderUrl = $orderUrl;
        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;
        $this->valid = ($status === OrderStatus::VALID);
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

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = $error;
        return $this;
    }

    public function getFinalizeUrl(): ?string
    {
        return $this->finalizeUrl;
    }

    public function setFinalizeUrl(?string $finalizeUrl): static
    {
        $this->finalizeUrl = $finalizeUrl;
        return $this;
    }

    public function getCertificateUrl(): ?string
    {
        return $this->certificateUrl;
    }

    public function setCertificateUrl(?string $certificateUrl): static
    {
        $this->certificateUrl = $certificateUrl;
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
     * @return Collection<int, Identifier>
     */
    public function getOrderIdentifiers(): Collection
    {
        return $this->orderIdentifiers;
    }

    public function addOrderIdentifier(Identifier $identifier): static
    {
        if (!$this->orderIdentifiers->contains($identifier)) {
            $this->orderIdentifiers->add($identifier);
            $identifier->setOrder($this);
        }

        return $this;
    }

    public function removeOrderIdentifier(Identifier $identifier): static
    {
        if ($this->orderIdentifiers->removeElement($identifier)) {
            if ($identifier->getOrder() === $this) {
                $identifier->setOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Authorization>
     */
    public function getAuthorizations(): Collection
    {
        return $this->authorizations;
    }

    public function addAuthorization(Authorization $authorization): static
    {
        if (!$this->authorizations->contains($authorization)) {
            $this->authorizations->add($authorization);
            $authorization->setOrder($this);
        }

        return $this;
    }

    public function removeAuthorization(Authorization $authorization): static
    {
        if ($this->authorizations->removeElement($authorization)) {
            if ($authorization->getOrder() === $this) {
                $authorization->setOrder(null);
            }
        }

        return $this;
    }

    public function getCertificate(): ?Certificate
    {
        return $this->certificate;
    }

    public function setCertificate(?Certificate $certificate): static
    {
        if ($certificate === null && $this->certificate !== null) {
            $this->certificate->setOrder(null);
        }

        if ($certificate !== null && $certificate->getOrder() !== $this) {
            $certificate->setOrder($this);
        }

        $this->certificate = $certificate;

        return $this;
    }

    /**
     * 检查订单是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expiresTime !== null && $this->expiresTime < new \DateTimeImmutable();
    }

    /**
     * 检查订单是否准备就绪
     */
    public function isReady(): bool
    {
        return $this->status === OrderStatus::READY;
    }

    /**
     * 检查订单是否有效
     */
    public function isInvalid(): bool
    {
        return $this->status === OrderStatus::INVALID;
    }

    /**
     * 检查所有授权是否完成
     */
    public function areAllAuthorizationsValid(): bool
    {
        foreach ($this->authorizations as $authorization) {
            if (!$authorization->isValid()) {
                return false;
            }
        }
        return true;
    }
}
