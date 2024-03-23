<?php

namespace Orbeji\PrCoverageChecker\Git;

use InvalidArgumentException;
use Orbeji\PrCoverageChecker\Git\Bitbucket\BitbucketAdapter;
use Orbeji\PrCoverageChecker\Git\GitHub\GitHubAdapter;
use PHPUnit\Framework\TestCase;

class GitAdapterFactoryTest extends TestCase
{
    public function testNoConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You must specify a git provider. Valid options are: Bitbucket or Github');
        $factory = new GitAdapterFactory();
        $factory->create([]);
    }

    public function testInvalidProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid git provider XXX. Valid options are: Bitbucket or Github');
        $factory = new GitAdapterFactory();
        $factory->create(['provider' => 'XXX']);
    }

    public function testProviderBitbucketNoConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Missing git_config parameters. Required parameters are: workspace, repo and api_token.'
        );
        $factory = new GitAdapterFactory();
        $factory->create(['provider' => 'Bitbucket']);
    }

    public function testProviderBitbucket(): void
    {
        $factory = new GitAdapterFactory();
        $gitAdapter = $factory->create(
            ['provider' => 'Bitbucket', 'workspace' => 'orbeji', 'repo' => 'test', 'api_token' => 'token']
        );
        $this->assertInstanceOf(BitbucketAdapter::class, $gitAdapter);
    }

    public function testProviderGithubNoConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Missing git_config parameters. Required parameters are: owner, repo and api_token.'
        );
        $factory = new GitAdapterFactory();
        $factory->create(['provider' => 'Github']);
    }

    public function testProviderGithub(): void
    {
        $factory = new GitAdapterFactory();
        $gitAdapter = $factory->create(
            ['provider' => 'Github', 'owner' => 'orbeji', 'repo' => 'test', 'api_token' => 'token']
        );
        $this->assertInstanceOf(GitHubAdapter::class, $gitAdapter);
    }
}
