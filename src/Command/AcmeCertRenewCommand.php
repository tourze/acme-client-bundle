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
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Repository\CertificateRepository;
use Tourze\ACMEClientBundle\Service\AcmeLogService;
use Tourze\ACMEClientBundle\Service\CertificateService;
use Tourze\ACMEClientBundle\Service\OrderService;

/**
 * ACME 证书续订命令
 */
#[AsCommand(name: self::NAME, description: '续订即将过期的ACME证书', help: <<<'TXT'

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

    TXT)]
class AcmeCertRenewCommand extends Command
{
    public const NAME = 'acme:cert:renew';

    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly OrderService $orderService,
        private readonly CertificateRepository $certificateRepository,
        private readonly AcmeLogService $logService,
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $certificateIdRaw = $input->getArgument('certificate-id');
        $certificateId = \is_string($certificateIdRaw) ? $certificateIdRaw : null;
        $daysBeforeExpiryRaw = $input->getOption('days-before-expiry');
        $daysBeforeExpiry = \is_numeric($daysBeforeExpiryRaw) ? (int) $daysBeforeExpiryRaw : 30;
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $io->section('ACME 证书续订');

            if (true === $dryRun) {
                $io->note('模拟运行模式 - 不会执行实际的续订操作');
            }

            $certificates = $this->getCertificatesToRenew($certificateId, $daysBeforeExpiry, $force, $io);

            if ([] === $certificates) {
                $io->success('没有需要续订的证书');

                return Command::SUCCESS;
            }

            $this->displayCertificateTable($certificates, $io);

            if (true === $dryRun) {
                $io->note('模拟运行完成 - 以上证书需要续订');

                return Command::SUCCESS;
            }

            if (!$io->confirm('确认续订以上证书吗？', false)) {
                $io->info('用户取消操作');

                return Command::SUCCESS;
            }

            return $this->processCertificateRenewal($certificates, $io);
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

    /**
     * @return Certificate[]
     */
    private function getCertificatesToRenew(
        ?string $certificateId,
        int $daysBeforeExpiry,
        bool $force,
        SymfonyStyle $io,
    ): array {
        if (null !== $certificateId) {
            return $this->getSingleCertificate($certificateId, $io);
        }

        return $this->getMultipleCertificates($daysBeforeExpiry, $force, $io);
    }

    /**
     * @return array<Certificate>
     */
    private function getSingleCertificate(string $certificateId, SymfonyStyle $io): array
    {
        $certificate = $this->certificateRepository->find((int) $certificateId);
        if (null === $certificate) {
            $io->error("证书不存在 (ID: {$certificateId})");

            return [];
        }

        $io->info("目标证书: ID {$certificateId}");

        return [$certificate];
    }

    /**
     * @return Certificate[]
     */
    private function getMultipleCertificates(int $daysBeforeExpiry, bool $force, SymfonyStyle $io): array
    {
        if ($force) {
            $certificates = $this->certificateService->findValidCertificates();
            $io->info('强制续订模式: 找到 ' . count($certificates) . ' 个有效证书');
        } else {
            $certificates = $this->certificateService->findExpiringCertificates($daysBeforeExpiry);
            $io->info('找到 ' . count($certificates) . " 个即将过期的证书（{$daysBeforeExpiry}天内）");
        }

        return $certificates;
    }

    /**
     * @param Certificate[] $certificates
     */
    private function displayCertificateTable(array $certificates, SymfonyStyle $io): void
    {
        $tableData = [];

        foreach ($certificates as $certificate) {
            $tableData[] = $this->formatCertificateRow($certificate);
        }

        $io->table(
            ['证书ID', '域名', '状态', '过期时间', '剩余天数'],
            $tableData
        );
    }

    /**
     * @return string[]
     */
    private function formatCertificateRow(Certificate $certificate): array
    {
        $order = $certificate->getOrder();
        if (null === $order) {
            return ['N/A', 'N/A', 'N/A', 'N/A', 'N/A'];
        }
        $domains = [];
        foreach ($order->getOrderIdentifiers() as $identifier) {
            $domains[] = $identifier->getValue();
        }

        $daysToExpiry = $this->calculateDaysToExpiry($certificate);

        return [
            (string) $certificate->getId(),
            implode(', ', $domains),
            $certificate->getStatus()->value,
            $certificate->getNotAfterTime()?->format('Y-m-d H:i:s') ?? 'N/A',
            $daysToExpiry ?? 'N/A',
        ];
    }

    private function calculateDaysToExpiry(Certificate $certificate): ?string
    {
        if (null === $certificate->getNotAfterTime()) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $expiry = $certificate->getNotAfterTime();
        $diff = $now->diff($expiry);

        return $diff->format('%r%a');
    }

    /**
     * @param Certificate[] $certificates
     */
    private function processCertificateRenewal(array $certificates, SymfonyStyle $io): int
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($certificates as $certificate) {
            if ($this->renewSingleCertificate($certificate, $io)) {
                ++$successCount;
            } else {
                ++$failureCount;
            }
        }

        $this->displayRenewalResults($successCount, $failureCount, $io);

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function renewSingleCertificate(Certificate $certificate, SymfonyStyle $io): bool
    {
        $order = $certificate->getOrder();
        if (null === $order) {
            $io->error("证书 ID {$certificate->getId()} 没有关联的订单");

            return false;
        }
        $account = $order->getAccount();
        if (null === $account) {
            $io->error("订单 ID {$order->getId()} 没有关联的账户");

            return false;
        }
        $domains = $this->getDomainsList($order);

        try {
            $io->text("正在续订证书 ID {$certificate->getId()}: " . implode(', ', $domains));

            $newOrder = $this->orderService->createOrder($account, $domains);

            $this->logService->logCertificateOperation(
                'renew_started',
                '开始续订证书: ' . implode(', ', $domains),
                $certificate->getId(),
                [
                    'old_certificate_id' => $certificate->getId(),
                    'new_order_id' => $newOrder->getId(),
                    'domains' => $domains,
                ]
            );

            $io->success("证书续订已启动 (新订单ID: {$newOrder->getId()})");

            return true;
        } catch (AbstractAcmeException $e) {
            $this->handleRenewalException($e, $certificate, $domains, $io);

            return false;
        } catch (\Throwable $e) {
            $this->handleGeneralException($e, $certificate, $domains, $io);

            return false;
        }
    }

    /**
     * @return string[]
     */
    private function getDomainsList(Order $order): array
    {
        $domains = [];
        foreach ($order->getOrderIdentifiers() as $identifier) {
            $domains[] = $identifier->getValue();
        }

        return $domains;
    }

    /**
     * @param string[] $domains
     */
    private function handleRenewalException(
        AbstractAcmeException $e,
        Certificate $certificate,
        array $domains,
        SymfonyStyle $io,
    ): void {
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
    }

    /**
     * @param string[] $domains
     */
    private function handleGeneralException(
        \Throwable $e,
        Certificate $certificate,
        array $domains,
        SymfonyStyle $io,
    ): void {
        $this->logService->logException($e, 'certificate', $certificate->getId(), [
            'operation' => 'renew',
            'domains' => $domains,
        ]);

        $io->error("证书 ID {$certificate->getId()} 续订时发生未知错误: {$e->getMessage()}");
    }

    private function displayRenewalResults(int $successCount, int $failureCount, SymfonyStyle $io): void
    {
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
    }
}
