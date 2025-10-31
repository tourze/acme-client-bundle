<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;

class AcmeExceptionLogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 创建连接超时异常日志
        $exceptionLog1 = new AcmeExceptionLog();
        $exceptionLog1->setExceptionClass('GuzzleHttp\Exception\ConnectException');
        $exceptionLog1->setMessage('Connection timeout while connecting to ACME server');
        $exceptionLog1->setCode(28);
        $exceptionLog1->setStackTrace('#0 /app/src/Service/AcmeApiClient.php(45): GuzzleHttp\Client->request()');
        $exceptionLog1->setFile('/app/src/Service/AcmeApiClient.php');
        $exceptionLog1->setLine(45);
        $exceptionLog1->setEntityType('Order');
        $exceptionLog1->setEntityId(1);
        $exceptionLog1->setContext(['server_url' => 'https://acme-v02.api.letsencrypt.org', 'timeout' => 30]);
        $exceptionLog1->setHttpUrl('https://acme-v02.api.letsencrypt.org/acme/new-order');
        $exceptionLog1->setHttpMethod('POST');
        $exceptionLog1->setHttpStatusCode(null);
        $exceptionLog1->setResolved(false);

        // 创建验证失败异常日志
        $exceptionLog2 = new AcmeExceptionLog();
        $exceptionLog2->setExceptionClass('Tourze\ACMEClientBundle\Exception\AcmeValidationException');
        $exceptionLog2->setMessage('DNS challenge validation failed for domain acme-test.tourze.dev');
        $exceptionLog2->setCode(400);
        $exceptionLog2->setStackTrace('#0 /app/src/Service/ChallengeService.php(78): Tourze\ACMEClientBundle\Service\ChallengeService->validateDnsChallenge()');
        $exceptionLog2->setFile('/app/src/Service/ChallengeService.php');
        $exceptionLog2->setLine(78);
        $exceptionLog2->setEntityType('Challenge');
        $exceptionLog2->setEntityId(1);
        $exceptionLog2->setContext(['domain' => 'acme-test.tourze.dev', 'challenge_type' => 'dns-01']);
        $exceptionLog2->setHttpUrl('https://acme-v02.api.letsencrypt.org/acme/challenge/test123');
        $exceptionLog2->setHttpMethod('POST');
        $exceptionLog2->setHttpStatusCode(400);
        $exceptionLog2->setResolved(true);

        // 创建证书生成异常日志
        $exceptionLog3 = new AcmeExceptionLog();
        $exceptionLog3->setExceptionClass('Tourze\ACMEClientBundle\Exception\CertificateGenerationException');
        $exceptionLog3->setMessage('Failed to generate certificate: Invalid CSR format');
        $exceptionLog3->setCode(500);
        $exceptionLog3->setStackTrace('#0 /app/src/Service/CertificateService.php(120): openssl_csr_new()');
        $exceptionLog3->setFile('/app/src/Service/CertificateService.php');
        $exceptionLog3->setLine(120);
        $exceptionLog3->setEntityType('Certificate');
        $exceptionLog3->setEntityId(null);
        $exceptionLog3->setContext(['domains' => ['test.acme.tourze.dev', '*.acme.tourze.dev']]);
        $exceptionLog3->setHttpUrl(null);
        $exceptionLog3->setHttpMethod(null);
        $exceptionLog3->setHttpStatusCode(null);
        $exceptionLog3->setResolved(false);

        $manager->persist($exceptionLog1);
        $manager->persist($exceptionLog2);
        $manager->persist($exceptionLog3);
        $manager->flush();
    }
}
