<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\ACMEClientBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(ACMEClientBundle::class)]
#[RunTestsInSeparateProcesses]
final class ACMEClientBundleTest extends AbstractBundleTestCase
{
}
