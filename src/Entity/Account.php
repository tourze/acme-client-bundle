<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
#[ORM\Table(name: 'acme_accounts', options: ['comment' => 'ACME 账户表，存储 ACME 服务提供商的账户信息'])]
class Account implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => 'ACME 服务器 URL'])]
    #[IndexColumn]
    #[Assert\NotBlank(message: 'ACME 服务器 URL 不能为空')]
    #[Assert\Url(message: '请输入有效的 URL 格式')]
    #[Assert\Length(max: 500, maxMessage: 'ACME 服务器 URL 长度不能超过 {{ limit }} 个字符')]
    private string $acmeServerUrl;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '账户在 ACME 服务器上的 URL'])]
    #[Assert\Url(message: '请输入有效的 URL 格式')]
    #[Assert\Length(max: 500, maxMessage: '账户 URL 长度不能超过 {{ limit }} 个字符')]
    private ?string $accountUrl = null;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '账户私钥（加密存储）'])]
    #[Assert\NotBlank(message: '私钥不能为空')]
    #[Assert\Length(max: 65535, maxMessage: '私钥长度不能超过 {{ limit }} 个字符')]
    private string $privateKey;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '账户公钥 JWK JSON'])]
    #[Assert\NotBlank(message: '公钥 JWK 不能为空')]
    #[Assert\Json(message: '公钥 JWK 必须是有效的 JSON 格式')]
    #[Assert\Length(max: 65535, maxMessage: '公钥 JWK 长度不能超过 {{ limit }} 个字符')]
    private string $publicKeyJwk;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AccountStatus::class, options: ['comment' => '账户状态'])]
    #[IndexColumn]
    #[Assert\Choice(callback: [AccountStatus::class, 'cases'], message: '请选择有效的账户状态')]
    private AccountStatus $status = AccountStatus::PENDING;

    /**
     * @var array<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '联系信息（如邮箱）'])]
    #[Assert\Type(type: 'array', message: '联系信息必须是数组格式')]
    private ?array $contacts = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否同意服务条款'])]
    #[Assert\Type(type: 'bool', message: '服务条款同意状态必须是布尔值')]
    private bool $termsOfServiceAgreed = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '账户是否有效'])]
    #[Assert\Type(type: 'bool', message: '账户有效状态必须是布尔值')]
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

    public function setAcmeServerUrl(string $acmeServerUrl): void
    {
        $this->acmeServerUrl = $acmeServerUrl;
    }

    public function getAccountUrl(): ?string
    {
        return $this->accountUrl;
    }

    public function setAccountUrl(?string $accountUrl): void
    {
        $this->accountUrl = $accountUrl;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function getPublicKeyJwk(): string
    {
        return $this->publicKeyJwk;
    }

    public function setPublicKeyJwk(string $publicKeyJwk): void
    {
        $this->publicKeyJwk = $publicKeyJwk;
    }

    public function getStatus(): AccountStatus
    {
        return $this->status;
    }

    public function setStatus(AccountStatus $status): void
    {
        $this->status = $status;
        $this->valid = (AccountStatus::VALID === $status);
    }

    /**
     * @return array<string>|null
     */
    public function getContacts(): ?array
    {
        return $this->contacts;
    }

    /**
     * @param array<string>|null $contacts
     */
    public function setContacts(?array $contacts): void
    {
        $this->contacts = $contacts;
    }

    public function isTermsOfServiceAgreed(): bool
    {
        return $this->termsOfServiceAgreed;
    }

    public function setTermsOfServiceAgreed(bool $termsOfServiceAgreed): void
    {
        $this->termsOfServiceAgreed = $termsOfServiceAgreed;
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
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setAccount($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): self
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
        return AccountStatus::DEACTIVATED === $this->status;
    }
}
