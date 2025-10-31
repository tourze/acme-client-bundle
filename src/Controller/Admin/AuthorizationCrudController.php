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
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Authorization>
 */
#[AdminCrud(
    routePath: '/acme/authorization',
    routeName: 'acme_authorization'
)]
final class AuthorizationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Authorization::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 授权')
            ->setEntityLabelInPlural('ACME 授权')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['authorizationUrl'])
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
                    '待处理' => AuthorizationStatus::PENDING->value,
                    '有效' => AuthorizationStatus::VALID->value,
                    '无效' => AuthorizationStatus::INVALID->value,
                    '已过期' => AuthorizationStatus::EXPIRED->value,
                    '已撤销' => AuthorizationStatus::REVOKED->value,
                ])
            )
            ->add(BooleanFilter::new('valid'))
            ->add(BooleanFilter::new('wildcard'))
            ->add(DateTimeFilter::new('expiresTime'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setLabel('ID')->onlyOnIndex();

        yield AssociationField::new('order')
            ->setLabel('关联订单')
            ->setHelp('授权所属的 ACME 订单')
        ;

        yield AssociationField::new('identifier')
            ->setLabel('域名标识')
            ->setHelp('授权对应的域名标识符')
        ;

        yield UrlField::new('authorizationUrl')
            ->setLabel('授权 URL')
            ->setHelp('授权在 ACME 服务器上的资源 URL')
        ;

        $statusField = EnumField::new('status')
            ->setLabel('状态')
        ;
        $statusField->setEnumCases(AuthorizationStatus::cases());
        $statusField->setHelp('授权在 ACME 服务器上的状态');
        yield $statusField;

        yield BooleanField::new('wildcard')
            ->setLabel('通配符授权')
            ->setHelp('是否为通配符域名授权')
            ->renderAsSwitch(false)
        ;

        yield BooleanField::new('valid')
            ->setLabel('有效状态')
            ->renderAsSwitch(false)
        ;

        yield DateTimeField::new('expiresTime')
            ->setLabel('过期时间')
            ->setHelp('授权的到期时间')
        ;

        yield AssociationField::new('challenges')
            ->setLabel('质询列表')
            ->setHelp('与此授权相关的质询')
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
