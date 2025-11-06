<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\ACMEClientBundle\Repository\IdentifierRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 标识实体
 *
 * 存储订单中的域名标识信息
 */
#[ORM\Entity(repositoryClass: IdentifierRepository::class)]
#[ORM\Table(name: 'acme_identifiers', options: ['comment' => 'ACME 标识符表，存储订单中的域名标识信息'])]
class Identifier implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    /**
     * 关联的订单
     */
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderIdentifiers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Order $order = null;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => '标识符类型（如 dns）'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[Assert\Choice(choices: ['dns'])]
    private string $type = 'dns';

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '标识符值（如域名）'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $value;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否为通配符域名'])]
    #[Assert\Type(type: 'bool', message: '通配符状态必须是布尔值')]
    private bool $wildcard = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '标识符是否有效'])]
    #[Assert\Type(type: 'bool', message: '有效状态必须是布尔值')]
    private bool $valid = false;

    public function __toString(): string
    {
        return sprintf('%s: %s', $this->type, $this->value);
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
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
}
