<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
#[ORM\Table(name: 'acme_operation_logs')]
#[ORM\Index(columns: ['operation_type'], name: 'idx_operation_type')]
#[ORM\Index(columns: ['occurred_time'], name: 'idx_operation_occurred_time')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_operation_entity')]
#[ORM\Index(columns: ['level'], name: 'idx_operation_level')]
class AcmeOperationLog implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: LogLevel::class, options: ['comment' => '日志级别'])]
    #[IndexColumn]
    private LogLevel $level = LogLevel::INFO;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '操作类型'])]
    #[IndexColumn]
    private string $operation;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '操作描述'])]
    private string $message;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '相关实体类型'])]
    #[IndexColumn]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '相关实体ID'])]
    #[IndexColumn]
    private ?int $entityId = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '操作上下文信息'])]
    private ?array $context = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'HTTP 请求 URL'])]
    private ?string $httpUrl = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => 'HTTP 请求方法'])]
    private ?string $httpMethod = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => 'HTTP 响应状态码'])]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '操作耗时（毫秒）'])]
    private ?int $durationMs = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '操作是否成功'])]
    private bool $success = true;

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

    public function setLevel(LogLevel $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): static
    {
        $this->operation = $operation;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getHttpUrl(): ?string
    {
        return $this->httpUrl;
    }

    public function setHttpUrl(?string $httpUrl): static
    {
        $this->httpUrl = $httpUrl;
        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(?string $httpMethod): static
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(?int $httpStatusCode): static
    {
        $this->httpStatusCode = $httpStatusCode;
        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): static
    {
        $this->durationMs = $durationMs;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): static
    {
        $this->success = $success;
        return $this;
    }

    public function isError(): bool
    {
        return $this->level === LogLevel::ERROR;
    }

    public function isWarning(): bool
    {
        return $this->level === LogLevel::WARNING;
    }

    public function isInfo(): bool
    {
        return $this->level === LogLevel::INFO;
    }

    public function isDebug(): bool
    {
        return $this->level === LogLevel::DEBUG;
    }

    /**
     * 检查是否有关联实体
     */
    public function hasRelatedEntity(): bool
    {
        return $this->entityType !== null && $this->entityId !== null;
    }

    /**
     * 检查是否有 HTTP 请求信息
     */
    public function hasHttpRequest(): bool
    {
        return $this->httpUrl !== null;
    }

    /**
     * 检查是否有 HTTP 响应信息
     */
    public function hasHttpResponse(): bool
    {
        return $this->httpStatusCode !== null;
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

        if ($this->durationMs !== null) {
            $description .= " ({$this->durationMs}ms)";
        }

        return $description;
    }

    /**
     * 创建账户操作日志
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
