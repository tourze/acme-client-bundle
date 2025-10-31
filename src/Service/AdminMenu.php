<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * ACME 客户端菜单服务
 */
#[Autoconfigure(public: true)]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('SSL 证书')) {
            $item->addChild('SSL 证书');
        }

        $sslMenu = $item->getChild('SSL 证书');

        if (null !== $sslMenu) {
            $sslMenu->addChild('ACME 账户')
                ->setUri($this->linkGenerator->getCurdListPage(Account::class))
                ->setAttribute('icon', 'fas fa-user-cog')
                ->setAttribute('description', '管理 ACME 服务提供商账户')
            ;

            $sslMenu->addChild('SSL 证书')
                ->setUri($this->linkGenerator->getCurdListPage(Certificate::class))
                ->setAttribute('icon', 'fas fa-certificate')
                ->setAttribute('description', '管理已签发的 SSL 证书')
            ;

            $sslMenu->addChild('证书订单')
                ->setUri($this->linkGenerator->getCurdListPage(Order::class))
                ->setAttribute('icon', 'fas fa-shopping-cart')
                ->setAttribute('description', '管理证书申请订单')
            ;

            $sslMenu->addChild('域名授权')
                ->setUri($this->linkGenerator->getCurdListPage(Authorization::class))
                ->setAttribute('icon', 'fas fa-shield-alt')
                ->setAttribute('description', '管理域名授权验证')
            ;

            $sslMenu->addChild('验证质询')
                ->setUri($this->linkGenerator->getCurdListPage(Challenge::class))
                ->setAttribute('icon', 'fas fa-tasks')
                ->setAttribute('description', '管理域名验证质询')
            ;

            $sslMenu->addChild('域名标识')
                ->setUri($this->linkGenerator->getCurdListPage(Identifier::class))
                ->setAttribute('icon', 'fas fa-tag')
                ->setAttribute('description', '管理域名标识符')
            ;

            if (null === $sslMenu->getChild('日志管理')) {
                $sslMenu->addChild('日志管理');
            }

            $logMenu = $sslMenu->getChild('日志管理');

            if (null !== $logMenu) {
                $logMenu->addChild('操作日志')
                    ->setUri($this->linkGenerator->getCurdListPage(AcmeOperationLog::class))
                    ->setAttribute('icon', 'fas fa-list-alt')
                    ->setAttribute('description', '查看 ACME 操作日志')
                ;

                $logMenu->addChild('异常日志')
                    ->setUri($this->linkGenerator->getCurdListPage(AcmeExceptionLog::class))
                    ->setAttribute('icon', 'fas fa-exclamation-triangle')
                    ->setAttribute('description', '查看 ACME 异常日志')
                ;
            }
        }
    }
}
