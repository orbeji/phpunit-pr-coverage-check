<?php

namespace Orbeji\PrCoverageChecker\Git;

use Orbeji\PrCoverageChecker\Exception\GitApiException;

interface GitAPIAdapterInterface
{
    /**
     * @throws GitApiException
     */
    public function getPullRequestDiff(string $pullRequestId): string;

    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     * @throws GitApiException
     */
    public function createCoverageComment(
        float $coveragePercentage,
        array $modifiedLinesUncovered,
        string $pullRequestId
    ): void;

    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     * @throws GitApiException
     */
    public function createCoverageReport(
        float $coveragePercentage,
        array $modifiedLinesUncovered,
        string $pullRequestId
    ): void;
}
