<?php

namespace Pheal\Core;


/**
 * A set of configurable options which can be manipulated if used.
 * @author Kevin Mauel | Bavragor (https://github.com/Bavragor) <kevin.mauel2@gmail.com>
 */
trait Configurable
{
    /**
     * usually this points to the EVE API directly, however if you use a API
     * proxy you might want to modify this.
     * use https://api.eveonline.com/ if you like to have ssl support
     * @var String
     */
    public $api_base = 'https://api.eveonline.com/';

    /**
     * enable the new customize key system (use keyID instead of userID, etc)
     * @var bool
     */
    public $api_customkeys = true;

    /**
     * associative array with additional parameters that should be passed
     * to the API on every request.
     * @var array
     */
    public $additional_request_parameters = [];

    /**
     * which outgoing ip/inteface should be used for the http request
     * (bool) false means use default ip address
     * @var String
     */
    public $http_interface_ip = false;

    /**
     * which useragent should be used for http calls.
     * (bool) false means do not change php default
     * @var String
     */
    public $http_user_agent = '( Unknown PHP Application )';

    /**
     * should parameters be transfered in the POST body request or via GET request
     * @var bool
     */
    public $http_post = false;

    /**
     * After what time should an api call considered to as timeout?
     * @var int
     */
    public $http_timeout = 20;

    /**
     * Verify the SSL peer?
     * You may need to provide a bundle of trusted Certificate Agencies
     *
     * @see self::$http_ssl_certificate_file
     * @see CURLOPT_SSL_VERIFYPEER
     *
     * @var bool
     */
    public $http_ssl_verifypeer = true;

    /**
     * If you want to verify the SSL connections to the EVE API, you may need to provide a bundle of
     * trusted certification agencies
     *
     * @see CURLOPT_CAINFO
     * @see http://curl.haxx.se/ca/cacert.pem
     *
     * @var string|false
     */
    public $http_ssl_certificate_file = false;

    /**
     * reuse a http connection (keep-alive for X seconds) to lower the connection handling overhead
     * keep in mind after the script ended the connection will be closed anyway.
     *
     * @var bool|int number of seconds a connection should be kept open (bool true == 15)
     */
    public $http_keepalive = false;
}
