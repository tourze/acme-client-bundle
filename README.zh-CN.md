# ACME Client Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)
[![License](https://img.shields.io/packagist/l/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)

一个提供完整 ACME（自动化证书管理环境）客户端实现的 Symfony Bundle，用于自动化 SSL/TLS 证书管理。

## 目录

- [功能特性](#功能特性)
- [安装](#安装)
- [配置](#配置)
- [快速开始](#快速开始)
- [控制台命令](#控制台命令)
- [服务](#服务)
- [数据库实体](#数据库实体)
- [异常处理](#异常处理)
- [高级用法](#高级用法)
- [安全](#安全)
- [测试](#测试)
- [贡献](#贡献)
- [许可证](#许可证)

## 功能特性

- **完整的 ACME 协议支持**：完整实现 ACME 协议，实现证书自动化管理
- **账户管理**：注册和管理 ACME 账户，支持公私钥对
- **证书生命周期**：自动化证书订购、验证和续期
- **挑战支持**：支持 HTTP-01、DNS-01 和 TLS-ALPN-01 挑战类型
- **操作日志**：全面记录所有 ACME 操作和异常
- **速率限制处理**：内置速率限制检测和重试机制
- **控制台命令**：提供证书管理和监控的命令行工具

## 安装

```bash
composer require tourze/acme-client-bundle
```

### 注册 Bundle

如果没有使用 Symfony Flex，需要手动添加到 `config/bundles.php`：

```php
return [
    // ...
    Tourze\ACMEClientBundle\ACMEClientBundle::class => ['all' => true],
];
```

## 配置

创建配置文件 `config/packages/acme_client.yaml`：

```yaml
acme_client:
    # ACME 服务器目录 URL
    # 生产环境: https://acme-v02.api.letsencrypt.org/directory
    # 测试环境: https://acme-staging-v02.api.letsencrypt.org/directory
    directory_url: '%env(ACME_DIRECTORY_URL)%'
    
    # HTTP 客户端配置
    http_client:
        max_retries: 3
        retry_delay: 1000  # 毫秒
        timeout: 30  # 秒
    
    # 账户配置
    account:
        contact_email: '%env(ACME_CONTACT_EMAIL)%'
        key_size: 4096  # RSA 密钥大小（位）
        
    # 证书配置
    certificate:
        days_before_expiry: 30  # 到期前多少天开始续期
        auto_renewal: true  # 启用自动续期
        
    # 日志配置
    logging:
        enabled: true
        level: info  # debug, info, warning, error
```

### 环境变量

```bash
# .env.local
ACME_DIRECTORY_URL="https://acme-staging-v02.api.letsencrypt.org/directory"
ACME_CONTACT_EMAIL="admin@example.com"
```

## 快速开始

### 基本用法

```php
<?php

use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\OrderService;

// 注册账户
$account = $accountService->registerAccount(
    contacts: ['mailto:admin@example.com'],
    termsOfServiceAgreed: true
);

// 订购证书
$order = $orderService->createOrder(
    account: $account,
    domains: ['example.com', 'www.example.com']
);
```

## 控制台命令

### 1. 注册 ACME 账户

```bash
# 注册新账户到 Let's Encrypt 测试环境
php bin/console acme:account:register admin@example.com

# 注册到生产环境 Let's Encrypt
php bin/console acme:account:register admin@example.com \
    --directory-url=https://acme-v02.api.letsencrypt.org/directory

# 使用自定义密钥大小注册
php bin/console acme:account:register admin@example.com \
    --key-size=4096 --agree-tos

# 使用现有私钥注册
php bin/console acme:account:register admin@example.com \
    --key-file=/path/to/private.key --agree-tos
```

### 2. 创建证书订单

```bash
# 为单个域名订购证书
php bin/console acme:order:create example.com

# 为多个域名订购证书
php bin/console acme:order:create example.com www.example.com api.example.com

# 使用特定账户订购
php bin/console acme:order:create example.com --account-id=123

# 订购通配符证书
php bin/console acme:order:create "*.example.com" example.com
```

### 3. 续期证书

```bash
# 续期 30 天内到期的所有证书
php bin/console acme:cert:renew

# 续期指定天数内到期的证书
php bin/console acme:cert:renew --days-before-expiry=60

# 续期特定证书
php bin/console acme:cert:renew --certificate-id=123

# 模拟运行，查看将要续期的证书
php bin/console acme:cert:renew --dry-run
```

### 4. 查询操作日志

```bash
# 查看最近的 ACME 操作
php bin/console acme:log:query

# 按日志级别过滤
php bin/console acme:log:query --level=error

# 按日期范围过滤
php bin/console acme:log:query --start-date="2024-01-01" --end-date="2024-01-31"

# 按操作类型过滤
php bin/console acme:log:query --operation=order_create
```

## 服务

### AccountService（账户服务）

管理 ACME 账户：

```php
use Tourze\ACMEClientBundle\Service\AccountService;

// 创建新账户
$account = $accountService->registerAccount(
    contacts: ['mailto:admin@example.com'],
    termsOfServiceAgreed: true,
    privateKeyPem: null // 如果为 null 则自动生成
);

// 通过邮箱查找账户
$account = $accountService->findByEmail('admin@example.com');

// 更新账户联系方式
$accountService->updateContact($account, ['mailto:new@example.com']);

// 停用账户
$accountService->deactivateAccount($account);
```

### OrderService（订单服务）

管理证书订单：

```php
use Tourze\ACMEClientBundle\Service\OrderService;

// 创建新订单
$order = $orderService->createOrder(
    account: $account,
    domains: ['example.com', 'www.example.com']
);

// 处理授权
foreach ($order->getAuthorizations() as $authorization) {
    $orderService->processAuthorization($authorization);
}

// 使用 CSR 完成订单
$certificate = $orderService->finalizeOrder($order, $csrPem);

// 检查订单状态
$status = $orderService->updateOrderStatus($order);
```

### CertificateService（证书服务）

管理证书：

```php
use Tourze\ACMEClientBundle\Service\CertificateService;

// 查找即将过期的证书
$expiring = $certificateService->findExpiringCertificates(days: 30);

// 下载证书
$pemChain = $certificateService->downloadCertificate($certificate);

// 吊销证书
$certificateService->revokeCertificate($certificate, reason: 'keyCompromise');

// 检查证书有效性
$isValid = $certificateService->isCertificateValid($certificate);
```

### ChallengeService（挑战服务）

处理 ACME 挑战：

```php
use Tourze\ACMEClientBundle\Service\ChallengeService;

// 按类型获取挑战
$challenge = $challengeService->getChallengeByType(
    authorization: $authorization,
    type: 'http-01'
);

// 准备挑战响应
$challengeService->prepareChallenge($challenge);

// 触发验证
$challengeService->triggerValidation($challenge);

// 验证后清理
$challengeService->cleanupChallenge($challenge);
```

## 数据库实体

该 Bundle 提供以下实体：

- **Account**：ACME 账户，包含联系信息和密钥对
- **Order**：证书订单及关联的授权
- **Authorization**：域名授权及挑战
- **Challenge**：单个挑战（HTTP-01、DNS-01 等）
- **Certificate**：已颁发的证书及过期跟踪
- **Identifier**：订单的域名标识符
- **AcmeOperationLog**：所有 ACME 操作的日志
- **AcmeExceptionLog**：异常和错误日志

## 异常处理

Bundle 提供特定的异常类型：

```php
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;
use Tourze\ACMEClientBundle\Exception\AcmeRateLimitException;

try {
    $order = $orderService->createOrder($account, $domains);
} catch (AcmeRateLimitException $e) {
    // 处理速率限制，稍后重试
    $retryAfter = $e->getRetryAfter();
    $this->logger->warning("触发速率限制，{$retryAfter} 秒后重试");
} catch (AcmeValidationException $e) {
    // 处理验证错误
    $this->logger->error("验证失败：" . $e->getMessage());
} catch (AcmeServerException $e) {
    // 处理服务器错误
    $this->logger->error("服务器错误：" . $e->getMessage());
} catch (AcmeClientException $e) {
    // 处理通用 ACME 错误
    $this->logger->error("ACME 错误：" . $e->getMessage());
}
```

## 高级用法

### 自定义挑战处理器

实现自定义挑战验证逻辑：

```php
use Tourze\ACMEClientBundle\Service\ChallengeService;
use Tourze\ACMEClientBundle\Entity\Challenge;

class CustomChallengeHandler
{
    public function handleDnsChallenge(Challenge $challenge): bool
    {
        // 实现 DNS 记录创建
        $record = "_acme-challenge.{$challenge->getDomain()}";
        $value = $challenge->getKeyAuthorization();
        
        // 创建 DNS TXT 记录
        return $this->dnsProvider->createTxtRecord($record, $value);
    }
}
```

### 事件订阅者

监听 ACME 事件：

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\ACMEClientBundle\Event\CertificateOrderedEvent;

class CertificateEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CertificateOrderedEvent::class => 'onCertificateOrdered',
        ];
    }
    
    public function onCertificateOrdered(CertificateOrderedEvent $event): void
    {
        // 发送通知、更新监控等
        $certificate = $event->getCertificate();
        $this->notificationService->sendCertificateIssuedEmail($certificate);
    }
}
```

### 自定义日志

配置详细的操作日志：

```yaml
acme_client:
    logging:
        enabled: true
        level: debug
        channels: ['acme']
        
monolog:
    channels: ['acme']
    handlers:
        acme_file:
            type: stream
            path: "%kernel.logs_dir%/acme.log"
            level: debug
            channels: ['acme']
```

### 自动化证书续期

设置 cron 任务进行自动证书续期：

```bash
# Crontab 条目 - 每天凌晨 2 点运行
0 2 * * * cd /path/to/project && php bin/console acme:cert:renew --quiet
```

## 安全

### 密钥管理

- **私钥** 自动生成并安全存储
- 密钥存储在数据库中时会被加密
- 建议使用环境变量配置敏感信息

### 速率限制

Bundle 自动处理 ACME 速率限制：

- 检测速率限制响应（HTTP 429）
- 实现指数退避算法
- 记录速率限制遭遇以便监控

### 证书验证

- 证书在存储前会被验证
- 自动跟踪过期日期
- 无效证书会触发相应的异常

### 安全注意事项

- 安全存储私钥并设置限制文件权限（0600）
- 所有 ACME 通信使用 HTTPS
- 监控操作日志以发现安全事件
- 为证书管理命令实现适当的访问控制
- 定期轮换账户密钥
- 使用测试环境进行测试以避免速率限制

## 测试

运行测试套件：

```bash
# 运行所有测试
./vendor/bin/phpunit packages/acme-client-bundle/tests

# 运行覆盖率测试
./vendor/bin/phpunit packages/acme-client-bundle/tests --coverage-html coverage

# 运行特定测试套件
./vendor/bin/phpunit packages/acme-client-bundle/tests/Service

# 运行 PHPStan 分析
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/acme-client-bundle
```

## 贡献

1. Fork 仓库
2. 创建功能分支（`git checkout -b feature/amazing-feature`）
3. 提交更改（`git commit -m 'Add some amazing feature'`）
4. 推送到分支（`git push origin feature/amazing-feature`）
5. 创建 Pull Request

请确保：
- 所有测试通过
- 代码遵循 PSR-12 编码标准
- PHPStan 分析在级别 8 通过
- 文档已更新

## 许可证

本 Bundle 基于 MIT 许可证发布。详见 [LICENSE](LICENSE) 文件。

## 支持

- **问题反馈**：[GitHub Issues](https://github.com/tourze/php-monorepo/issues)
- **文档**：[ACME 协议 RFC 8555](https://tools.ietf.org/html/rfc8555)
- **Let's Encrypt**：[文档](https://letsencrypt.org/docs/)