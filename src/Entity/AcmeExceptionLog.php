<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'acme_exception_logs_idx_exception_entity')]
class AcmeExceptionLog implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '异常类名'])]
    #[IndexColumn]
    #[Assert\NotBlank(message: '异常类名不能为空')]
    #[Assert\Length(max: 255, maxMessage: '异常类名长度不能超过 {{ limit }} 个字符')]
    private string $exceptionClass;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '异常消息'])]
    #[Assert\NotBlank(message: '异常消息不能为空')]
    #[Assert\Length(max: 65535, maxMessage: '异常消息长度不能超过 {{ limit }} 个字符')]
    private string $message;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '异常代码'])]
    #[Assert\Type(type: 'int', message: '异常代码必须是整数')]
    private int $code = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '异常堆栈跟踪'])]
    #[Assert\Length(max: 65535, maxMessage: '堆栈跟踪长度不能超过 {{ limit }} 个字符')]
    private ?string $stackTrace = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '异常发生的文件'])]
    #[Assert\Length(max: 255, maxMessage: '文件路径长度不能超过 {{ limit }} 个字符')]
    private ?string $file = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '异常发生的行号'])]
    #[Assert\Type(type: 'int', message: '行号必须是整数')]
    #[Assert\PositiveOrZero(message: '行号必须是非负整数')]
    private ?int $line = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '相关实体类型'])]
    #[IndexColumn]
    #[Assert\Length(max: 100, maxMessage: '实体类型长度不能超过 {{ limit }} 个字符')]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '相关实体ID'])]
    #[IndexColumn]
    #[Assert\Type(type: 'int', message: '实体ID必须是整数')]
    #[Assert\Positive(message: '实体ID必须是正整数')]
    private ?int $entityId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '异常上下文信息'])]
    #[Assert\Type(type: 'array', message: '上下文必须是数组格式')]
    private ?array $context = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'HTTP 请求 URL'])]
    #[Assert\Url(message: '请输入有效的 URL 格式')]
    #[Assert\Length(max: 255, maxMessage: 'HTTP URL 长度不能超过 {{ limit }} 个字符')]
    private ?string $httpUrl = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => 'HTTP 请求方法'])]
    #[Assert\Choice(choices: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], message: '请选择有效的 HTTP 方法')]
    #[Assert\Length(max: 10, maxMessage: 'HTTP 方法长度不能超过 {{ limit }} 个字符')]
    private ?string $httpMethod = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => 'HTTP 响应状态码'])]
    #[Assert\Type(type: 'int', message: 'HTTP状态码必须是整数')]
    #[Assert\Range(min: 100, max: 599, notInRangeMessage: 'HTTP状态码必须在 {{ min }} 到 {{ max }} 之间')]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '异常是否已处理'])]
    #[Assert\Type(type: 'bool', message: '解决状态必须是布尔值')]
    private bool $resolved = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '异常发生时间'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '异常发生时间不能为空')]
    private \DateTimeImmutable $occurredTime;

    public function __construct()
    {
        $this->occurredTime = new \DateTimeImmutable();
    }

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

    public function setExceptionClass(string $exceptionClass): void
    {
        $this->exceptionClass = $exceptionClass;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(?string $stackTrace): void
    {
        $this->stackTrace = $stackTrace;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): void
    {
        $this->file = $file;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function setLine(?int $line): void
    {
        $this->line = $line;
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

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setResolved(bool $resolved): void
    {
        $this->resolved = $resolved;
    }

    public function getOccurredTime(): \DateTimeImmutable
    {
        return $this->occurredTime;
    }

    public function setOccurredTime(\DateTimeImmutable $occurredTime): void
    {
        $this->occurredTime = $occurredTime;
    }

    /**
     * 从异常对象创建日志实体
     *
     * @param array<string, mixed>|null $context
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
        $log->setOccurredTime(new \DateTimeImmutable());

        return $log;
    }

    /**
     * 获取简化的异常信息（不包含堆栈跟踪）
     */
    public function getShortDescription(): string
    {
        $description = $this->exceptionClass . ': ' . $this->message;
        if (null !== $this->file && null !== $this->line) {
            $description .= ' in ' . basename($this->file) . ':' . $this->line;
        }

        return $description;
    }

    /**
     * 检查是否有关联实体
     */
    public function hasRelatedEntity(): bool
    {
        return null !== $this->entityType && null !== $this->entityId;
    }
}
