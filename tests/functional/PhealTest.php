<?php

namespace Pheal\Tests;

use Apix\Cache\Factory;
use Pheal\Cache\NullStorage as CacheNullStorage;
use Pheal\Archive\NullStorage as ArchiveNullStorage;
use Pheal\Log\NullStorage as LogNullStorage;
use Pheal\Access\NullAccess;
use Pheal\Fetcher\Guzzle;
use Pheal\Pheal;
use Pheal\RateLimiter\NullRateLimiter;
use PHPUnit_Framework_TestCase;

/**
 * This is a functional test case which provides basic api interaction
 * @author Kevin Mauel | Bavragor (https://github.com/Bavragor) <kevin.mauel2@gmail.com>
 */
class PhealTest extends PHPUnit_Framework_TestCase {

    public function testBasicPhealUsage()
    {
        $pheal = new Pheal(
            Factory::getPool([]),
            new ArchiveNullStorage(),
            new LogNullStorage(),
            new NullAccess(),
            new Guzzle(),
            new NullRateLimiter()
        );
        $response = $pheal->serverScope->ServerStatus();

        $this->assertTrue(
            $response->serverOpen === 'True' ||
            $response->serverOpen === 'False'
        );
    }

    public function testAdvancedPhealUsage()
    {
        $apiKeyInformation =
            explode(',', rtrim(ltrim(file_get_contents(__DIR__.'/../../apiTestsInformation.txt'))));

        $pheal = new Pheal(
            Factory::getPool([]),
            new ArchiveNullStorage(),
            new LogNullStorage(),
            new NullAccess(),
            new Guzzle(),
            new NullRateLimiter(),
            $apiKeyInformation[0],
            $apiKeyInformation[1]
        );

        $response = $pheal->accountScope->Characters();

        $this->assertNotEmpty($response);
    }

    public function testCachePhealUsage()
    {
        $apiKeyInformation =
            explode(',', rtrim(ltrim(file_get_contents(__DIR__.'/../../apiTestsInformation.txt'))));

        $redis = new \Redis();

        if($redis->connect('127.0.0.1') === false) {
            $this->markTestSkipped('There is no redis instance running!');
        }

        $pheal = new Pheal(
            Factory::getPool($redis),
            new ArchiveNullStorage(),
            new LogNullStorage(),
            new NullAccess(),
            new Guzzle(),
            new NullRateLimiter(),
            $apiKeyInformation[0],
            $apiKeyInformation[1]
        );

        $response = $pheal->accountScope->Characters();

        $this->assertNotEmpty($response);
    }
}
