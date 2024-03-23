<?php

namespace Orbeji\PrCoverageChecker;

use Nyholm\BundleTest\TestKernel;
use Orbeji\PrCoverageChecker\Coverage\Parser;
use Orbeji\PrCoverageChecker\Git\GitAdapterFactory;
use Orbeji\PrCoverageChecker\Mocks\Git\Bitbucket\BitbucketAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Prevent setting the class alias for all test suites
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PrCoverageCheckerTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /**
         * @var TestKernel $kernel
         */
        $kernel = parent::createKernel($options);
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testcommand(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $gitFactory = $this->createStub(GitAdapterFactory::class);
        $gitFactory->method('create')->willReturn(new BitbucketAdapter('', '', ''));

        $application->add(
            new PrCoverageChecker(
                new Parser(),
                $gitFactory
            )
        );

        $command = $application->find('orbeji:pr-coverage-check');
        $commandTester = new CommandTester($command);
        $status = $commandTester->execute(
            [
                'coverage_report' => __DIR__ . '/clover.xml',
                'percentage' => '90',
                'pullrequest-id' => '1',
                '--git_config' => '{"provider":"Bitbucket", "workspace":"orbeji", "repo":"test", "api_token":"fsfowif"}',
                '--createCoverageReportComment' => true,
                '--createReport' => true,
            ]
        );

        $this->assertEquals(Command::FAILURE, $status);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertEquals("40\n", $output);
    }

    public function testcommandSuccess(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $gitFactory = $this->createStub(GitAdapterFactory::class);
        $gitFactory->method('create')->willReturn(new BitbucketAdapter('', '', ''));

        $application->add(
            new PrCoverageChecker(
                new Parser(),
                $gitFactory
            )
        );

        $command = $application->find('orbeji:pr-coverage-check');
        $commandTester = new CommandTester($command);
        $status = $commandTester->execute(
            [
                'coverage_report' => __DIR__ . '/clover.xml',
                'percentage' => '40',
                'pullrequest-id' => '1',
                '--git_config' => '{"provider":"Bitbucket", "workspace":"orbeji", "repo":"test", "api_token":"fsfowif"}',
            ]
        );

        $this->assertEquals(Command::SUCCESS, $status);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertEquals('', $output);
    }
}
