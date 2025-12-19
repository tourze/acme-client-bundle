<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;

#[When(env: 'test')]
#[When(env: 'dev')]
class AcmeOperationLogFixtures extends Fixture
{
    public const OPERATION_LOG_REFERENCE = 'operation-log';

    public function load(ObjectManager $manager): void
    {
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('account_create');
        $log->setMessage('Test operation log message for fixtures');
        $log->setEntityType('Account');
        $log->setEntityId(1);
        $log->setContext(['test' => 'value']);
        $log->setHttpUrl('https://acme-staging-v02.api.letsencrypt.org/acct/123'); // @phpstan-ignore shipmonk.dataFixturesPlaceholderImageService
        $log->setHttpMethod('POST');
        $log->setHttpStatusCode(201);
        $log->setDurationMs(150);
        $log->setSuccess(true);

        $manager->persist($log);
        $this->addReference(self::OPERATION_LOG_REFERENCE, $log);

        $manager->flush();
    }
}
