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
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;

/**
 * @extends AbstractCrudController<AcmeExceptionLog>
 */
#[AdminCrud(
    routePath: '/acme/exception-log',
    routeName: 'acme_exception_log'
)]
final class AcmeExceptionLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AcmeExceptionLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 异常日志')
            ->setEntityLabelInPlural('ACME 异常日志')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['exceptionClass', 'message', 'entityType'])
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
            ->add(ChoiceFilter::new('exceptionClass')
                ->setChoices([
                    'AcmeOperationException' => 'Tourze\ACMEClientBundle\Exception\AcmeOperationException',
                    'AcmeAccountException' => 'Tourze\ACMEClientBundle\Exception\AcmeAccountException',
                    'AcmeChallengeException' => 'Tourze\ACMEClientBundle\Exception\AcmeChallengeException',
                    'AcmeCertificateException' => 'Tourze\ACMEClientBundle\Exception\AcmeCertificateException',
                    'AcmeOrderException' => 'Tourze\ACMEClientBundle\Exception\AcmeOrderException',
                    'AcmeAuthorizationException' => 'Tourze\ACMEClientBundle\Exception\AcmeAuthorizationException',
                    'RuntimeException' => 'RuntimeException',
                    'InvalidArgumentException' => 'InvalidArgumentException',
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
            ->add(NumericFilter::new('code'))
            ->add(NumericFilter::new('httpStatusCode'))
            ->add(DateTimeFilter::new('createTime'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->setLabel('ID')
            ->onlyOnIndex()
        ;

        yield TextField::new('exceptionClass')
            ->setLabel('异常类')
            ->setHelp('发生异常的类名')
        ;

        yield TextareaField::new('message')
            ->setLabel('异常消息')
            ->setHelp('异常的详细消息内容')
            ->setMaxLength(65535)
        ;

        yield IntegerField::new('code')
            ->setLabel('异常代码')
            ->setHelp('异常错误代码')
        ;

        yield TextField::new('file')
            ->setLabel('发生文件')
            ->setHelp('异常发生的文件路径')
            ->hideOnIndex()
        ;

        yield IntegerField::new('line')
            ->setLabel('发生行号')
            ->setHelp('异常发生的代码行号')
            ->hideOnIndex()
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
            ->setHelp('引发异常的 HTTP 请求 URL')
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
            ->hideOnIndex()
        ;

        yield IntegerField::new('httpStatusCode')
            ->setLabel('HTTP 状态码')
            ->setHelp('HTTP 响应状态码')
            ->hideOnIndex()
        ;

        yield TextareaField::new('stackTrace')
            ->setLabel('堆栈跟踪')
            ->setHelp('异常的堆栈跟踪信息')
            ->hideOnIndex()
            ->setMaxLength(65535)
        ;

        yield ArrayField::new('context')
            ->setLabel('上下文信息')
            ->setHelp('异常相关的详细上下文数据')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime')
            ->setLabel('创建时间')
            ->hideOnForm()
        ;
    }
}
