<?php

namespace Orbeji\PrCoverageChecker;

use Exception;
use InvalidArgumentException;
use Orbeji\PrCoverageChecker\Coverage\Parser;
use Orbeji\PrCoverageChecker\Git\GitAdapterFactory;
use Orbeji\PrCoverageChecker\Git\GitAPIAdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

class PrCoverageChecker extends Command
{
    /**
     * @var GitAPIAdapterInterface
     */
    private $gitService;

    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var GitAdapterFactory
     */
    private $gitAdapterFactory;

    public function __construct(Parser $parser, GitAdapterFactory $gitAdapterFactory)
    {
        parent::__construct();
        $this->parser = $parser;
        $this->gitAdapterFactory = $gitAdapterFactory;
    }


    protected function configure(): void
    {
        $this
            ->setName('orbeji:pr-coverage-check')
            ->setDescription('Checks the coverages of the specified PullRequest')
            ->setHelp('This command allows you to create a user...')
            ->addArgument(
                'coverage_report',
                InputArgument::REQUIRED,
                'Path to coverage report'
            )
            ->addArgument(
                'percentage',
                InputArgument::REQUIRED,
                'Required coverage percentage of the new code in the PullRequest'
            )
            ->addArgument(
                'pullrequest-id',
                InputArgument::REQUIRED,
                'Identifier of the pull request to be checked'
            )
            ->addOption(
                'git_config',
                null,
                InputOption::VALUE_REQUIRED,
                'Needed configuration to access the git repository through the APIs',
                json_encode([]),
                [
                    '"provider:"Bitbucket", "workspace":"orbeji", "repo":"pr-coverage-check", "api_token":"bearer"}',
                    '"provider:"Github", "owner":"orbeji", "repo":"pr-coverage-check", "api_token":"bearer"}',
                ]
            )
            ->addOption(
                'createCoverageReportComment',
                null,
                InputOption::VALUE_NONE,
                'Creates a simple coverage report of the uncovered lines in a comment of the pull request'
            )
            ->addOption(
                'createReport',
                null,
                InputOption::VALUE_NONE,
                '(Only for Bitbucket) Creates a report associated to the PullRequest with annotations of' .
                ' the uncovered lines'
            )
            ->addUsage(
                'clover.xml percentage pullrequest-id --git_config={"provider:"Bitbucket", "workspace":"orbeji", ' .
                '"repo":"pr-coverage-check", "api_token":"bearerToken"}'
            );
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $gitConfig = $input->getOption('git_config');
        Assert::string($gitConfig);

        $pullRequestId = $input->getArgument('pullrequest-id');
        Assert::string($pullRequestId);

        $coverageReportPath = $input->getArgument('coverage_report');
        Assert::string($coverageReportPath);

        $expectedPercentage = $input->getArgument('percentage');
        Assert::integer($expectedPercentage);

        $createCoverageReportComment = $input->getOption('createCoverageReportComment');
        Assert::boolean($createCoverageReportComment);

        $createReport = (bool)$input->getOption('createReport');
        Assert::boolean($createReport);

        $gitConfig = json_decode($gitConfig, true);
        if (!is_array($gitConfig)) {
            throw new InvalidArgumentException('Cannot parse git_config parameter');
        }
        $this->gitService = $this->gitAdapterFactory->create($gitConfig);

        $coverageReport = file_get_contents($coverageReportPath);
        if ($coverageReport === false) {
            throw new InvalidArgumentException('Cannot read file: ' . $coverageReportPath);
        }
        [$coveragePercentage, $modifiedLinesUncovered] = $this->check(
            $pullRequestId,
            $coverageReport
        );

        if ($coveragePercentage < $expectedPercentage) {
            if ($createCoverageReportComment) {
                $this->gitService->createCoverageComment($coveragePercentage, $modifiedLinesUncovered, $pullRequestId);
            }
            if ($createReport) {
                $this->gitService->createCoverageReport($coveragePercentage, $modifiedLinesUncovered, $pullRequestId);
            }
        }

        if ($coveragePercentage < $expectedPercentage) {
            $output->writeln((string)$coveragePercentage);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{0:float, 1:array<string,array<int>>}
     * @throws Exception
     */
    private function check(
        string $pullRequestId,
        string $coverageReport
    ): array {
        $diff = $this->gitService->getPullRequestDiff($pullRequestId);
        [$uncoveredLines, $coveredLines] = $this->parser->getCoverageLines($coverageReport);
        $modifiedLines = $this->parser->getPrModifiedLines($diff);
        $modifiedLines = $this->parser->filterModifiedLinesNotInReport($modifiedLines, $uncoveredLines, $coveredLines);

        $modifiedLinesUncovered = $this->parser->getModifiedLinesUncovered($modifiedLines, $uncoveredLines);

        $coveragePercentage = $this->parser->calculateCoveragePercentage($modifiedLinesUncovered, $modifiedLines);

        return [$coveragePercentage, $modifiedLinesUncovered];
    }
}
