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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Tourze\ACMEClientBundle\Entity\Identifier;

/**
 * @extends AbstractCrudController<Identifier>
 */
#[AdminCrud(
    routePath: '/acme/identifier',
    routeName: 'acme_identifier'
)]
final class IdentifierCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Identifier::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 标识符')
            ->setEntityLabelInPlural('ACME 标识符')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['value'])
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
            ->add(ChoiceFilter::new('type')
                ->setChoices([
                    'DNS' => 'dns',
                ])
            )
            ->add(BooleanFilter::new('wildcard'))
            ->add(BooleanFilter::new('valid'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setLabel('ID')->onlyOnIndex();

        yield AssociationField::new('order')
            ->setLabel('关联订单')
            ->setHelp('标识符所属的 ACME 订单')
        ;

        yield ChoiceField::new('type')
            ->setLabel('类型')
            ->setChoices([
                'DNS' => 'dns',
            ])
        ;

        yield TextField::new('value')
            ->setLabel('标识符值')
            ->setHelp('域名或其他标识符值')
        ;

        yield BooleanField::new('wildcard')
            ->setLabel('通配符域名')
            ->setHelp('是否为通配符域名（*.example.com）')
            ->renderAsSwitch(false)
        ;

        yield BooleanField::new('valid')
            ->setLabel('有效状态')
            ->renderAsSwitch(false)
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
