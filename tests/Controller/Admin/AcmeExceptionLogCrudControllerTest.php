<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Controller\Admin\AcmeExceptionLogCrudController;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * AcmeExceptionLogCrudController Web测试
 *
 * @internal
 */
#[CoversClass(AcmeExceptionLogCrudController::class)]
#[RunTestsInSeparateProcesses]
final class AcmeExceptionLogCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
        $this->assertTrue($reflection->isFinal());
    }

    public function testControllerHasRequiredConfigurationMethods(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);

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
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;

                // 验证AdminCrud注解的参数
                $arguments = $attribute->getArguments();
                $this->assertArrayHasKey('routePath', $arguments);
                $this->assertEquals('/acme/exception-log', $arguments['routePath']);
                $this->assertArrayHasKey('routeName', $arguments);
                $this->assertEquals('acme_exception_log', $arguments['routeName']);
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller应该有AdminCrud注解');
    }

    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);

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
            (new \ReflectionClass(AcmeExceptionLogCrudController::class))->getNamespaceName()
        );
    }

    public function testConfigureFieldsReturnsIterable(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
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
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $method = $reflection->getMethod('configureFilters');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testConfigureCrudReturnsCorrectConfiguration(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $method = $reflection->getMethod('configureCrud');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testConfigureActionsReturnsCorrectConfiguration(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $method = $reflection->getMethod('configureActions');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testControllerRequiresAuthentication(): void
    {
        $client = self::createClient();

        // 尝试访问ACME异常日志管理页面，应该被重定向到登录或返回404
        $client->request('GET', '/admin/acme-exception-log');

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

    public function testCrudConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
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
        $this->assertStringContainsString('setPaginatorPageSize(50)', $source);
        $this->assertStringContainsString('ACME 异常日志', $source);
    }

    public function testActionsConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
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
        $this->assertStringContainsString('disable(Action::NEW, Action::EDIT, Action::DELETE)', $source);
    }

    public function testFiltersConfigurationMethodExists(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
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
        $this->assertStringContainsString("ChoiceFilter::new('exceptionClass')", $source);
        $this->assertStringContainsString("ChoiceFilter::new('httpMethod')", $source);
        $this->assertStringContainsString("NumericFilter::new('code')", $source);
        $this->assertStringContainsString("NumericFilter::new('httpStatusCode')", $source);
        $this->assertStringContainsString("DateTimeFilter::new('createTime')", $source);
    }

    public function testHttpMethodFilterHasCorrectChoices(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证HTTP方法过滤器包含所有正确的选项
        $expectedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($expectedMethods as $method) {
            $this->assertStringContainsString("'{$method}' => '{$method}'", $source, "应该包含HTTP方法: {$method}");
        }
    }

    public function testControllerHasCorrectImports(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证关键导入语句
        $expectedImports = [
            'use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;',
            'use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;',
            'use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;',
            'use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;',
            'use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;',
        ];

        foreach ($expectedImports as $import) {
            $this->assertStringContainsString($import, $source, "应该包含导入: {$import}");
        }
    }

    public function testRouteConfigurationIsCorrect(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
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
        $this->assertStringContainsString("routePath: '/acme/exception-log'", $source);
        $this->assertStringContainsString("routeName: 'acme_exception_log'", $source);
    }

    public function testControllerUsesCorrectEntityClass(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        $this->assertStringContainsString('AcmeExceptionLog::class', $source);
        $this->assertStringContainsString('use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;', $source);
    }

    public function testControllerHasCorrectPhpDocComment(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment, 'Controller应该有PHPDoc注释');
        $this->assertStringContainsString('@extends AbstractCrudController<AcmeExceptionLog>', $docComment);
    }

    public function testControllerClassModifiers(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);

        // 应该是final类
        $this->assertTrue($reflection->isFinal(), 'Controller类应该是final的');

        // 不应该是abstract类
        $this->assertFalse($reflection->isAbstract(), 'Controller类不应该是abstract的');

        // 应该是public类
        $this->assertTrue($reflection->isInstantiable(), 'Controller类应该是可实例化的');
    }

    public function testControllerStrictTypesDeclaration(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $source, 'Controller应该声明严格类型');
    }

    public function testControllerEntityRelationship(): void
    {
        // 验证Controller返回的Entity类存在且正确
        $entityClass = AcmeExceptionLogCrudController::getEntityFqcn();
        $this->assertEquals(AcmeExceptionLog::class, $entityClass);

        // 验证Entity类是可实例化的
        $entityReflection = new \ReflectionClass($entityClass);
        $this->assertTrue($entityReflection->isInstantiable(), 'Entity类必须可实例化');
    }

    public function testControllerMethodsReturnCorrectTypes(): void
    {
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);

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
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Field类
        $expectedFields = [
            'IdField',
            'TextField',
            'TextareaField',
            'IntegerField',
            'ChoiceField',
            'UrlField',
            'DateTimeField',
            'ArrayField',
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
        $reflection = new \ReflectionClass(AcmeExceptionLogCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Filter类
        $expectedFilters = [
            'ChoiceFilter',
            'NumericFilter',
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

    /**
     * @return AbstractCrudController<AcmeExceptionLog>
     */
    protected function getControllerService(): AbstractCrudController
    {
        $controller = self::getContainer()->get(AcmeExceptionLogCrudController::class);
        self::assertInstanceOf(AcmeExceptionLogCrudController::class, $controller);

        return $controller;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '异常类' => ['异常类'];
        yield '异常消息' => ['异常消息'];
        yield '异常代码' => ['异常代码'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * NEW 操作在此控制器中被禁用，测试将被跳过
     * 提供一个占位符避免PHPUnit的空数据集错误，测试会检查动作是否启用并跳过
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'placeholder' => ['placeholder'];
    }

    /**
     * EDIT 操作在此控制器中被禁用，测试将被跳过
     * 提供一个占位符避免PHPUnit的空数据集错误，测试会检查动作是否启用并跳过
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'placeholder' => ['placeholder'];
    }
}
