<?php
/*
 MIT License
 Copyright (c) 2010 - 2014 Daniel Hoffend, Peter Petermann

 Permission is hereby granted, free of charge, to any person
 obtaining a copy of this software and associated documentation
 files (the "Software"), to deal in the Software without
 restriction, including without limitation the rights to use,
 copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the
 Software is furnished to do so, subject to the following
 conditions:

 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Pheal;

use Pheal\Access\CanAccess;
use Pheal\Archive\CanArchive;
use Pheal\Cache\CanCache;
use Pheal\Core\Result;
use Pheal\Exceptions\ConnectionException;
use Pheal\Exceptions\HTTPException;
use Pheal\Exceptions\PhealException;
use Pheal\Fetcher\CanFetch;
use Pheal\Log\CanLog;
use Pheal\RateLimiter\CanRateLimit;

/**
 * Pheal (PHp Eve Api Library), a EAAL Port for PHP
 * @method Result APIKeyInfo()
 * @author Kevin Mauel | Bavragor <kevin.mauel2@gmail.com>
 */
class Pheal
{
    /**
     * Version container
     *
     * @var string
     */
    const VERSION = '3.0.0';

    /**
     * @var int
     */
    private $userId;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string|null
     */
    private $keyType;

    /**
     * @var int
     */
    private $accessMask;

    /**
     * EVE Api scope to be used (for example: "account", "char","corp"...)
     * @var string
     */
    public $scope;

    /**
     * Result of the last XML request, so application can use the raw xml data
     * @var String
     */
    public $xml;

    /**
     * Cache Object, defaults to an \Pheal\Cache\NullStorage Object
     * @var CanCache
     */
    public $cache;

    /**
     * Archive Object, defaults to an \Pheal\Archive\NullStorage Object
     * @var CanArchive
     */
    public $archive;

    /**
     * Access Object to validate and check an API with a given keyType+accessMask
     * defaults to \Pheal\Access\NullAccess
     * @var CanAccess
     */
    public $access;

    /**
     * Fetcher object to decide what technology is to be used to fetch
     * defaults to \Pheal\Fetcher\Guzzle
     * @var CanFetch
     */
    public $fetcher;

    /**
     * Log object to log and measure the API calls that were made
     * defaults to \Pheal\Log\NullStorage (== no logging)
     *
     * @var CanLog
     */
    public $log;

    /**
     * Rate limiter object to avoid exceeding CCP-defined maximum requests per second.
     * Defaults to \Pheal\RateLimiter\NullRateLimiter (== no rate limiting)
     *
     * @var CanRateLimit
     */
    public $rateLimiter;

    /**
     * creates new Pheal API object
     * @param CanCache $cache
     * @param CanArchive $archive
     * @param CanLog $log
     * @param CanAccess $access
     * @param CanFetch $fetcher
     * @param CanRateLimit $rateLimit
     * @param null $userId
     * @param string $key the EVE apikey/vCode
     * @param string $scope to use, defaults to account. Can be changed during runtime by modifying attribute "scope"
     */
    public function __construct(
        CanCache $cache,
        CanArchive $archive,
        CanLog $log,
        CanAccess $access,
        CanFetch $fetcher,
        CanRateLimit $rateLimit,
        $userId = null,
        $key = null,
        $scope = 'account'
    ) {
        $this->userId = $userId;
        $this->key = $key;
        $this->scope = $scope;

        $this->cache = $cache;
        $this->archive = $archive;
        $this->log = $log;
        $this->access = $access;
        $this->fetcher = $fetcher;
        $this->rateLimiter = $rateLimit;
    }

    /**
     * Magic __call method, will translate all function calls to object to API requests
     * @param String $name name of the function
     * @param array $arguments an array of arguments
     * @return \Pheal\Core\Result
     */
    public function __call($name, $arguments)
    {
        if (count($arguments) < 1 || !is_array($arguments[0])) {
            $arguments[0] = [];
        }
        $scope = $this->scope;
        // we only use the first argument params need to be passed as an array, due to naming
        return $this->requestXml($scope, $name, $arguments[0]);

    }

    /**
     * Magic __get method used to set scope
     * @param string $name name of the scope e.g. "mapScope"
     * @return \Pheal\Pheal|null
     */
    public function __get($name)
    {
        /**
         * TODO: A regex is usually slower than php builtin functions
         */
        if (preg_match('/(.+)Scope$/', $name, $matches) === 1) {
            $this->scope = $matches[1];
            return $this;
        }
        return null;
    }

    /**
     * Set keyType/accessMask
     * @param string $keyType must be Account/Character/Corporation or null
     * @param int $accessMask must be integer or 0
     * @return void
     */
    public function setAccess($keyType = null, $accessMask = 0)
    {
        $this->keyType = in_array(
            ucfirst(strtolower($keyType)),
            array('Account', 'Character', 'Corporation')
        ) ? $keyType : null;
        $this->accessMask = (int)$accessMask;
    }

    /**
     * clear+reset keyType/accessMask
     * @return void
     */
    public function clearAccess()
    {
        $this->setAccess();
    }

    /**
     * if userid+key is given it automatically detects (api call) the keyinfo and
     * set the correct access level for futher checks.
     *
     * Keep in mind this method will make an api request to account/APIKeyInfo based
     * on the given \Pheal\Core\Config settings with the given key credentials.
     *
     * More important! This method will throw Exceptions on invalid keys or networks errors
     * So place this call into your try statement
     *
     * @throws \Pheal\Exceptions\PhealException|\Pheal\Exceptions\APIException|\Pheal\Exceptions\HTTPException
     * @return boolean|\Pheal\Core\Result
     */
    public function detectAccess()
    {
        // don't request keyinfo if api keys are not set or if new CAK aren't enabled
        if (!$this->userId || !$this->key || !$this->fetcher->api_customkeys) {
            return false;
        }

        // request api key info, save old scope and restore it afterwords
        $old = $this->scope;
        $this->scope = 'account';
        $keyInfo = $this->APIKeyInfo();
        $this->scope = $old;

        // set detected keyType and accessMask
        $this->setAccess($keyInfo->key->type, $keyInfo->key->accessMask);

        // return the APIKeyInfo Result object in the case you need it.
        return $keyInfo;
    }

    /**
     * Method will ask caching class for valid xml, if non valid available
     * will make API call, and return the appropriate result
     *
     * @param string $scope api scope (examples: eve, map, server, ...)
     * @param string $name api method (examples: ServerStatus, Kills, Sovereignty, ...)
     * @param array $options additional args (example.: characterID => 12345), shouldn't contain apikey/userid/keyid/vcode
     * @throws ConnectionException
     * @throws HTTPException
     * @throws PhealException
     * @throws \Exception
     * @return Result
     *
     */
    private function requestXml($scope, $name, array $options = [])
    {
        // apikey/userid/keyid|vcode shouldn't be allowed in arguments and
        // removed to avoid wrong cached api calls
        foreach ($options as $key => $value) {
            if (in_array(strtolower($key), array('userid', 'apikey', 'keyid', 'vcode'))) {
                unset($options[$key]);
            }
        }

        // prepare http arguments + url (to not modify original argument list for cache saving)
        $url = $this->fetcher->api_base . $scope . '/' . $name . '.xml.aspx';
        $useCustomKey = (bool)$this->fetcher->api_customkeys;
        $http_opts = $options;
        if ($this->userId) {
            $http_opts[($useCustomKey ? 'keyID' : 'userid')] = $this->userId;
        }
        if ($this->key) {
            $http_opts[($useCustomKey ? 'vCode' : 'apikey')] = $this->key;
        }

        // check access level if given (throws PhealAccessExpception if API call is not allowed)
        if ($useCustomKey && $this->userId && $this->key && $this->keyType) {
            try {
                $this->access->check($scope, $name, $this->keyType, $this->accessMask);
            } catch (\Exception $e) {
                $this->log->errorLog($scope, $name, $http_opts, $e->getMessage());
                throw $e;
            }
        }

        // check cache first
        if (!$this->xml = $this->cache->load($this->userId, $this->key, $scope, $name, $options)) {
            try {
                // start measure the response time
                $this->log->start();

                // rate limit
                $this->rateLimiter->rateLimit();

                // request
                $this->xml = $this->fetcher->fetch($url, $http_opts);

                // stop measure the response time
                $this->log->stop();

                $element = @new \SimpleXMLElement($this->xml);

                // check if we could parse this
                if ($element === false) {
                    $errorMessage = '';
                    foreach (libxml_get_errors() as $error) {
                        $errorMessage .= $error->message . "\n";
                    }
                    throw new PhealException('XML Parser Error: ' . $errorMessage);
                }

                // archive+save only non-error api calls + logging
                if (!$element->error) {
                    $this->log->log($scope, $name, $http_opts);
                    $this->archive->save($this->userId, $this->key, $scope, $name, $options, $this->xml);
                } else {
                    $this->log->errorLog(
                        $scope,
                        $name,
                        $http_opts,
                        $element->error['code'] . ': ' . $element->error
                    );
                }

                $this->cache->save($this->userId, $this->key, $scope, $name, $options, $this->xml);
                // just forward HTTP Errors
            } catch (HTTPException $e) {
                throw $e;
                // ensure that connection exceptions are passed on
            } catch (ConnectionException $e) {
                throw $e;
                // other request errors
            } catch (\Exception $e) {
                // log + throw error
                $this->log->errorLog(
                    $scope,
                    $name,
                    $http_opts,
                    $e->getCode() . ': ' . $e->getMessage()
                );
                throw new PhealException('Original exception: ' . $e->getMessage(), $e->getCode(), $e);
            }

        } else {
            $element = @new \SimpleXMLElement($this->xml);
        }

        return new Result($element);
    }
}
