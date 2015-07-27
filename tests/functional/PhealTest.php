<?php

namespace Pheal\Tests;

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
            new CacheNullStorage(),
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
}
