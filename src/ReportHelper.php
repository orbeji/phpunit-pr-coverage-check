<?php

namespace Orbeji\PrCoverageChecker;

class ReportHelper
{
    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     */
    public static function createGithubHtmlReport(
        float $coveragePercentage,
        array $modifiedLinesUncovered,
        string $commitId,
        string $owner,
        string $repo
    ): string {
        $report = '<div>Coverage: <strong>' . $coveragePercentage . '%</strong></div>';
        $report .= '<table>';
        $report .= '<thead>';
        $report .= '<th>File</th><th>Uncovered lines</th>';
        $report .= '</thead>';

        $report .= '<tbody>';
        foreach ($modifiedLinesUncovered as $file => $lines) {
            $report .= '<tr><td>' . $file . '</td><td>';
            foreach ($lines as $line) {
                $href = sprintf(
                    'https://github.com/%s/%s/blob/%s/%s#L%s',
                    $owner,
                    $repo,
                    $commitId,
                    $file,
                    $line
                );
                $report .= '<a href="' . $href . '">' . $line . '</a> ';
            }
            $report .= '</td></tr>';
        }
        $report .= '</tbody>';
        $report .= '</table>';
        return $report;
    }

    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     */
    public static function createMarkdownBitbucketReport(
        float $coveragePercentage,
        array $modifiedLinesUncovered,
        string $commitId,
        string $workspace,
        string $repo
    ): string {
        $report = 'Coverage: **' . $coveragePercentage . '%**' . PHP_EOL . PHP_EOL;
        $report .= '|**File**|**Uncovered lines**|' . PHP_EOL;
        $report .= '|---|---|' . PHP_EOL;

        foreach ($modifiedLinesUncovered as $file => $lines) {
            $href = sprintf(
                'https://bitbucket.org/%s/%s/src/%s/%s#lines-%s',
                $workspace,
                $repo,
                $commitId,
                $file,
                implode(',', $lines)
            );
            $report .= '|[' . $file . '](' . $href . ')|';
            foreach ($lines as $line) {
                $href = sprintf(
                    'https://bitbucket.org/%s/%s/src/%s/%s#lines-%s',
                    $workspace,
                    $repo,
                    $commitId,
                    $file,
                    $line
                );
                $report .= ' [' . $line . '](' . $href . ')';
            }
            $report .= '|' . PHP_EOL;
        }
        return $report;
    }
}
