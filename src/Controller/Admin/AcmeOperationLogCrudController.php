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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;

/**
 * @extends AbstractCrudController<AcmeOperationLog>
 */
#[AdminCrud(
    routePath: '/acme/operation-log',
    routeName: 'acme_operation_log'
)]
final class AcmeOperationLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AcmeOperationLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 操作日志')
            ->setEntityLabelInPlural('ACME 操作日志')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['operation', 'message', 'entityType'])
            ->setPaginatorPageSize(50)
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
            ->add(ChoiceFilter::new('level')
                ->setChoices([
                    'Debug' => LogLevel::DEBUG->value,
                    'Info' => LogLevel::INFO->value,
                    'Warning' => LogLevel::WARNING->value,
                    'Error' => LogLevel::ERROR->value,
                ])
            )
            ->add(ChoiceFilter::new('httpMethod')
                ->setChoices([
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'PATCH' => 'PATCH',
                    'DELETE' => 'DELETE',
                ])
            )
            ->add(NumericFilter::new('httpStatusCode'))
            ->add(DateTimeFilter::new('createTime'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setLabel('ID')->onlyOnIndex();

        yield ChoiceField::new('level')
            ->setLabel('日志级别')
            ->setChoices([
                'Debug' => LogLevel::DEBUG,
                'Info' => LogLevel::INFO,
                'Warning' => LogLevel::WARNING,
                'Error' => LogLevel::ERROR,
            ])
            ->renderAsBadges()
        ;

        yield TextField::new('operation')
            ->setLabel('操作类型')
            ->setHelp('执行的 ACME 操作类型')
        ;

        yield TextareaField::new('message')
            ->setLabel('操作描述')
            ->setHelp('详细的操作描述信息')
            ->setMaxLength(65535)
        ;

        yield TextField::new('entityType')
            ->setLabel('实体类型')
            ->setHelp('相关联的实体类型')
            ->hideOnIndex()
        ;

        yield IntegerField::new('entityId')
            ->setLabel('实体 ID')
            ->setHelp('相关联的实体 ID')
            ->hideOnIndex()
        ;

        yield UrlField::new('httpUrl')
            ->setLabel('HTTP URL')
            ->setHelp('ACME API 请求 URL')
            ->hideOnIndex()
        ;

        yield ChoiceField::new('httpMethod')
            ->setLabel('HTTP 方法')
            ->setChoices([
                'GET' => 'GET',
                'POST' => 'POST',
                'PUT' => 'PUT',
                'PATCH' => 'PATCH',
                'DELETE' => 'DELETE',
                'HEAD' => 'HEAD',
                'OPTIONS' => 'OPTIONS',
            ])
            ->renderAsBadges()
            ->hideOnIndex()
        ;

        yield IntegerField::new('httpStatusCode')
            ->setLabel('HTTP 状态码')
            ->setHelp('ACME API 响应状态码')
            ->hideOnIndex()
        ;

        yield IntegerField::new('durationMs')
            ->setLabel('执行耗时')
            ->setHelp('操作执行耗时（毫秒）')
            ->hideOnIndex()
        ;

        yield ArrayField::new('context')
            ->setLabel('上下文信息')
            ->setHelp('操作相关的详细上下文数据')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime')
            ->setLabel('创建时间')
            ->hideOnForm()
        ;
    }
}
