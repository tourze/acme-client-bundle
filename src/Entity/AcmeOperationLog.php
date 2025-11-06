<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Repository\AcmeOperationLogRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 操作日志实体
 *
 * 记录所有 ACME 操作的详细日志，便于追踪和审计
 */
#[ORM\Entity(repositoryClass: AcmeOperationLogRepository::class)]
#[ORM\Table(name: 'acme_operation_logs', options: ['comment' => 'ACME 操作日志表，记录所有 ACME 操作的详细日志'])]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'acme_operation_logs_idx_operation_entity')]
class AcmeOperationLog implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: LogLevel::class, options: ['comment' => '日志级别'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '日志级别不能为空')]
    #[Assert\Choice(callback: [LogLevel::class, 'cases'], message: '无效的日志级别')]
    private LogLevel $level = LogLevel::INFO;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '操作类型'])]
    #[IndexColumn]
    #[Assert\NotBlank(message: '操作类型不能为空')]
    #[Assert\Length(max: 100, maxMessage: '操作类型长度不能超过 {{ limit }} 个字符')]
    private string $operation;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '操作描述'])]
    #[Assert\NotBlank(message: '操作描述不能为空')]
    #[Assert\Length(max: 65535, maxMessage: '操作描述长度不能超过 {{ limit }} 个字符')]
    private string $message;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '相关实体类型'])]
    #[IndexColumn]
    #[Assert\Length(max: 100, maxMessage: '实体类型长度不能超过 {{ limit }} 个字符')]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '相关实体ID'])]
    #[IndexColumn]
    #[Assert\PositiveOrZero(message: '实体ID必须为非负数')]
    private ?int $entityId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '操作上下文信息'])]
    #[Assert\Type(type: 'array', message: '上下文信息必须为数组类型')]
    private ?array $context = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'HTTP 请求 URL'])]
    #[Assert\Length(max: 255, maxMessage: 'HTTP URL 长度不能超过 {{ limit }} 个字符')]
    #[Assert\Url(message: '请输入有效的 URL 地址')]
    private ?string $httpUrl = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => 'HTTP 请求方法'])]
    #[Assert\Length(max: 10, maxMessage: 'HTTP 方法长度不能超过 {{ limit }} 个字符')]
    #[Assert\Choice(choices: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], message: '无效的 HTTP 方法')]
    private ?string $httpMethod = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => 'HTTP 响应状态码'])]
    #[Assert\Range(min: 100, max: 599, notInRangeMessage: 'HTTP 状态码必须在 {{ min }} 和 {{ max }} 之间')]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '操作耗时（毫秒）'])]
    #[Assert\PositiveOrZero(message: '操作耗时必须为非负数')]
    #[Assert\Range(min: 0, max: 3600000, notInRangeMessage: '操作耗时必须在 0 到 3600000 毫秒（1小时）之间')]
    private ?int $durationMs = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '操作是否成功'])]
    #[Assert\Type(type: 'bool', message: '成功标识必须为布尔类型')]
    private bool $success = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '操作发生时间'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '操作发生时间不能为空')]
    private \DateTimeImmutable $occurredTime;

    public function __construct()
    {
        $this->occurredTime = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('Log #%d: %s', $this->id ?? 0, $this->operation);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function setLevel(LogLevel $level): void
    {
        $this->level = $level;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): void
    {
        $this->operation = $operation;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): void
    {
        $this->entityType = $entityType;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): void
    {
        $this->entityId = $entityId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function setContext(?array $context): void
    {
        $this->context = $context;
    }

    public function getHttpUrl(): ?string
    {
        return $this->httpUrl;
    }

    public function setHttpUrl(?string $httpUrl): void
    {
        $this->httpUrl = $httpUrl;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(?string $httpMethod): void
    {
        $this->httpMethod = $httpMethod;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(?int $httpStatusCode): void
    {
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): void
    {
        $this->durationMs = $durationMs;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getOccurredTime(): \DateTimeImmutable
    {
        return $this->occurredTime;
    }

    public function setOccurredTime(\DateTimeImmutable $occurredTime): void
    {
        $this->occurredTime = $occurredTime;
    }

    public function isError(): bool
    {
        return LogLevel::ERROR === $this->level;
    }

    public function isWarning(): bool
    {
        return LogLevel::WARNING === $this->level;
    }

    public function isInfo(): bool
    {
        return LogLevel::INFO === $this->level;
    }

    public function isDebug(): bool
    {
        return LogLevel::DEBUG === $this->level;
    }

    /**
     * 检查是否有关联实体
     */
    public function hasRelatedEntity(): bool
    {
        return null !== $this->entityType && null !== $this->entityId;
    }

    /**
     * 检查是否有 HTTP 请求信息
     */
    public function hasHttpRequest(): bool
    {
        return null !== $this->httpUrl;
    }

    /**
     * 检查是否有 HTTP 响应信息
     */
    public function hasHttpResponse(): bool
    {
        return null !== $this->httpStatusCode;
    }

    /**
     * 获取格式化的操作描述
     */
    public function getFormattedDescription(): string
    {
        $description = "[{$this->level->value}] {$this->operation}: {$this->message}";

        if ($this->hasRelatedEntity()) {
            $description .= " ({$this->entityType}#{$this->entityId})";
        }

        if (null !== $this->durationMs) {
            $description .= " ({$this->durationMs}ms)";
        }

        return $description;
    }

    /**
     * 创建账户操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public static function accountOperation(string $operation, string $message, ?int $accountId = null, ?array $details = null): self
    {
        $log = new self();
        $log->setOperation("account_{$operation}");
        $log->setMessage($message);
        $log->setEntityType('Account');
        $log->setEntityId($accountId);
        $log->setContext($details);

        return $log;
    }

    /**
     * 创建订单操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public static function orderOperation(string $operation, string $message, ?int $orderId = null, ?array $details = null): self
    {
        $log = new self();
        $log->setOperation("order_{$operation}");
        $log->setMessage($message);
        $log->setEntityType('Order');
        $log->setEntityId($orderId);
        $log->setContext($details);

        return $log;
    }

    /**
     * 创建质询操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public static function challengeOperation(string $operation, string $message, ?int $challengeId = null, ?array $details = null): self
    {
        $log = new self();
        $log->setOperation("challenge_{$operation}");
        $log->setMessage($message);
        $log->setEntityType('Challenge');
        $log->setEntityId($challengeId);
        $log->setContext($details);

        return $log;
    }

    /**
     * 创建证书操作日志
     *
     * @param array<string, mixed>|null $details
     */
    public static function certificateOperation(string $operation, string $message, ?int $certificateId = null, ?array $details = null): self
    {
        $log = new self();
        $log->setOperation("certificate_{$operation}");
        $log->setMessage($message);
        $log->setEntityType('Certificate');
        $log->setEntityId($certificateId);
        $log->setContext($details);

        return $log;
    }
}
