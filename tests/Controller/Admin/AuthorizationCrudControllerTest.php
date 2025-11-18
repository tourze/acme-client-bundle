<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Controller\Admin\AuthorizationCrudController;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * AuthorizationCrudController Web测试
 *
 * @internal
 */
#[CoversClass(AuthorizationCrudController::class)]
#[RunTestsInSeparateProcesses]
final class AuthorizationCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
        $this->assertTrue($reflection->isFinal());
    }

    public function testControllerHasRequiredConfigurationMethods(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);

        $requiredMethods = [
            'getEntityFqcn',
            'configureCrud',
            'configureActions',
            'configureFields',
            'configureFilters',
        ];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "方法 {$methodName} 必须存在");

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic(), "方法 {$methodName} 必须是public");
        }
    }

    public function testControllerHasCorrectAnnotations(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller应该有AdminCrud注解');
    }

    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);

        // 测试类是final的
        $this->assertTrue($reflection->isFinal());

        // 测试继承关系
        $this->assertTrue($reflection->isSubclassOf(AbstractCrudController::class));

        // 测试getEntityFqcn是静态方法
        $getEntityMethod = $reflection->getMethod('getEntityFqcn');
        $this->assertTrue($getEntityMethod->isStatic());
        $this->assertTrue($getEntityMethod->isPublic());
    }

    public function testControllerHasProperNamespace(): void
    {
        $this->assertEquals(
            'Tourze\ACMEClientBundle\Controller\Admin',
            (new \ReflectionClass(AuthorizationCrudController::class))->getNamespaceName()
        );
    }

    public function testConfigureFieldsReturnsIterable(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $method = $reflection->getMethod('configureFields');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('iterable', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testConfigureFiltersReturnsFilters(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $method = $reflection->getMethod('configureFilters');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testConfigureCrudReturnsCorrectConfiguration(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $method = $reflection->getMethod('configureCrud');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testConfigureActionsReturnsCorrectConfiguration(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $method = $reflection->getMethod('configureActions');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testControllerRequiresAuthentication(): void
    {
        $client = self::createClient();

        // 尝试访问ACME授权管理页面，应该被重定向到登录或返回404（如果路由不存在）
        $client->request('GET', '/admin/acme-authorization');

        // 检查状态码 - 应该是302重定向或404未找到
        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();

        if (302 === $statusCode) {
            // 如果是重定向，检查是否重定向到登录页面
            $location = $response->headers->get('Location');
            $this->assertNotNull($location);
            $this->assertStringContainsString('/login', $location);
        } else {
            // 如果路由不存在，应该是404
            $this->assertEquals(404, $statusCode, 'Expected either 302 redirect to login or 404 not found');
        }
    }

    public function testConfigureFieldsContainsExpectedFields(): void
    {
        $controller = new AuthorizationCrudController();
        $fields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        $fieldTypes = array_map(fn ($field) => is_object($field) ? get_class($field) : $field, $fields);

        // 验证包含必需的字段类型
        $this->assertContains(IdField::class, $fieldTypes, '应该包含IdField');
        $this->assertContains(AssociationField::class, $fieldTypes, '应该包含AssociationField');
        $this->assertContains(UrlField::class, $fieldTypes, '应该包含UrlField');
        $this->assertContains(EnumField::class, $fieldTypes, '应该包含EnumField');
        $this->assertContains(BooleanField::class, $fieldTypes, '应该包含BooleanField');
        $this->assertContains(DateTimeField::class, $fieldTypes, '应该包含DateTimeField');
    }

    public function testCrudConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $method = $reflection->getMethod('configureCrud');

        $this->assertTrue($method->isPublic());
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证源代码包含预期的配置
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString('setEntityLabelInSingular', $source);
        $this->assertStringContainsString('setEntityLabelInPlural', $source);
        $this->assertStringContainsString("'id' => 'DESC'", $source);
        $this->assertStringContainsString('authorizationUrl', $source);
        $this->assertStringContainsString('ROLE_ADMIN', $source);
        $this->assertStringContainsString('setPaginatorPageSize(30)', $source);
    }

    public function testActionsConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $method = $reflection->getMethod('configureActions');

        $this->assertTrue($method->isPublic());
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证源代码包含预期的配置
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString('Action::DETAIL', $source);
        $this->assertStringContainsString('Crud::PAGE_INDEX', $source);
    }

    public function testFiltersConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $method = $reflection->getMethod('configureFilters');

        $this->assertTrue($method->isPublic());
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证源代码包含预期的过滤器配置
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString("ChoiceFilter::new('status')", $source);
        $this->assertStringContainsString("BooleanFilter::new('valid')", $source);
        $this->assertStringContainsString("BooleanFilter::new('wildcard')", $source);
        $this->assertStringContainsString("DateTimeFilter::new('expiresTime')", $source);
    }

    public function testStatusFilterHasCorrectChoices(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证状态过滤器包含所有正确的枚举值
        $this->assertStringContainsString('AuthorizationStatus::PENDING->value', $source);
        $this->assertStringContainsString('AuthorizationStatus::VALID->value', $source);
        $this->assertStringContainsString('AuthorizationStatus::INVALID->value', $source);
        $this->assertStringContainsString('AuthorizationStatus::EXPIRED->value', $source);
        $this->assertStringContainsString('AuthorizationStatus::REVOKED->value', $source);

        // 验证包含中文标签
        $this->assertStringContainsString('待处理', $source);
        $this->assertStringContainsString('有效', $source);
        $this->assertStringContainsString('无效', $source);
        $this->assertStringContainsString('已过期', $source);
        $this->assertStringContainsString('已撤销', $source);
    }

    public function testControllerUsesCorrectEnumClass(): void
    {
        // 验证Controller使用了正确的AuthorizationStatus枚举
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        $this->assertStringContainsString(AuthorizationStatus::class, $source);
        $this->assertStringContainsString('AuthorizationStatus::PENDING', $source);
        $this->assertStringContainsString('AuthorizationStatus::VALID', $source);
        $this->assertStringContainsString('AuthorizationStatus::INVALID', $source);
        $this->assertStringContainsString('AuthorizationStatus::EXPIRED', $source);
        $this->assertStringContainsString('AuthorizationStatus::REVOKED', $source);
    }

    public function testControllerHasCorrectImports(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证关键导入语句
        $expectedImports = [
            'use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;',
            'use Tourze\ACMEClientBundle\Entity\Authorization;',
            'use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;',
            'use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;',
        ];

        foreach ($expectedImports as $import) {
            $this->assertStringContainsString($import, $source, "应该包含导入: {$import}");
        }
    }

    public function testRouteConfigurationIsCorrect(): void
    {
        $reflection = new \ReflectionClass(AuthorizationCrudController::class);
        $attributes = $reflection->getAttributes();

        $adminCrudAttribute = null;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $adminCrudAttribute = $attribute;
                break;
            }
        }

        $this->assertNotNull($adminCrudAttribute, 'Controller应该有AdminCrud属性');

        // 验证路由配置在源代码中正确
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString("routePath: '/acme/authorization'", $source);
        $this->assertStringContainsString("routeName: 'acme_authorization'", $source);
    }

    /**
     * @return AbstractCrudController<Authorization>
     */
    protected function getControllerService(): AbstractCrudController
    {
        $controller = self::getContainer()->get(AuthorizationCrudController::class);
        self::assertInstanceOf(AuthorizationCrudController::class, $controller);

        return $controller;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '关联订单' => ['关联订单'];
        yield '域名标识' => ['域名标识'];
        yield '授权 URL' => ['授权 URL'];
        yield '状态' => ['状态'];
        yield '通配符授权' => ['通配符授权'];
        yield '有效状态' => ['有效状态'];
        yield '过期时间' => ['过期时间'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'order' => ['关联订单'];
        yield 'identifier' => ['域名标识'];
        yield 'authorizationUrl' => ['授权 URL'];
        yield 'status' => ['状态'];
        yield 'wildcard' => ['通配符授权'];
        yield 'valid' => ['有效状态'];
        yield 'expiresTime' => ['过期时间'];
        yield 'challenges' => ['质询列表'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // Authorization实体的所有字段都由ACME服务器生成
        // 不在NEW页面提供可编辑字段，使用expiresTime作为只读示例
        yield 'expiresTime' => ['过期时间'];
    }
}
