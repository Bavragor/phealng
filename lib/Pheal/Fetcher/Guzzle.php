<?php

namespace Pheal\Fetcher;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Pheal\Core\Config;
use Pheal\Exceptions\ConnectionException;
use Pheal\Exceptions\HTTPException;
use Pheal\Pheal;


/**
 * @author Kevin Mauel | Bavragor (https://github.com/Bavragor) <kevin.mauel2@gmail.com>
 */
class Guzzle extends CanFetch
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    /**
     * Set the actual config instance, based on it creates a client instance
     */
    public function __construct()
    {
        $this->client = new Client(
            $this->generateClientConfiguration()
        );

    }

    /**
     * Fetches data from api
     * @param string $url
     * @param array $options
     * @throws ConnectionException
     * @throws HTTPException
     * @return string
     */
    public function fetch($url, array $options)
    {
        $options = array_merge($options, $this->additional_request_parameters);

        try {
            $response = $this->client->request(
                $this->http_post === true ? 'POST' : 'GET',
                $url,
                $options
            );
        } catch (GuzzleException $exception) {
            throw new ConnectionException(
                $exception->getMessage(),
                $exception->getCode()
            );
        }

        if ($response->getStatusCode() >= 400) {
            // ccp is using error codes even if they send a valid application
            // error response now, so we have to use the content as result
            // for some of the errors. This will actually break if CCP ever uses
            // the HTTP Status for an actual transport related error.
            switch ($response->getStatusCode()) {
                case 400:
                case 403:
                case 500:
                case 503:
                    return $response->getBody()->getContents();
                    break;
            }

            throw new HTTPException(
                $response->getStatusCode(),
                $url
            );
        }

        return $response->getBody()->getContents();
    }

    /**
     * Generates Client configuration based on current config instance
     * @return array
     */
    private function generateClientConfiguration()
    {
        $clientConfiguration = [
            'base_uri' => $this->api_base,
            'timeout' => $this->http_timeout,
            'headers' => [
                'Connection' => 'keep-alive',
                'Accept-Encoding' => ''
            ]
        ];

        if ($this->http_user_agent !== false) {
            $clientConfiguration['headers']['User-Agent'] =
                'PhealNG/' . Pheal::VERSION . ' ' . $this->http_user_agent;
        }

        if ($this->http_keepalive !== false) {
            $clientConfiguration['headers']['Keep-Alive'] = 'timeout=' .
            $this->http_keepalive === true ? 15 : $this->http_keepalive .
                ', max=1000';
        }

        $clientConfiguration['verify'] = false;

        if ($this->http_ssl_verifypeer === true && $this->http_ssl_certificate_file !== false) {
            $clientConfiguration['verify'] = $this->http_ssl_certificate_file;
        } elseif ($this->http_ssl_verifypeer === true) {
            $clientConfiguration['verify'] = true;
        }

        return $clientConfiguration;
    }
}
