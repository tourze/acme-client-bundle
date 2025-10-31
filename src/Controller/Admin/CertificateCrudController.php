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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Certificate>
 */
#[AdminCrud(
    routePath: '/acme/certificate',
    routeName: 'acme_certificate'
)]
final class CertificateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Certificate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 证书')
            ->setEntityLabelInPlural('ACME 证书')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['domains', 'serialNumber', 'fingerprint', 'issuer'])
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
                    '有效' => CertificateStatus::VALID->value,
                    '已过期' => CertificateStatus::EXPIRED->value,
                    '已撤销' => CertificateStatus::REVOKED->value,
                ])
            )
            ->add(BooleanFilter::new('valid'))
            ->add(DateTimeFilter::new('notAfterTime'))
            ->add(DateTimeFilter::new('issuedTime'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setLabel('ID')->onlyOnIndex();

        yield AssociationField::new('order')
            ->setLabel('关联订单')
            ->setHelp('证书所属的 ACME 订单')
        ;

        $statusField = EnumField::new('status')
            ->setLabel('状态')
        ;
        $statusField->setEnumCases(CertificateStatus::cases());
        $statusField->setHelp('证书的当前状态');
        yield $statusField;

        yield ArrayField::new('domains')
            ->setLabel('域名列表')
            ->setHelp('证书包含的所有域名')
        ;

        yield TextField::new('serialNumber')
            ->setLabel('序列号')
            ->setHelp('证书序列号')
            ->hideOnIndex()
        ;

        yield TextField::new('fingerprint')
            ->setLabel('指纹')
            ->setHelp('证书 SHA256 指纹')
            ->hideOnIndex()
        ;

        yield TextField::new('issuer')
            ->setLabel('颁发机构')
            ->setHelp('证书颁发机构信息')
            ->hideOnIndex()
        ;

        yield BooleanField::new('valid')
            ->setLabel('有效状态')
            ->renderAsSwitch(false)
        ;

        yield DateTimeField::new('notBeforeTime')
            ->setLabel('生效时间')
            ->setHelp('证书开始生效的时间')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('notAfterTime')
            ->setLabel('过期时间')
            ->setHelp('证书失效的时间')
        ;

        yield DateTimeField::new('issuedTime')
            ->setLabel('签发时间')
            ->setHelp('证书实际签发的时间')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('revokedTime')
            ->setLabel('撤销时间')
            ->setHelp('证书被撤销的时间（如有）')
            ->hideOnIndex()
        ;

        yield TextareaField::new('certificatePem')
            ->setLabel('证书内容')
            ->setHelp('PEM 格式的证书内容')
            ->hideOnIndex()
            ->setMaxLength(65535)
        ;

        yield TextareaField::new('certificateChainPem')
            ->setLabel('证书链')
            ->setHelp('PEM 格式的证书链')
            ->hideOnIndex()
            ->setMaxLength(65535)
        ;

        yield TextareaField::new('privateKeyPem')
            ->setLabel('私钥')
            ->setHelp('PEM 格式的私钥（敏感信息）')
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
