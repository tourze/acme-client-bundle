<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;

class AcmeOperationLogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 创建账户注册操作日志
        $operationLog1 = new AcmeOperationLog();
        $operationLog1->setLevel(LogLevel::INFO);
        $operationLog1->setOperation('account_register');
        $operationLog1->setMessage('Successfully registered new ACME account');
        $operationLog1->setEntityType('Account');
        $operationLog1->setEntityId(1);
        $operationLog1->setContext(['email' => 'test@acme-test.tourze.dev', 'server' => 'letsencrypt-staging']);
        $operationLog1->setHttpUrl('https://acme-staging-v02.api.letsencrypt.org/acme/new-acct');
        $operationLog1->setHttpMethod('POST');
        $operationLog1->setHttpStatusCode(201);
        $operationLog1->setDurationMs(1250);
        $operationLog1->setSuccess(true);

        // 创建证书申请操作日志
        $operationLog2 = new AcmeOperationLog();
        $operationLog2->setLevel(LogLevel::INFO);
        $operationLog2->setOperation('order_create');
        $operationLog2->setMessage('Created new certificate order for domains: acme-test.tourze.dev, www.acme-test.tourze.dev');
        $operationLog2->setEntityType('Order');
        $operationLog2->setEntityId(1);
        $operationLog2->setContext(['domains' => ['acme-test.tourze.dev', 'www.acme-test.tourze.dev'], 'order_id' => 'abc123']);
        $operationLog2->setHttpUrl('https://acme-staging-v02.api.letsencrypt.org/acme/new-order');
        $operationLog2->setHttpMethod('POST');
        $operationLog2->setHttpStatusCode(201);
        $operationLog2->setDurationMs(850);
        $operationLog2->setSuccess(true);

        // 创建 DNS 质询验证操作日志
        $operationLog3 = new AcmeOperationLog();
        $operationLog3->setLevel(LogLevel::WARNING);
        $operationLog3->setOperation('challenge_validate');
        $operationLog3->setMessage('DNS challenge validation failed, retrying in 30 seconds');
        $operationLog3->setEntityType('Challenge');
        $operationLog3->setEntityId(1);
        $operationLog3->setContext(['domain' => 'acme-test.tourze.dev', 'challenge_type' => 'dns-01', 'retry_count' => 2]);
        $operationLog3->setHttpUrl('https://acme-staging-v02.api.letsencrypt.org/acme/challenge/xyz789');
        $operationLog3->setHttpMethod('POST');
        $operationLog3->setHttpStatusCode(400);
        $operationLog3->setDurationMs(3200);
        $operationLog3->setSuccess(false);

        $manager->persist($operationLog1);
        $manager->persist($operationLog2);
        $manager->persist($operationLog3);
        $manager->flush();
    }
}
