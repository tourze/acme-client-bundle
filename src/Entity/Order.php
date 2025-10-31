<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
class Order implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (Doctrine auto-assigns after persist)

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'orders', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Account $account = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '订单在 ACME 服务器上的 URL'])]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private string $orderUrl;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: OrderStatus::class, options: ['comment' => '订单状态'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [OrderStatus::class, 'cases'], message: '请选择有效的订单状态')]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '订单过期时间'])]
    #[IndexColumn]
    #[Assert\Type(type: '\DateTimeImmutable', message: '过期时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $expiresTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '订单错误信息'])]
    #[Assert\Length(max: 65535, maxMessage: '错误信息长度不能超过 {{ limit }} 个字符')]
    private ?string $error = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '终结页面URL'])]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private ?string $finalizeUrl = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '证书下载URL'])]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private ?string $certificateUrl = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '订单是否有效'])]
    #[Assert\Type(type: 'bool', message: '有效状态必须是布尔值')]
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
     * 关联的证书集合
     *
     * @var Collection<int, Certificate>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: Certificate::class, cascade: ['persist', 'remove'])]
    private Collection $certificates;

    public function __construct()
    {
        $this->orderIdentifiers = new ArrayCollection();
        $this->authorizations = new ArrayCollection();
        $this->certificates = new ArrayCollection();
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

    public function setAccount(?Account $account): void
    {
        $this->account = $account;
    }

    public function getOrderUrl(): string
    {
        return $this->orderUrl;
    }

    public function setOrderUrl(string $orderUrl): void
    {
        $this->orderUrl = $orderUrl;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
        $this->valid = (OrderStatus::VALID === $status);
    }

    public function getExpiresTime(): ?\DateTimeImmutable
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(?\DateTimeImmutable $expiresTime): void
    {
        $this->expiresTime = $expiresTime;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }

    public function getFinalizeUrl(): ?string
    {
        return $this->finalizeUrl;
    }

    public function setFinalizeUrl(?string $finalizeUrl): void
    {
        $this->finalizeUrl = $finalizeUrl;
    }

    public function getCertificateUrl(): ?string
    {
        return $this->certificateUrl;
    }

    public function setCertificateUrl(?string $certificateUrl): void
    {
        $this->certificateUrl = $certificateUrl;
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
     * @return Collection<int, Identifier>
     */
    public function getOrderIdentifiers(): Collection
    {
        return $this->orderIdentifiers;
    }

    public function addOrderIdentifier(Identifier $identifier): void
    {
        if (!$this->orderIdentifiers->contains($identifier)) {
            $this->orderIdentifiers->add($identifier);
            $identifier->setOrder($this);
        }
    }

    public function removeOrderIdentifier(Identifier $identifier): void
    {
        if ($this->orderIdentifiers->removeElement($identifier)) {
            if ($identifier->getOrder() === $this) {
                $identifier->setOrder(null);
            }
        }
    }

    /**
     * @return Collection<int, Authorization>
     */
    public function getAuthorizations(): Collection
    {
        return $this->authorizations;
    }

    public function addAuthorization(Authorization $authorization): void
    {
        if (!$this->authorizations->contains($authorization)) {
            $this->authorizations->add($authorization);
            $authorization->setOrder($this);
        }
    }

    public function removeAuthorization(Authorization $authorization): void
    {
        if ($this->authorizations->removeElement($authorization)) {
            if ($authorization->getOrder() === $this) {
                $authorization->setOrder(null);
            }
        }
    }

    /**
     * @return Collection<int, Certificate>
     */
    public function getCertificates(): Collection
    {
        return $this->certificates;
    }

    public function addCertificate(Certificate $certificate): void
    {
        if (!$this->certificates->contains($certificate)) {
            $this->certificates->add($certificate);
            $certificate->setOrder($this);
        }
    }

    public function removeCertificate(Certificate $certificate): void
    {
        if ($this->certificates->removeElement($certificate)) {
            if ($certificate->getOrder() === $this) {
                $certificate->setOrder(null);
            }
        }
    }

    public function getCertificate(): ?Certificate
    {
        $certificate = $this->certificates->first();

        return false !== $certificate ? $certificate : null;
    }

    public function setCertificate(?Certificate $certificate): void
    {
        // 清空所有现有证书
        foreach ($this->certificates as $existingCertificate) {
            $this->removeCertificate($existingCertificate);
        }

        // 添加新证书
        if (null !== $certificate) {
            $this->addCertificate($certificate);
        }
    }

    /**
     * 检查订单是否已过期
     */
    public function isExpired(): bool
    {
        return null !== $this->expiresTime && $this->expiresTime < new \DateTimeImmutable();
    }

    /**
     * 检查订单是否准备就绪
     */
    public function isReady(): bool
    {
        return OrderStatus::READY === $this->status;
    }

    /**
     * 检查订单是否有效
     */
    public function isInvalid(): bool
    {
        return OrderStatus::INVALID === $this->status;
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
