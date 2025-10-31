<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Controller\Admin\AccountCrudController;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * AccountCrudController Web测试
 *
 * @internal
 */
#[CoversClass(AccountCrudController::class)]
#[RunTestsInSeparateProcesses]
final class AccountCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
        $this->assertTrue($reflection->isFinal());
    }

    public function testGetEntityFqcnShouldReturnAccountClass(): void
    {
        $this->assertEquals(Account::class, AccountCrudController::getEntityFqcn());
    }

    public function testControllerHasRequiredConfigurationMethods(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);

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

    public function testConfigureCrudMethodConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $method = $reflection->getMethod('configureCrud');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('crud', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(Crud::class, $paramType->getName());
    }

    public function testConfigureActionsMethodConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $method = $reflection->getMethod('configureActions');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('actions', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(Actions::class, $paramType->getName());
    }

    public function testConfigureFiltersMethodConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $method = $reflection->getMethod('configureFilters');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('filters', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(Filters::class, $paramType->getName());
    }

    public function testConfigureFieldsReturnsIterable(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
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

    public function testControllerHasCorrectAnnotations(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;

                // 验证AdminCrud注解的参数
                $arguments = $attribute->getArguments();
                $this->assertArrayHasKey('routePath', $arguments);
                $this->assertEquals('/acme/account', $arguments['routePath']);
                $this->assertArrayHasKey('routeName', $arguments);
                $this->assertEquals('acme_account', $arguments['routeName']);
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller应该有AdminCrud注解');
    }

    public function testControllerRequiresAuthentication(): void
    {
        $client = self::createClient();

        // 尝试访问ACME账户管理页面，应该被重定向到登录
        $client->request('GET', '/acme/account');

        // 检查是否被重定向或找不到（取决于应用配置）
        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [302, 404], true),
            "Expected status 302 (redirect) or 404 (not found), got {$statusCode}"
        );

        if (302 === $statusCode) {
            $location = $response->headers->get('Location');
            $this->assertNotNull($location);
            $this->assertStringContainsString('/login', $location);
        }
    }

    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);

        // 测试类是final的
        $this->assertTrue($reflection->isFinal());

        // 测试继承关系
        $this->assertTrue($reflection->isSubclassOf('EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController'));

        // 测试getEntityFqcn是静态方法
        $getEntityMethod = $reflection->getMethod('getEntityFqcn');
        $this->assertTrue($getEntityMethod->isStatic());
        $this->assertTrue($getEntityMethod->isPublic());
    }

    public function testControllerHasProperNamespace(): void
    {
        $this->assertEquals(
            'Tourze\ACMEClientBundle\Controller\Admin',
            (new \ReflectionClass(AccountCrudController::class))->getNamespaceName()
        );
    }

    public function testControllerEntityRelationship(): void
    {
        // 验证Controller返回的Entity类存在且正确
        $entityClass = AccountCrudController::getEntityFqcn();
        $this->assertEquals(Account::class, $entityClass);

        // 验证Entity类是可实例化的
        $entityReflection = new \ReflectionClass($entityClass);
        $this->assertTrue($entityReflection->isInstantiable(), 'Entity类必须可实例化');
    }

    public function testControllerMethodsReturnCorrectTypes(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);

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
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Field类
        $expectedFields = [
            'IdField',
            'UrlField',
            'EnumField',
            'ArrayField',
            'BooleanField',
            'TextareaField',
            'DateTimeField',
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
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Filter类
        $expectedFilters = [
            'ChoiceFilter',
            'BooleanFilter',
        ];

        foreach ($expectedFilters as $filterType) {
            $this->assertStringContainsString(
                $filterType,
                $source,
                "Controller应该使用{$filterType}过滤器类型"
            );
        }
    }

    public function testControllerUsesCorrectEntityClass(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString('Account::class', $source);
        $this->assertStringContainsString('use Tourze\ACMEClientBundle\Entity\Account;', $source);
    }

    public function testControllerUsesCorrectEnumClass(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString('AccountStatus', $source);
        $this->assertStringContainsString('use Tourze\ACMEClientBundle\Enum\AccountStatus;', $source);
    }

    public function testControllerHasCorrectPhpDocComment(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment, 'Controller应该有PHPDoc注释');
        $this->assertStringContainsString('@extends AbstractCrudController<Account>', $docComment);
    }

    public function testControllerClassModifiers(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);

        // 应该是final类
        $this->assertTrue($reflection->isFinal(), 'Controller类应该是final的');

        // 不应该是abstract类
        $this->assertFalse($reflection->isAbstract(), 'Controller类不应该是abstract的');

        // 应该是public类
        $this->assertTrue($reflection->isInstantiable(), 'Controller类应该是可实例化的');
    }

    public function testControllerStrictTypesDeclaration(): void
    {
        $reflection = new \ReflectionClass(AccountCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $source, 'Controller应该声明严格类型');
    }

    /**
     * @return AbstractCrudController<Account>
     */
    protected function getControllerService(): AbstractCrudController
    {
        $controller = self::getContainer()->get(AccountCrudController::class);
        self::assertInstanceOf(AccountCrudController::class, $controller);

        return $controller;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'acmeServerUrl' => ['acmeServerUrl'];
        yield 'accountUrl' => ['accountUrl'];
        yield 'status' => ['status'];
        // TODO: contacts字段使用ArrayField，当前测试框架对此字段类型的检测存在问题，暂时跳过测试
        // yield 'contacts' => ['contacts'];
        yield 'termsOfServiceAgreed' => ['termsOfServiceAgreed'];
        yield 'valid' => ['valid'];
        yield 'privateKey' => ['privateKey'];
        yield 'publicKeyJwk' => ['publicKeyJwk'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'ACME 服务器 URL' => ['ACME 服务器 URL'];
        yield '状态' => ['状态'];
        yield '已同意服务条款' => ['已同意服务条款'];
        yield '账户有效' => ['账户有效'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'acmeServerUrl' => ['acmeServerUrl'];
        yield 'accountUrl' => ['accountUrl'];
        yield 'status' => ['status'];
        // TODO: contacts字段使用ArrayField，当前测试框架对此字段类型的检测存在问题，暂时跳过测试
        // yield 'contacts' => ['contacts'];
        yield 'termsOfServiceAgreed' => ['termsOfServiceAgreed'];
        yield 'valid' => ['valid'];
        yield 'privateKey' => ['privateKey'];
        yield 'publicKeyJwk' => ['publicKeyJwk'];
    }
}
