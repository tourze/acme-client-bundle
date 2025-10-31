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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Controller\Admin\OrderCrudController;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * OrderCrudController Web测试
 *
 * @internal
 */
#[CoversClass(OrderCrudController::class)]
#[RunTestsInSeparateProcesses]
final class OrderCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
        $this->assertTrue($reflection->isFinal());
    }

    public function testGetEntityFqcnShouldReturnOrderClass(): void
    {
        $this->assertEquals(Order::class, OrderCrudController::getEntityFqcn());
    }

    public function testControllerHasRequiredConfigurationMethods(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);

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
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;

                // 验证AdminCrud注解的参数
                $arguments = $attribute->getArguments();
                $this->assertArrayHasKey('routePath', $arguments);
                $this->assertEquals('/acme/order', $arguments['routePath']);
                $this->assertArrayHasKey('routeName', $arguments);
                $this->assertEquals('acme_order', $arguments['routeName']);
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller应该有AdminCrud注解');
    }

    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);

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
            (new \ReflectionClass(OrderCrudController::class))->getNamespaceName()
        );
    }

    public function testConfigureFieldsReturnsIterable(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $method = $reflection->getMethod('configureFields');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('iterable', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('pageName', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals('string', $paramType->getName());
    }

    public function testConfigureFiltersReturnsFilters(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $method = $reflection->getMethod('configureFilters');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testConfigureCrudReturnsCorrectConfiguration(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $method = $reflection->getMethod('configureCrud');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testConfigureActionsReturnsCorrectConfiguration(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $method = $reflection->getMethod('configureActions');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testControllerRequiresAuthentication(): void
    {
        $client = self::createClient();

        // 尝试访问ACME订单管理页面，应该被重定向到登录或返回404
        $client->request('GET', '/acme/order');

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
        $controller = new OrderCrudController();
        $fields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        $fieldTypes = array_map(fn ($field) => is_object($field) ? get_class($field) : $field, $fields);

        // 验证包含必需的字段类型
        $this->assertContains(IdField::class, $fieldTypes, '应该包含IdField');
        $this->assertContains(AssociationField::class, $fieldTypes, '应该包含AssociationField');
        $this->assertContains(UrlField::class, $fieldTypes, '应该包含UrlField');
        $this->assertContains(EnumField::class, $fieldTypes, '应该包含EnumField');
        $this->assertContains(BooleanField::class, $fieldTypes, '应该包含BooleanField');
        $this->assertContains(DateTimeField::class, $fieldTypes, '应该包含DateTimeField');
        $this->assertContains(TextareaField::class, $fieldTypes, '应该包含TextareaField');
    }

    public function testCrudConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
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
        $this->assertStringContainsString('ROLE_ADMIN', $source);
        $this->assertStringContainsString('setPaginatorPageSize(30)', $source);
        $this->assertStringContainsString('ACME 订单', $source);
        $this->assertStringContainsString("'orderUrl', 'finalizeUrl', 'certificateUrl'", $source);
    }

    public function testActionsConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
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
        $reflection = new \ReflectionClass(OrderCrudController::class);
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
        $this->assertStringContainsString("DateTimeFilter::new('expiresTime')", $source);
    }

    public function testStatusFilterHasCorrectChoices(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证状态过滤器包含所有正确的枚举值
        $this->assertStringContainsString('OrderStatus::PENDING->value', $source);
        $this->assertStringContainsString('OrderStatus::READY->value', $source);
        $this->assertStringContainsString('OrderStatus::PROCESSING->value', $source);
        $this->assertStringContainsString('OrderStatus::VALID->value', $source);
        $this->assertStringContainsString('OrderStatus::INVALID->value', $source);

        // 验证包含中文标签
        $this->assertStringContainsString('待处理', $source);
        $this->assertStringContainsString('准备就绪', $source);
        $this->assertStringContainsString('处理中', $source);
        $this->assertStringContainsString('有效', $source);
        $this->assertStringContainsString('无效', $source);
    }

    public function testControllerUsesCorrectEnumClass(): void
    {
        // 验证Controller使用了正确的OrderStatus枚举
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        $this->assertStringContainsString(OrderStatus::class, $source);
        $this->assertStringContainsString('OrderStatus::PENDING', $source);
        $this->assertStringContainsString('OrderStatus::READY', $source);
        $this->assertStringContainsString('OrderStatus::PROCESSING', $source);
        $this->assertStringContainsString('OrderStatus::VALID', $source);
        $this->assertStringContainsString('OrderStatus::INVALID', $source);
    }

    public function testControllerHasCorrectImports(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证关键导入语句
        $expectedImports = [
            'use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;',
            'use Tourze\ACMEClientBundle\Entity\Order;',
            'use Tourze\ACMEClientBundle\Enum\OrderStatus;',
            'use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;',
            'use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;',
            'use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;',
        ];

        foreach ($expectedImports as $import) {
            $this->assertStringContainsString($import, $source, "应该包含导入: {$import}");
        }
    }

    public function testRouteConfigurationIsCorrect(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
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
        $this->assertStringContainsString("routePath: '/acme/order'", $source);
        $this->assertStringContainsString("routeName: 'acme_order'", $source);
    }

    public function testControllerUsesCorrectEntityClass(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        $this->assertStringContainsString('Order::class', $source);
        $this->assertStringContainsString('use Tourze\ACMEClientBundle\Entity\Order;', $source);
    }

    public function testControllerHasCorrectPhpDocComment(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment, 'Controller应该有PHPDoc注释');
        $this->assertStringContainsString('@extends AbstractCrudController<Order>', $docComment);
    }

    public function testControllerClassModifiers(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);

        // 应该是final类
        $this->assertTrue($reflection->isFinal(), 'Controller类应该是final的');

        // 不应该是abstract类
        $this->assertFalse($reflection->isAbstract(), 'Controller类不应该是abstract的');

        // 应该是public类
        $this->assertTrue($reflection->isInstantiable(), 'Controller类应该是可实例化的');
    }

    public function testControllerStrictTypesDeclaration(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $source, 'Controller应该声明严格类型');
    }

    public function testControllerEntityRelationship(): void
    {
        // 验证Controller返回的Entity类存在且正确
        $entityClass = OrderCrudController::getEntityFqcn();
        $this->assertEquals(Order::class, $entityClass);

        // 验证Entity类是可实例化的
        $entityReflection = new \ReflectionClass($entityClass);
        $this->assertTrue($entityReflection->isInstantiable(), 'Entity类必须可实例化');
    }

    public function testControllerMethodsReturnCorrectTypes(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);

        // getEntityFqcn 应该返回string
        $getEntityMethod = $reflection->getMethod('getEntityFqcn');
        $returnType = $getEntityMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureCrud 应该返回Crud
        $configureCrudMethod = $reflection->getMethod('configureCrud');
        $returnType = $configureCrudMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureActions 应该返回Actions
        $configureActionsMethod = $reflection->getMethod('configureActions');
        $returnType = $configureActionsMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureFilters 应该返回Filters
        $configureFiltersMethod = $reflection->getMethod('configureFilters');
        $returnType = $configureFiltersMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureFields 应该返回iterable
        $configureFieldsMethod = $reflection->getMethod('configureFields');
        $returnType = $configureFieldsMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('iterable', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testControllerHasExpectedFieldsConfiguration(): void
    {
        // 通过反射检查Controller中是否引用了正确的Field类型
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Field类
        $expectedFields = [
            'IdField',
            'AssociationField',
            'UrlField',
            'EnumField',
            'BooleanField',
            'DateTimeField',
            'TextareaField',
        ];

        foreach ($expectedFields as $fieldType) {
            $this->assertStringContainsString(
                $fieldType,
                $source,
                "Controller应该使用{$fieldType}字段类型"
            );
        }
    }

    public function testControllerHasExpectedFiltersConfiguration(): void
    {
        // 通过反射检查Controller中是否引用了正确的Filter类型
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Filter类
        $expectedFilters = [
            'ChoiceFilter',
            'BooleanFilter',
            'DateTimeFilter',
        ];

        foreach ($expectedFilters as $filterType) {
            $this->assertStringContainsString(
                $filterType,
                $source,
                "Controller应该使用{$filterType}过滤器类型"
            );
        }
    }

    public function testEnumFieldConfigurationIsCorrect(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证EnumField使用了正确的配置
        $this->assertStringContainsString("EnumField::new('status')", $source);
        $this->assertStringContainsString('setEnumCases(OrderStatus::cases())', $source);
    }

    public function testBooleanFieldConfigurationIsCorrect(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证BooleanField使用了正确的配置
        $this->assertStringContainsString("BooleanField::new('valid')", $source);
        $this->assertStringContainsString('renderAsSwitch(false)', $source);
    }

    public function testAssociationFieldsConfiguration(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证关联字段配置
        $this->assertStringContainsString("AssociationField::new('account')", $source);
        $this->assertStringContainsString("AssociationField::new('orderIdentifiers')", $source);
        $this->assertStringContainsString("AssociationField::new('authorizations')", $source);
        $this->assertStringContainsString("AssociationField::new('certificates')", $source);
    }

    public function testFieldLabelsAreInChinese(): void
    {
        $reflection = new \ReflectionClass(OrderCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证字段标签使用中文
        $this->assertStringContainsString('ACME 账户', $source);
        $this->assertStringContainsString('订单 URL', $source);
        $this->assertStringContainsString('状态', $source);
        $this->assertStringContainsString('有效状态', $source);
        $this->assertStringContainsString('过期时间', $source);
        $this->assertStringContainsString('终结 URL', $source);
        $this->assertStringContainsString('证书下载 URL', $source);
        $this->assertStringContainsString('错误信息', $source);
        $this->assertStringContainsString('域名标识', $source);
        $this->assertStringContainsString('授权列表', $source);
        $this->assertStringContainsString('证书列表', $source);
        $this->assertStringContainsString('创建时间', $source);
        $this->assertStringContainsString('更新时间', $source);
    }

    /**
     * @return AbstractCrudController<Order>
     */
    protected function getControllerService(): AbstractCrudController
    {
        $controller = self::getContainer()->get(OrderCrudController::class);
        self::assertInstanceOf(OrderCrudController::class, $controller);

        return $controller;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'ACME 账户' => ['ACME 账户'];
        yield '订单 URL' => ['订单 URL'];
        yield '状态' => ['状态'];
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
        yield 'account' => ['ACME 账户'];
        yield 'orderUrl' => ['订单 URL'];
        yield 'status' => ['状态'];
        yield 'valid' => ['有效状态'];
        yield 'expiresTime' => ['过期时间'];
        yield 'finalizeUrl' => ['终结 URL'];
        yield 'certificateUrl' => ['证书下载 URL'];
        yield 'error' => ['错误信息'];
        yield 'orderIdentifiers' => ['域名标识'];
        yield 'authorizations' => ['授权列表'];
        yield 'certificates' => ['证书列表'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // AssociationField、服务器生成的URL和某些EnumField在NEW页面可能不显示
        // 仅测试确定能正常渲染的字段
        yield 'valid' => ['有效状态'];
        yield 'expiresTime' => ['过期时间'];
        yield 'error' => ['错误信息'];
    }
}
