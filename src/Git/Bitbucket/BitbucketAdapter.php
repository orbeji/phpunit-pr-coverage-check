<?php

namespace Orbeji\PrCoverageChecker\Git\Bitbucket;

use Orbeji\PrCoverageChecker\Exception\GitApiException;
use Orbeji\PrCoverageChecker\Git\GitAPIAdapterInterface;
use Orbeji\PrCoverageChecker\ReportHelper;
use stdClass;
use Unirest\Request;
use Unirest\Response;

class BitbucketAdapter implements GitAPIAdapterInterface
{
    /**
     * @var string
     */
    private $workspace;
    /**
     * @var string
     */
    private $repo;
    /**
     * @var string
     */
    private $bearerToken;

    public function __construct(string $workspace, string $repo, string $bearerToken)
    {
        $this->workspace = $workspace;
        $this->repo = $repo;
        $this->bearerToken = $bearerToken;
    }

    /**
     * @throws GitApiException
     */
    public function getPullRequestDiff(string $pullRequestId): string
    {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->bearerToken
        );

        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/pullrequests/%s/diff',
            $this->workspace,
            $this->repo,
            $pullRequestId
        );

        $response = Request::get(
            $url,
            $headers
        );

        if ($response->code !== 200) {
            $message = $this->getErrorMessage($response);
            throw new GitApiException($message);
        }

        return $response->raw_body;
    }

    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     * @throws GitApiException
     */
    public function createCoverageComment(
        float $coveragePercentage,
        array $modifiedLinesUncovered,
        string $pullRequestId
    ): void {
        $commitId = $this->getCommitIdFromPullRequest($pullRequestId);
        $this->addCoverageComment($coveragePercentage, $modifiedLinesUncovered, $pullRequestId, $commitId);
    }

    /**
     * @throws GitApiException
     */
    private function getCommitIdFromPullRequest(string $pullRequestId): string
    {
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->bearerToken,
        );
        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/pullrequests/%s',
            $this->workspace,
            $this->repo,
            $pullRequestId
        );
        $response = Request::get(
            $url,
            $headers
        );

        if ($response->code !== 200) {
            $message = $this->getErrorMessage($response);
            throw new GitApiException($message);
        }

        return $response->body->source->commit->hash;
    }

    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     * @throws GitApiException
     */
    public function addCoverageComment(
        float $coveragePercentage,
        array $modifiedLinesUncovered,
        string $pullRequestId,
        string $commitId
    ): void {
        $markdownReport = ReportHelper::createMarkdownBitbucketReport(
            $coveragePercentage,
            $modifiedLinesUncovered,
            $commitId,
            $this->workspace,
            $this->repo
        );
        $this->commentMarkdownReport($pullRequestId, $markdownReport);
    }

    /**
     * @throws GitApiException
     */
    private function commentMarkdownReport(string $pullRequestId, string $markdownReport): void
    {
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->bearerToken
        );

        $body = [
            "content" => [
                "raw" => $markdownReport
            ]
        ];

        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/pullrequests/%s/comments',
            $this->workspace,
            $this->repo,
            $pullRequestId
        );

        $response = Request::post(
            $url,
            $headers,
            json_encode($body)
        );

        if ($response->code !== 200) {
            $message = $this->getErrorMessage($response);
            throw new GitApiException($message);
        }
    }

    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     * @throws GitApiException
     */
    public function createCoverageReport(
        float $coveragePercentage,
        array $modifiedLinesUncovered,
        string $pullRequestId
    ): void {
        $commitId = $this->getCommitIdFromPullRequest($pullRequestId);
        $this->deleteOutdatedCoverageReports($commitId);
        $idReport = $this->createReport($coveragePercentage, $commitId);
        $this->addAnnotations($idReport, $modifiedLinesUncovered, $commitId);
    }

    /**
     * @throws GitApiException
     */
    private function deleteOutdatedCoverageReports(string $commitId): void
    {
        $coverageReports = $this->getCoverageReports($commitId);
        foreach ($coverageReports as $coverageReport) {
            $this->deleteReport($commitId, $coverageReport);
        }
    }

    /**
     * @return array<stdClass>
     * @throws GitApiException
     */
    private function getCoverageReports(string $commitId): array
    {
        $reports = $this->listReports($commitId);
        $coverageReports = [];
        foreach ($reports as $report) {
            if ($report->report_type === 'COVERAGE') {
                $coverageReports[] = $report;
            }
        }
        return $coverageReports;
    }

    /**
     * @return array<stdClass>
     * @throws GitApiException
     */
    private function listReports(string $commitId): array
    {
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->bearerToken
        );
        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/commit/%s/reports',
            $this->workspace,
            $this->repo,
            $commitId
        );
        $response = Request::get(
            $url,
            $headers
        );

        if ($response->code !== 200) {
            $message = $this->getErrorMessage($response);
            throw new GitApiException($message);
        }

        return $response->body->values;
    }

    /**
     * @throws GitApiException
     */
    private function deleteReport(string $commitId, stdClass $coverageReport): void
    {
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->bearerToken,
        );

        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/commit/%s/reports/%s',
            $this->workspace,
            $this->repo,
            $commitId,
            $coverageReport->external_id
        );
        $response = Request::delete(
            $url,
            $headers
        );

        if ($response->code !== 200) {
            $message = $this->getErrorMessage($response);
            throw new GitApiException($message);
        }
    }

    /**
     * @throws GitApiException
     */
    private function createReport(float $coveragePercentage, string $commitId): string
    {
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->bearerToken
        );

        $idReport = uuid_create(UUID_TYPE_RANDOM);

        $body = [
            "external_id" => $idReport,
            "title" => "Coverage report",
            "details" => "Coverage report of the modified/created code",
            "report_type" => "COVERAGE",
            "result" => "FAILED",
            "data" => [
                [
                    "type" => "PERCENTAGE",
                    "title" => "Coverage of new code",
                    "value" => $coveragePercentage,
                ]
            ]
        ];

        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/commit/%s/reports/%s',
            $this->workspace,
            $this->repo,
            $commitId,
            $idReport
        );

        $response = Request::put(
            $url,
            $headers,
            json_encode($body)
        );

        if ($response->code !== 200) {
            $message = $this->getErrorMessage($response);
            throw new GitApiException($message);
        }

        return $response->body->uuid;
    }

    /**
     * @param array<string,array<int>> $modifiedLinesUncovered
     * @throws GitApiException
     */
    private function addAnnotations(?string $idReport, array $modifiedLinesUncovered, string $commitId): void
    {
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->bearerToken,
        );

        $body = [];
        foreach ($modifiedLinesUncovered as $file => $lines) {
            foreach ($lines as $line) {
                $body[] = [
                    "external_id" => uuid_create(UUID_TYPE_RANDOM),
                    "annotation_type" => "VULNERABILITY",
                    "summary" => "Line not covered in tests",
                    "severity" => "HIGH",
                    "path" => $file,
                    "line" => $line
                ];
            }
        }

        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/commit/%s/reports/%s/annotations',
            $this->workspace,
            $this->repo,
            $commitId,
            $idReport
        );

        $response = Request::post(
            $url,
            $headers,
            json_encode($body)
        );

        if ($response->code !== 200) {
            $message = $this->getErrorMessage($response);
            throw new GitApiException($message);
        }
    }

    private function getErrorMessage(Response $response): string
    {
        $message = 'API error';
        if (json_validate($response->raw_body)) {
            $error = json_decode($response->raw_body, true);
            if (is_array($error) && array_key_exists('error', $error)) {
                $message = $error['error']['message'] ?? '';
            }
        }
        return $message;
    }
}
