<?php

namespace Orbeji\PrCoverageChecker\Git;

use InvalidArgumentException;
use Orbeji\PrCoverageChecker\Git\Bitbucket\BitbucketAdapter;
use Orbeji\PrCoverageChecker\Git\GitHub\GitHubAdapter;

class GitAdapterFactory
{
    /**
     * @param array<string, string> $gitConfig
     * @return GitAPIAdapterInterface
     */
    public function create(array $gitConfig): GitAPIAdapterInterface
    {
        if (!array_key_exists('provider', $gitConfig)) {
            throw new InvalidArgumentException(
                'You must specify a git provider. Valid options are: Bitbucket or Github'
            );
        }

        switch ($gitConfig['provider']) {
            case 'Bitbucket':
                if (!isset($gitConfig['workspace'], $gitConfig['repo'], $gitConfig['api_token'])) {
                    throw new InvalidArgumentException(
                        'Missing git_config parameters. Required parameters are: workspace, repo and api_token.'
                    );
                }
                return new BitbucketAdapter($gitConfig['workspace'], $gitConfig['repo'], $gitConfig['api_token']);
            case 'Github':
                if (!isset($gitConfig['owner'], $gitConfig['repo'], $gitConfig['api_token'])) {
                    throw new InvalidArgumentException(
                        'Missing git_config parameters. Required parameters are: owner, repo and api_token.'
                    );
                }
                return new GithubAdapter($gitConfig['owner'], $gitConfig['repo'], $gitConfig['api_token']);
            default:
                throw new InvalidArgumentException(
                    'Invalid git provider ' .
                    $gitConfig['provider'] .
                    '. Valid options are: Bitbucket or Github'
                );
        }
    }
}
