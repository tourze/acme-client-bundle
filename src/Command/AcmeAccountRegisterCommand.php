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
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\AcmeLogService;

/**
 * ACME 账户注册命令
 */
#[AsCommand(name: self::NAME, description: '注册ACME账户', help: <<<'TXT'

    此命令用于向ACME服务提供商注册新账户。

    示例:
      <info>php bin/console acme:account:register user@example.com</info>
      <info>php bin/console acme:account:register user@example.com --directory-url=https://acme-v02.api.letsencrypt.org/directory --agree-tos</info>

    默认使用Let's Encrypt的测试环境，生产环境请使用:
      --directory-url=https://acme-v02.api.letsencrypt.org/directory

    TXT)]
class AcmeAccountRegisterCommand extends Command
{
    public const NAME = 'acme:account:register';

    public function __construct(
        private readonly AccountService $accountService,
        private readonly AcmeLogService $logService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, '账户邮箱地址')
            ->addOption('directory-url', 'd', InputOption::VALUE_OPTIONAL, 'ACME目录URL', 'https://acme-staging-v02.api.letsencrypt.org/directory')
            ->addOption('key-size', 'k', InputOption::VALUE_OPTIONAL, '私钥大小（位）', '2048')
            ->addOption('agree-tos', null, InputOption::VALUE_NONE, '自动同意服务条款')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailRaw = $input->getArgument('email');
        $email = \is_string($emailRaw) ? $emailRaw : '';
        $directoryUrlRaw = $input->getOption('directory-url');
        $directoryUrl = \is_string($directoryUrlRaw) ? $directoryUrlRaw : '';
        $keySizeRaw = $input->getOption('key-size');
        $keySize = \is_numeric($keySizeRaw) ? (int) $keySizeRaw : 2048;
        $agreeTos = $input->getOption('agree-tos');

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('提供的邮箱地址无效');

            return Command::FAILURE;
        }

        try {
            $io->section('ACME 账户注册');
            $io->info("邮箱: {$email}");
            $io->info("目录URL: {$directoryUrl}");
            $io->info("私钥大小: {$keySize} 位");

            // 检查是否已存在账户
            $existingAccount = $this->accountService->findAccountByEmail($email, $directoryUrl);
            if (null !== $existingAccount) {
                $io->warning("账户已存在 (ID: {$existingAccount->getId()})");
                $io->table(
                    ['字段', '值'],
                    [
                        ['ID', $existingAccount->getId()],
                        ['邮箱', $this->accountService->getEmailFromAccount($existingAccount)],
                        ['状态', $existingAccount->getStatus()->value],
                        ['账户URL', $existingAccount->getAccountUrl()],
                        ['注册时间', $existingAccount->getCreateTime()?->format('Y-m-d H:i:s') ?? 'N/A'],
                    ]
                );

                return Command::SUCCESS;
            }

            // 确认服务条款
            if (true !== $agreeTos) {
                if (!$io->confirm('您需要同意ACME服务条款才能继续注册，是否同意？', false)) {
                    $io->info('用户取消注册');

                    return Command::FAILURE;
                }
            }

            $io->info('正在生成账户私钥...');

            // 注册账户
            $account = $this->accountService->registerAccountByEmail($email, $directoryUrl, $keySize);

            $this->logService->logAccountOperation(
                'register',
                "账户注册成功: {$email}",
                $account->getId(),
                [
                    'email' => $email,
                    'directory_url' => $directoryUrl,
                    'key_size' => $keySize,
                ]
            );

            $io->success('账户注册成功！');
            $io->table(
                ['字段', '值'],
                [
                    ['ID', $account->getId()],
                    ['邮箱', $this->accountService->getEmailFromAccount($account)],
                    ['状态', $account->getStatus()->value],
                    ['账户URL', $account->getAccountUrl()],
                    ['注册时间', $account->getCreateTime()?->format('Y-m-d H:i:s') ?? 'N/A'],
                ]
            );

            $io->note([
                '请妥善保管账户私钥！',
                '如需查看账户私钥，请直接查询数据库。',
                '建议在生产环境中使用专门的密钥管理系统。',
            ]);

            return Command::SUCCESS;
        } catch (AbstractAcmeException $e) {
            $this->logService->logAccountOperation(
                'register_failed',
                "账户注册失败: {$e->getMessage()}",
                null,
                [
                    'email' => $email,
                    'directory_url' => $directoryUrl,
                    'error' => $e->getMessage(),
                ]
            );

            $io->error("账户注册失败: {$e->getMessage()}");

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->logService->logException($e, 'account', null, [
                'email' => $email,
                'directory_url' => $directoryUrl,
            ]);

            $io->error("发生未知错误: {$e->getMessage()}");
            $io->note('详细错误信息已记录到日志中');

            return Command::FAILURE;
        }
    }
}
