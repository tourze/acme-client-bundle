<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 账户实体
 *
 * 存储 ACME 服务提供商的账户信息，包括私钥、状态、联系信息等
 */
#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'acme_accounts')]
#[ORM\Index(columns: ['status'], name: 'idx_account_status')]
#[ORM\Index(columns: ['acme_server_url'], name: 'idx_account_server')]
class Account implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => 'ACME 服务器 URL'])]
    #[IndexColumn]
    private string $acmeServerUrl;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '账户在 ACME 服务器上的 URL'])]
    private ?string $accountUrl = null;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '账户私钥（加密存储）'])]
    private string $privateKey;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '账户公钥 JWK JSON'])]
    private string $publicKeyJwk;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AccountStatus::class, options: ['comment' => '账户状态'])]
    #[IndexColumn]
    private AccountStatus $status = AccountStatus::PENDING;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '联系信息（如邮箱）'])]
    private ?array $contacts = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否同意服务条款'])]
    private bool $termsOfServiceAgreed = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '账户是否有效'])]
    private bool $valid = false;

    /**
     * 关联的订单
     *
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(mappedBy: 'account', targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('Account #%d (%s)', $this->id ?? 0, $this->acmeServerUrl ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAcmeServerUrl(): string
    {
        return $this->acmeServerUrl;
    }

    public function setAcmeServerUrl(string $acmeServerUrl): static
    {
        $this->acmeServerUrl = $acmeServerUrl;
        return $this;
    }

    public function getAccountUrl(): ?string
    {
        return $this->accountUrl;
    }

    public function setAccountUrl(?string $accountUrl): static
    {
        $this->accountUrl = $accountUrl;
        return $this;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function setPrivateKey(string $privateKey): static
    {
        $this->privateKey = $privateKey;
        return $this;
    }

    public function getPublicKeyJwk(): string
    {
        return $this->publicKeyJwk;
    }

    public function setPublicKeyJwk(string $publicKeyJwk): static
    {
        $this->publicKeyJwk = $publicKeyJwk;
        return $this;
    }

    public function getStatus(): AccountStatus
    {
        return $this->status;
    }

    public function setStatus(AccountStatus $status): static
    {
        $this->status = $status;
        $this->valid = ($status === AccountStatus::VALID);
        return $this;
    }

    public function getContacts(): ?array
    {
        return $this->contacts;
    }

    public function setContacts(?array $contacts): static
    {
        $this->contacts = $contacts;
        return $this;
    }

    public function isTermsOfServiceAgreed(): bool
    {
        return $this->termsOfServiceAgreed;
    }

    public function setTermsOfServiceAgreed(bool $termsOfServiceAgreed): static
    {
        $this->termsOfServiceAgreed = $termsOfServiceAgreed;
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
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setAccount($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getAccount() === $this) {
                $order->setAccount(null);
            }
        }

        return $this;
    }

    /**
     * 检查账户是否已停用
     */
    public function isDeactivated(): bool
    {
        return $this->status === AccountStatus::DEACTIVATED;
    }
}
