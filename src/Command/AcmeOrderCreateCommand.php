<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\AcmeLogService;
use Tourze\ACMEClientBundle\Service\CertificateService;
use Tourze\ACMEClientBundle\Service\ChallengeService;
use Tourze\ACMEClientBundle\Service\OrderService;

/**
 * ACME 证书订单创建命令
 */
#[AsCommand(name: self::NAME, description: '创建ACME证书订单并完成DNS-01质询', help: <<<'TXT'

    此命令用于创建ACME证书订单并处理DNS-01质询。

    示例:
      <info>php bin/console acme:order:create 1 "example.com,www.example.com"</info>
      <info>php bin/console acme:order:create 1 "example.com" --wait-validation --auto-download</info>

    注意:
      - 需要确保账户已注册并有效
      - 需要配置DNS提供商以支持DNS-01质询
      - 使用 --wait-validation 选项会等待质询验证完成
      - 使用 --auto-download 选项会在验证成功后自动下载证书

    TXT)]
class AcmeOrderCreateCommand extends Command
{
    public const NAME = 'acme:order:create';

    public function __construct(
        private readonly AccountService $accountService,
        private readonly OrderService $orderService,
        private readonly ChallengeService $challengeService,
        private readonly CertificateService $certificateService,
        private readonly AcmeLogService $logService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('account-id', InputArgument::REQUIRED, '账户ID')
            ->addArgument('domains', InputArgument::REQUIRED, '域名列表（逗号分隔）')
            ->addOption('wait-validation', 'w', InputOption::VALUE_NONE, '等待DNS质询验证完成')
            ->addOption('auto-download', 'a', InputOption::VALUE_NONE, '自动下载证书')
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, '质询验证超时时间（秒）', '300')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $accountIdRaw = $input->getArgument('account-id');
        $accountId = \is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;
        $domainsInputRaw = $input->getArgument('domains');
        $domainsInput = \is_string($domainsInputRaw) ? $domainsInputRaw : '';
        $waitValidation = (bool) $input->getOption('wait-validation');
        $autoDownload = (bool) $input->getOption('auto-download');
        $timeoutRaw = $input->getOption('timeout');
        $timeout = \is_numeric($timeoutRaw) ? (int) $timeoutRaw : 300;

        $domains = $this->parseDomains($domainsInput, $io);
        if (null === $domains) {
            return Command::FAILURE;
        }

        try {
            $account = $this->validateAccount($io, $accountId);
            if (null === $account) {
                return Command::FAILURE;
            }

            $order = $this->createOrder($io, $account, $accountId, $domains);
            $this->processAuthorizations($io, $order);

            if ($waitValidation) {
                return $this->handleValidationAndDownload($io, $order, $autoDownload, $timeout, $accountId, $domains);
            }

            $this->showInstructions($io, $order);

            return Command::SUCCESS;
        } catch (AbstractAcmeException $e) {
            return $this->handleAcmeException($e, $accountId, $domains);
        } catch (\Throwable $e) {
            return $this->handleGenericException($e, $accountId, $domains);
        }
    }

    /**
     * @return string[]|null
     */
    private function parseDomains(string $domainsInput, SymfonyStyle $io): ?array
    {
        $domains = array_map('trim', explode(',', $domainsInput));
        $domains = array_filter($domains, static fn (string $domain): bool => '' !== $domain);

        if ([] === $domains) {
            $io->error('域名列表不能为空');

            return null;
        }

        foreach ($domains as $domain) {
            if (false === filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $io->error("无效的域名格式: {$domain}");

                return null;
            }
        }

        return $domains;
    }

    private function validateAccount(SymfonyStyle $io, int $accountId): ?Account
    {
        $account = $this->accountService->getAccountById($accountId);
        if (null === $account) {
            $io->error("账户不存在 (ID: {$accountId})");

            return null;
        }

        if (!$this->accountService->isAccountValid($account)) {
            $io->error("账户无效或已停用 (ID: {$accountId})");

            return null;
        }

        return $account;
    }

    /**
     * @param string[] $domains
     */
    private function createOrder(SymfonyStyle $io, Account $account, int $accountId, array $domains): Order
    {
        $io->section('ACME 证书订单创建');
        $io->info("账户ID: {$accountId}");
        $io->info('域名: ' . implode(', ', $domains));
        $io->info('账户: ' . ($this->accountService->getEmailFromAccount($account) ?? 'N/A'));
        $io->info('服务器: ' . $account->getAcmeServerUrl());

        $io->text('正在创建证书订单...');
        $order = $this->orderService->createOrder($account, $domains);

        $this->logService->logOrderOperation(
            'create',
            '订单创建成功: ' . implode(', ', $domains),
            $order->getId(),
            [
                'account_id' => $accountId,
                'domains' => $domains,
            ]
        );

        $io->success("订单创建成功 (ID: {$order->getId()})");

        $io->table(
            ['字段', '值'],
            [
                ['订单ID', $order->getId()],
                ['状态', $order->getStatus()->value],
                ['域名', implode(', ', $domains)],
                ['创建时间', $order->getCreateTime()?->format('Y-m-d H:i:s') ?? 'N/A'],
            ]
        );

        return $order;
    }

    private function processAuthorizations(SymfonyStyle $io, Order $order): int
    {
        $io->text('正在处理域名授权...');
        $authorizations = $this->orderService->getOrderAuthorizations($order);

        foreach ($authorizations as $authorization) {
            $io->text("处理授权: {$authorization->getIdentifierValue()}");

            $challenge = $this->challengeService->getDns01Challenge($authorization);
            if (null === $challenge) {
                $io->error("未找到DNS-01质询: {$authorization->getIdentifierValue()}");

                return Command::FAILURE;
            }

            $io->text('正在设置DNS记录...');
            $this->challengeService->setupDnsRecord($challenge);

            $io->text('正在启动质询验证...');
            $this->challengeService->startChallenge($challenge);
        }

        $io->success('所有DNS记录已设置，质询验证已启动');

        return Command::SUCCESS;
    }

    /**
     * @param string[] $domains
     */
    private function handleValidationAndDownload(
        SymfonyStyle $io,
        Order $order,
        bool $autoDownload,
        int $timeout,
        int $accountId,
        array $domains,
    ): int {
        $io->text("等待质询验证完成（超时: {$timeout}秒）...");

        $validated = $this->waitForValidation($io, $order, $timeout);
        if (!$validated) {
            $io->warning('质询验证超时，请稍后手动检查');

            return Command::FAILURE;
        }

        $io->success('质询验证完成！');

        if ($autoDownload) {
            return $this->downloadCertificate($io, $order, $domains);
        }

        return Command::SUCCESS;
    }

    private function waitForValidation(SymfonyStyle $io, Order $order, int $timeout): bool
    {
        $startTime = time();

        while ((time() - $startTime) < $timeout) {
            $order = $this->orderService->refreshOrderStatus($order);

            if (OrderStatus::READY === $order->getStatus()) {
                return true;
            }

            if (OrderStatus::INVALID === $order->getStatus()) {
                $io->error('订单验证失败');

                return false;
            }

            $io->text('质询验证中...（剩余: ' . ($timeout - (time() - $startTime)) . '秒）');
            sleep(5);
        }

        return false;
    }

    /**
     * @param string[] $domains
     */
    private function downloadCertificate(SymfonyStyle $io, Order $order, array $domains): int
    {
        $io->text('正在完成订单...');
        $order = $this->orderService->finalizeOrderWithAutoCSR($order);

        $io->text('正在下载证书...');
        $certificate = $this->certificateService->downloadCertificate($order);

        $this->logService->logCertificateOperation(
            'download',
            '证书下载成功',
            $certificate->getId(),
            [
                'order_id' => $order->getId(),
                'domains' => $domains,
            ]
        );

        $io->success("证书下载成功 (ID: {$certificate->getId()})");

        $io->table(
            ['字段', '值'],
            [
                ['证书ID', $certificate->getId()],
                ['状态', $certificate->getStatus()->value],
                ['有效期至', $certificate->getNotAfterTime()?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['序列号', $certificate->getSerialNumber() ?? 'N/A'],
            ]
        );

        return Command::SUCCESS;
    }

    private function showInstructions(SymfonyStyle $io, Order $order): void
    {
        $io->note([
            '质询验证已启动，请等待验证完成。',
            '您可以使用以下命令检查状态:',
            "php bin/console acme:order:status {$order->getId()}",
        ]);
    }

    /**
     * @param string[] $domains
     */
    private function handleAcmeException(AbstractAcmeException $e, int $accountId, array $domains): int
    {
        $this->logService->logOrderOperation(
            'create_failed',
            "订单创建失败: {$e->getMessage()}",
            null,
            [
                'account_id' => $accountId,
                'domains' => $domains,
                'error' => $e->getMessage(),
            ]
        );

        (new SymfonyStyle(new ArrayInput([]), new ConsoleOutput()))
            ->error("订单创建失败: {$e->getMessage()}")
        ;

        return Command::FAILURE;
    }

    /**
     * @param string[] $domains
     */
    private function handleGenericException(\Throwable $e, int $accountId, array $domains): int
    {
        $this->logService->logException($e, 'order', null, [
            'account_id' => $accountId,
            'domains' => $domains,
        ]);

        $io = new SymfonyStyle(new ArrayInput([]), new ConsoleOutput());
        $io->error("发生未知错误: {$e->getMessage()}");
        $io->note('详细错误信息已记录到日志中');

        return Command::FAILURE;
    }
}
