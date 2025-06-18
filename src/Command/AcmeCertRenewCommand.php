<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\CertificateRepository;
use Tourze\ACMEClientBundle\Service\AcmeLogService;
use Tourze\ACMEClientBundle\Service\CertificateService;
use Tourze\ACMEClientBundle\Service\OrderService;

/**
 * ACME 证书续订命令
 */
#[AsCommand(
    name: 'acme:cert:renew',
    description: '续订即将过期的ACME证书',
)]
class AcmeCertRenewCommand extends Command
{
    public const NAME = 'acme:cert:renew';
    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly OrderService $orderService,
        private readonly CertificateRepository $certificateRepository,
        private readonly AcmeLogService $logService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('certificate-id', InputArgument::OPTIONAL, '证书ID（可选，如果不提供则续订所有即将过期的证书）')
            ->addOption('days-before-expiry', 'd', InputOption::VALUE_OPTIONAL, '提前多少天续订证书', '30')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制续订，即使证书尚未到期')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '模拟运行，不实际执行续订操作')
            ->setHelp('
此命令用于续订即将过期的ACME证书。

示例:
  <info>php bin/console acme:cert:renew</info>                    # 续订所有即将过期的证书
  <info>php bin/console acme:cert:renew 123</info>               # 续订指定ID的证书
  <info>php bin/console acme:cert:renew --days-before-expiry=7</info>  # 提前7天续订证书
  <info>php bin/console acme:cert:renew --force</info>           # 强制续订所有证书
  <info>php bin/console acme:cert:renew --dry-run</info>         # 模拟运行

注意:
  - 默认会在证书过期前30天进行续订
  - 使用 --force 选项会续订所有有效证书，不考虑过期时间
  - 使用 --dry-run 选项可以查看哪些证书需要续订，但不会实际执行
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $certificateId = $input->getArgument('certificate-id');
        $daysBeforeExpiry = (int) $input->getOption('days-before-expiry');
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $io->section('ACME 证书续订');

            if ($dryRun === true) {
                $io->note('模拟运行模式 - 不会执行实际的续订操作');
            }

            $certificates = [];

            if ($certificateId !== null) {
                // 续订指定证书
                $certificate = $this->certificateRepository->find((int) $certificateId);
                if ($certificate === null) {
                    $io->error("证书不存在 (ID: {$certificateId})");
                    return Command::FAILURE;
                }
                $certificates = [$certificate];
                $io->info("目标证书: ID {$certificateId}");
            } else {
                // 查找需要续订的证书
                if ($force) {
                    $certificates = $this->certificateService->findValidCertificates();
                    $io->info("强制续订模式: 找到 " . count($certificates) . " 个有效证书");
                } else {
                    $certificates = $this->certificateService->findExpiringCertificates($daysBeforeExpiry);
                    $io->info("找到 " . count($certificates) . " 个即将过期的证书（{$daysBeforeExpiry}天内）");
                }
            }

            if (empty($certificates)) {
                $io->success('没有需要续订的证书');
                return Command::SUCCESS;
            }

            // 显示证书列表
            $tableData = [];
            foreach ($certificates as $certificate) {
                $order = $certificate->getOrder();
                $domains = [];
                foreach ($order->getOrderIdentifiers() as $identifier) {
                    $domains[] = $identifier->getValue();
                }

                $daysToExpiry = null;
                if ($certificate->getNotAfterTime() !== null) {
                    $now = new \DateTimeImmutable();
                    $expiry = $certificate->getNotAfterTime();
                    $diff = $now->diff($expiry);
                    $daysToExpiry = $diff->format('%r%a');
                }

                $tableData[] = [
                    $certificate->getId(),
                    implode(', ', $domains),
                    $certificate->getStatus()->value,
                    $certificate->getNotAfterTime()?->format('Y-m-d H:i:s') ?? 'N/A',
                    $daysToExpiry ?? 'N/A',
                ];
            }

            $io->table(
                ['证书ID', '域名', '状态', '过期时间', '剩余天数'],
                $tableData
            );

            if ($dryRun === true) {
                $io->note('模拟运行完成 - 以上证书需要续订');
                return Command::SUCCESS;
            }

            if (!$io->confirm('确认续订以上证书吗？', false)) {
                $io->info('用户取消操作');
                return Command::SUCCESS;
            }

            // 执行续订
            $successCount = 0;
            $failureCount = 0;

            foreach ($certificates as $certificate) {
                $order = $certificate->getOrder();
                $account = $order->getAccount();
                $domains = [];
                foreach ($order->getOrderIdentifiers() as $identifier) {
                    $domains[] = $identifier->getValue();
                }

                try {
                    $io->text("正在续订证书 ID {$certificate->getId()}: " . implode(', ', $domains));

                    // 创建新订单来续订证书
                    $newOrder = $this->orderService->createOrder($account, $domains);

                    $this->logService->logCertificateOperation(
                        'renew_started',
                        "开始续订证书: " . implode(', ', $domains),
                        $certificate->getId(),
                        [
                            'old_certificate_id' => $certificate->getId(),
                            'new_order_id' => $newOrder->getId(),
                            'domains' => $domains,
                        ]
                    );

                    $io->success("证书续订已启动 (新订单ID: {$newOrder->getId()})");
                    $successCount++;
                } catch (AcmeClientException $e) {
                    $this->logService->logCertificateOperation(
                        'renew_failed',
                        "证书续订失败: {$e->getMessage()}",
                        $certificate->getId(),
                        [
                            'error' => $e->getMessage(),
                            'domains' => $domains,
                        ]
                    );

                    $io->error("证书 ID {$certificate->getId()} 续订失败: {$e->getMessage()}");
                    $failureCount++;
                } catch (\Throwable $e) {
                    $this->logService->logException($e, 'certificate', $certificate->getId(), [
                        'operation' => 'renew',
                        'domains' => $domains,
                    ]);

                    $io->error("证书 ID {$certificate->getId()} 续订时发生未知错误: {$e->getMessage()}");
                    $failureCount++;
                }
            }

            // 显示续订结果
            if ($successCount > 0) {
                $io->success("成功启动 {$successCount} 个证书的续订流程");
            }

            if ($failureCount > 0) {
                $io->warning("有 {$failureCount} 个证书续订失败");
            }

            $io->note([
                '续订已启动的证书需要完成DNS质询验证才能获得新证书。',
                '请使用 acme:order:create 命令来处理质询和下载新证书。',
                '或者使用 acme:log:query 命令查看详细日志。',
            ]);

            return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logService->logException($e, 'certificate', null, [
                'operation' => 'bulk_renew',
                'certificate_id' => $certificateId,
            ]);

            $io->error("续订过程中发生错误: {$e->getMessage()}");
            $io->note('详细错误信息已记录到日志中');
            return Command::FAILURE;
        }
    }
}
