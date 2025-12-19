<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;

#[When(env: 'test')]
#[When(env: 'dev')]
class AcmeExceptionLogFixtures extends Fixture
{
    public const EXCEPTION_LOG_REFERENCE = 'exception-log';

    public function load(ObjectManager $manager): void
    {
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('Tourze\\ACMEClientBundle\\Exception\\AcmeServerException');
        $log->setMessage('Test exception message for fixtures');
        $log->setCode(500);
        $log->setStackTrace('#0 /path/to/file.php(10): TestClass->testMethod()');
        $log->setFile('/path/to/file.php');
        $log->setLine(10);
        $log->setEntityType('Account');
        $log->setEntityId(1);
        $log->setContext(['operation' => 'test']);
        $log->setHttpUrl('https://acme-staging-v02.api.letsencrypt.org/test'); // @phpstan-ignore shipmonk.dataFixturesPlaceholderImageService
        $log->setHttpMethod('POST');
        $log->setHttpStatusCode(500);

        $manager->persist($log);
        $this->addReference(self::EXCEPTION_LOG_REFERENCE, $log);

        $manager->flush();
    }
}
