<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ACMEClientBundle\Contract\QueryHandlerInterface;
use Tourze\ACMEClientBundle\Exception\QueryHandlerNotFoundException;

/**
 * ACME 日志查询命令
 */
#[AsCommand(name: self::NAME, description: '查询ACME操作日志和异常日志', help: <<<'TXT'

    此命令用于查询和管理ACME操作日志。

    查询示例:
      <info>php bin/console acme:log:query</info>                                   # 查询最近50条操作日志
      <info>php bin/console acme:log:query --type=exception</info>                 # 查询异常日志
      <info>php bin/console acme:log:query --operation=register</info>             # 查询账户注册操作
      <info>php bin/console acme:log:query --entity-type=certificate --entity-id=123</info>  # 查询指定证书的日志
      <info>php bin/console acme:log:query --level=error</info>                    # 查询错误级别日志
      <info>php bin/console acme:log:query --since="1 day ago"</info>              # 查询1天内的日志
      <info>php bin/console acme:log:query --stats</info>                          # 显示统计信息

    管理示例:
      <info>php bin/console acme:log:query --cleanup=30</info>                     # 清理30天前的日志

    支持的操作类型:
      - register, register_failed (账户注册)
      - create, create_failed (订单创建)
      - download, download_failed (证书下载)
      - renew, renew_started, renew_failed (证书续订)

    支持的实体类型:
      - account (账户)
      - order (订单)
      - challenge (质询)
      - certificate (证书)

    TXT)]
class AcmeLogQueryCommand extends Command
{
    public const NAME = 'acme:log:query';

    /**
     * @param QueryHandlerInterface[] $handlers
     */
    public function __construct(
        private readonly iterable $handlers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, '日志类型 (operation|exception)', 'operation')
            ->addOption('operation', 'o', InputOption::VALUE_OPTIONAL, '操作类型 (register|create|download|renew等)')
            ->addOption('entity-type', 'e', InputOption::VALUE_OPTIONAL, '实体类型 (account|order|challenge|certificate)')
            ->addOption('entity-id', null, InputOption::VALUE_OPTIONAL, '实体ID')
            ->addOption('level', 'l', InputOption::VALUE_OPTIONAL, '日志级别 (info|warning|error)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, '查询条数限制', '50')
            ->addOption('since', 's', InputOption::VALUE_OPTIONAL, '起始时间 (格式: Y-m-d H:i:s 或相对时间如 "1 hour ago")')
            ->addOption('stats', null, InputOption::VALUE_NONE, '显示统计信息')
            ->addOption('cleanup', null, InputOption::VALUE_OPTIONAL, '清理N天前的日志', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $options = $this->parseInputOptions($input);

        try {
            $handler = $this->findHandler($options);
            $io->section('ACME 日志查询');

            return $handler->handle($io, $options);
        } catch (\Throwable $e) {
            $io->error("执行命令时发生错误: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseInputOptions(InputInterface $input): array
    {
        $entityIdRaw = $input->getOption('entity-id');
        $entityId = \is_numeric($entityIdRaw) ? (int) $entityIdRaw : null;

        $limitRaw = $input->getOption('limit');
        $limit = \is_numeric($limitRaw) ? (int) $limitRaw : 50;

        return [
            'type' => $input->getOption('type'),
            'operation' => $input->getOption('operation'),
            'entityType' => $input->getOption('entity-type'),
            'entityId' => $entityId,
            'level' => $input->getOption('level'),
            'limit' => $limit,
            'sinceInput' => $input->getOption('since'),
            'showStats' => (bool) $input->getOption('stats'),
            'cleanup' => $input->getOption('cleanup'),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function findHandler(array $options): QueryHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($options)) {
                return $handler;
            }
        }

        throw QueryHandlerNotFoundException::forOptions($options);
    }
}
