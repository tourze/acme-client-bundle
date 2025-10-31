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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Challenge>
 */
#[AdminCrud(
    routePath: '/acme/challenge',
    routeName: 'acme_challenge'
)]
final class ChallengeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Challenge::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ACME 质询')
            ->setEntityLabelInPlural('ACME 质询')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['challengeUrl', 'token', 'dnsRecordName'])
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
                    'DNS-01 质询' => ChallengeType::DNS_01->value,
                ])
            )
            ->add(ChoiceFilter::new('status')
                ->setChoices([
                    '待处理' => ChallengeStatus::PENDING->value,
                    '处理中' => ChallengeStatus::PROCESSING->value,
                    '有效' => ChallengeStatus::VALID->value,
                    '无效' => ChallengeStatus::INVALID->value,
                ])
            )
            ->add(BooleanFilter::new('valid'))
            ->add(DateTimeFilter::new('validatedTime'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setLabel('ID')->onlyOnIndex();

        yield AssociationField::new('authorization')
            ->setLabel('关联授权')
            ->setHelp('质询所属的 ACME 授权')
        ;

        yield UrlField::new('challengeUrl')
            ->setLabel('质询 URL')
            ->setHelp('质询在 ACME 服务器上的资源 URL')
        ;

        $typeField = EnumField::new('type')
            ->setLabel('质询类型')
        ;
        $typeField->setEnumCases(ChallengeType::cases());
        $typeField->setHelp('ACME 质询验证的类型');
        yield $typeField;

        $statusField = EnumField::new('status')
            ->setLabel('状态')
        ;
        $statusField->setEnumCases(ChallengeStatus::cases());
        $statusField->setHelp('质询验证的处理状态');
        yield $statusField;

        yield TextField::new('token')
            ->setLabel('质询令牌')
            ->setHelp('ACME 服务器提供的质询令牌')
        ;

        yield TextField::new('keyAuthorization')
            ->setLabel('密钥授权')
            ->setHelp('质询响应内容')
            ->hideOnIndex()
        ;

        yield TextField::new('dnsRecordName')
            ->setLabel('DNS 记录名称')
            ->setHelp('需要添加的 DNS 记录名称')
            ->hideOnIndex()
        ;

        yield TextField::new('dnsRecordValue')
            ->setLabel('DNS 记录值')
            ->setHelp('DNS 记录对应的值')
            ->hideOnIndex()
        ;

        yield BooleanField::new('valid')
            ->setLabel('有效状态')
            ->renderAsSwitch(false)
        ;

        yield DateTimeField::new('validatedTime')
            ->setLabel('验证时间')
            ->setHelp('质询通过验证的时间')
            ->hideOnIndex()
        ;

        yield ArrayField::new('error')
            ->setLabel('错误信息')
            ->setHelp('质询处理过程中的错误信息')
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
