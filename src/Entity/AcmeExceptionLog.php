<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\ACMEClientBundle\Repository\AcmeExceptionLogRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * ACME 异常日志实体
 *
 * 记录所有 ACME 操作过程中发生的异常，便于排查问题
 */
#[ORM\Entity(repositoryClass: AcmeExceptionLogRepository::class)]
#[ORM\Table(name: 'acme_exception_logs', options: ['comment' => 'ACME 异常日志表，记录所有 ACME 操作过程中发生的异常'])]
#[ORM\Index(columns: ['exception_class'], name: 'idx_exception_class')]
#[ORM\Index(columns: ['occurred_time'], name: 'idx_exception_occurred_time')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_exception_entity')]
class AcmeExceptionLog implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '异常类名'])]
    #[IndexColumn]
    private string $exceptionClass;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '异常消息'])]
    private string $message;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '异常代码'])]
    private int $code = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '异常堆栈跟踪'])]
    private ?string $stackTrace = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '异常发生的文件'])]
    private ?string $file = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '异常发生的行号'])]
    private ?int $line = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '相关实体类型'])]
    #[IndexColumn]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '相关实体ID'])]
    #[IndexColumn]
    private ?int $entityId = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '异常上下文信息'])]
    private ?array $context = null;


    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'HTTP 请求 URL'])]
    private ?string $httpUrl = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => 'HTTP 请求方法'])]
    private ?string $httpMethod = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => 'HTTP 响应状态码'])]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '异常是否已处理'])]
    private bool $resolved = false;

    public function __toString(): string
    {
        return sprintf('Exception #%d: %s', $this->id ?? 0, $this->exceptionClass);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function setExceptionClass(string $exceptionClass): static
    {
        $this->exceptionClass = $exceptionClass;
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

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(?string $stackTrace): static
    {
        $this->stackTrace = $stackTrace;
        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function setLine(?int $line): static
    {
        $this->line = $line;
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

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setResolved(bool $resolved): static
    {
        $this->resolved = $resolved;
        return $this;
    }

    /**
     * 从异常对象创建日志实体
     */
    public static function fromException(\Throwable $exception, ?string $entityType = null, ?int $entityId = null, ?array $context = null): self
    {
        $log = new self();
        $log->setExceptionClass(get_class($exception));
        $log->setMessage($exception->getMessage());
        $log->setCode($exception->getCode());
        $log->setStackTrace($exception->getTraceAsString());
        $log->setFile($exception->getFile());
        $log->setLine($exception->getLine());
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setContext($context);

        return $log;
    }

    /**
     * 获取简化的异常信息（不包含堆栈跟踪）
     */
    public function getShortDescription(): string
    {
        $description = $this->exceptionClass . ': ' . $this->message;
        if ($this->file !== null && $this->line !== null) {
            $description .= ' in ' . basename($this->file) . ':' . $this->line;
        }
        return $description;
    }

    /**
     * 检查是否有关联实体
     */
    public function hasRelatedEntity(): bool
    {
        return $this->entityType !== null && $this->entityId !== null;
    }
}
