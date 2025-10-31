<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Order>
 */
#[AdminCrud(
    routePath: '/acme/order',
    routeName: 'acme_order'
)]
final class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 订单')
            ->setEntityLabelInPlural('ACME 订单')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['orderUrl', 'finalizeUrl', 'certificateUrl'])
            ->setPaginatorPageSize(30)
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')
                ->setChoices([
                    '待处理' => OrderStatus::PENDING->value,
                    '准备就绪' => OrderStatus::READY->value,
                    '处理中' => OrderStatus::PROCESSING->value,
                    '有效' => OrderStatus::VALID->value,
                    '无效' => OrderStatus::INVALID->value,
                ])
            )
            ->add(BooleanFilter::new('valid'))
            ->add(DateTimeFilter::new('expiresTime'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setLabel('ID')->onlyOnIndex();

        yield AssociationField::new('account')
            ->setLabel('ACME 账户')
            ->setHelp('订单所属的 ACME 账户')
        ;

        yield UrlField::new('orderUrl')
            ->setLabel('订单 URL')
            ->setHelp('订单在 ACME 服务器上的资源 URL')
        ;

        $statusField = EnumField::new('status')
            ->setLabel('状态')
        ;
        $statusField->setEnumCases(OrderStatus::cases());
        $statusField->setHelp('订单在 ACME 服务器上的处理状态');
        yield $statusField;

        yield BooleanField::new('valid')
            ->setLabel('有效状态')
            ->renderAsSwitch(false)
        ;

        yield DateTimeField::new('expiresTime')
            ->setLabel('过期时间')
            ->setHelp('订单的到期时间')
        ;

        yield UrlField::new('finalizeUrl')
            ->setLabel('终结 URL')
            ->setHelp('完成订单流程的 URL')
            ->hideOnIndex()
        ;

        yield UrlField::new('certificateUrl')
            ->setLabel('证书下载 URL')
            ->setHelp('证书下载地址')
            ->hideOnIndex()
        ;

        yield TextareaField::new('error')
            ->setLabel('错误信息')
            ->setHelp('订单处理过程中的错误信息')
            ->hideOnIndex()
            ->setMaxLength(65535)
        ;

        yield AssociationField::new('orderIdentifiers')
            ->setLabel('域名标识')
            ->setHelp('订单包含的域名标识列表')
            ->hideOnIndex()
        ;

        yield AssociationField::new('authorizations')
            ->setLabel('授权列表')
            ->setHelp('订单相关的域名授权')
            ->hideOnIndex()
        ;

        yield AssociationField::new('certificates')
            ->setLabel('证书列表')
            ->setHelp('订单生成的证书')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime')
            ->setLabel('创建时间')
            ->hideOnForm()
        ;

        yield DateTimeField::new('updateTime')
            ->setLabel('更新时间')
            ->hideOnForm()
        ;
    }
}
