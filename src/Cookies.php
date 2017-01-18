<?php
/*
 * This file is part of the HTTP package.
 *
 * (c) Unit6 <team@unit6websites.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Unit6\HTTP;

use InvalidArgumentException;

/**
 * Cookies
 *
 * This class represents a collection of cookies
 * that is used in both the HTTP request and response objects.
 */
class Cookies implements CookiesInterface
{
    /**
     * Cookies from HTTP request
     *
     * @var array
     */
    protected $requestCookies = [];

    /**
     * Cookies for HTTP response
     *
     * @var array
     */
    protected $responseCookies = [];

    /**
     * Default cookie properties
     *
     * @var array
     */
    protected $defaults = [
        'value'    => '',
        'domain'   => null,
        'path'     => null,
        'expires'  => null,
        'secure'   => false,
        'httponly' => false
    ];

    /**
     * Create new cookies helper
     *
     * @param array $cookies
     */
    public function __construct(array $cookies = [])
    {
        $this->requestCookies = $cookies;
    }

    /**
     * Set default cookie properties
     *
     * @param array $settings
     *
     * @return void
     */
    public function setDefaults(array $settings)
    {
        $this->defaults = array_replace($this->defaults, $settings);
    }

    /**
     * Get request cookie
     *
     * @param  string $name    Cookie name
     * @param  mixed  $default Cookie default value
     *
     * @return mixed Cookie value if present, else default
     */
    public function get($name, $default = null)
    {
        return isset($this->requestCookies[$name]) ? $this->requestCookies[$name] : $default;
    }

    /**
     * Set response cookie
     *
     * @param string       $name  Cookie name
     * @param string|array $value Cookie value, or cookie properties
     *
     * @return void
     */
    public function set($name, $value)
    {
        if ( ! is_array($value)) {
            $value = ['value' => (string) $value];
        }

        $this->responseCookies[$name] = array_replace($this->defaults, $value);
    }

    /**
     * Check if the cookie matches a domain value.
     *
     * @param string $domain Domain to check against
     *
     * @return bool
     */
    public static function isDomainMatch(array $cookie, $domain)
    {
        // Remove the leading '.' as per spec in RFC 6265.
        // http://tools.ietf.org/html/rfc6265#section-5.2.3
        $cookieDomain = isset($cookie['Domain']) ? ltrim($cookie['Domain'], '.') : null;

        // Domain not set or exact match.
        if ( ! $cookieDomain || ! strcasecmp($domain, $cookieDomain)) {
            return true;
        }

        // Matching the subdomain according to RFC 6265.
        // http://tools.ietf.org/html/rfc6265#section-5.1.3
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return false;
        }

        return (bool) preg_match('/\.' . preg_quote($cookieDomain) . '$/i', $domain);
    }

    /**
     * Convert to `Set-Cookie` headers
     *
     * @return string[]
     */
    public function toHeaders()
    {
        $headers = [];
        foreach ($this->responseCookies as $name => $properties) {
            $headers[] = $this->toHeader($name, $properties);
        }

        return $headers;
    }

    /**
     * Convert to `Set-Cookie` header
     *
     * @param  string $name       Cookie name
     * @param  array  $properties Cookie properties
     *
     * @return string
     */
    protected function toHeader($name, array $properties)
    {
        $result = urlencode($name) . '=' . urlencode($properties['value']);

        if (isset($properties['domain'])) {
            $result .= '; domain=' . $properties['domain'];
        }

        if (isset($properties['path'])) {
            $result .= '; path=' . $properties['path'];
        }

        if (isset($properties['expires'])) {
            if (is_string($properties['expires'])) {
                $timestamp = strtotime($properties['expires']);
            } else {
                $timestamp = (int) $properties['expires'];
            }

            if ($timestamp !== 0) {
                $result .= '; expires=' . gmdate('D, d-M-Y H:i:s e', $timestamp);
            }
        }

        if (isset($properties['secure']) && $properties['secure']) {
            $result .= '; secure';
        }

        if (isset($properties['httponly']) && $properties['httponly']) {
            $result .= '; httponly';
        }

        return $result;
    }

    /**
     * Parse HTTP request `Cookie:` header and extract
     * into a PHP associative array.
     *
     * @param array|string $header The raw HTTP request `Cookie:` header
     *
     * @return array Associative array of cookie names and values
     *
     * @throws InvalidArgumentException if the cookie data cannot be parsed
     */
    public static function parseHeader($header)
    {
        if (is_array($header)) {
            $header = isset($header[0]) ? $header[0] : '';
        }

        if ( ! is_string($header)) {
            throw new InvalidArgumentException('Cookie header value must be a string.');
        }

        $header = rtrim($header, "\r\n");
        $pieces = preg_split('@\s*[;,]\s*@', $header);
        $cookies = [];

        foreach ($pieces as $cookie) {
            $cookie = explode('=', $cookie, 2);

            if (count($cookie) === 2) {
                $key = urldecode($cookie[0]);
                $value = urldecode($cookie[1]);

                if ( ! isset($cookies[$key])) {
                    $cookies[$key] = $value;
                }
            }
        }

        return $cookies;
    }
}
