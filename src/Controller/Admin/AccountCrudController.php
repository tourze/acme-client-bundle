<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Account>
 */
#[AdminCrud(
    routePath: '/acme/account',
    routeName: 'acme_account'
)]
final class AccountCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Account::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 账户')
            ->setEntityLabelInPlural('ACME 账户')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['acmeServerUrl', 'accountUrl', 'contacts'])
            ->setPaginatorPageSize(30)
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')
                ->setChoices([
                    '待处理' => AccountStatus::PENDING->value,
                    '有效' => AccountStatus::VALID->value,
                    '已停用' => AccountStatus::DEACTIVATED->value,
                ])
            )
            ->add(BooleanFilter::new('valid'))
            ->add(BooleanFilter::new('termsOfServiceAgreed'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->setLabel('ID')
            ->onlyOnIndex()
        ;

        yield UrlField::new('acmeServerUrl')
            ->setLabel('ACME 服务器 URL')
            ->setHelp('ACME 服务提供商的服务器地址')
        ;

        yield UrlField::new('accountUrl')
            ->setLabel('账户 URL')
            ->setHelp('账户在 ACME 服务器上的资源 URL')
            ->hideOnIndex()
        ;

        $statusField = EnumField::new('status')
            ->setLabel('状态')
        ;
        $statusField->setEnumCases(AccountStatus::cases());
        $statusField->setHelp('账户在 ACME 服务器上的状态');
        yield $statusField;

        yield ArrayField::new('contacts')
            ->setLabel('联系信息')
            ->setHelp('账户关联的邮箱等联系方式')
            ->hideOnIndex()
        ;

        yield BooleanField::new('termsOfServiceAgreed')
            ->setLabel('已同意服务条款')
            ->renderAsSwitch(false)
        ;

        yield BooleanField::new('valid')
            ->setLabel('账户有效')
            ->renderAsSwitch(false)
        ;

        yield TextareaField::new('privateKey')
            ->setLabel('私钥')
            ->setHelp('账户私钥（加密存储）')
            ->hideOnIndex()
            ->setMaxLength(65535)
        ;

        yield TextareaField::new('publicKeyJwk')
            ->setLabel('公钥 JWK')
            ->setHelp('账户公钥的 JWK JSON 格式')
            ->hideOnIndex()
            ->setMaxLength(65535)
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
