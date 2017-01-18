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

/**
 * Cookies Interface
 *
 * Defines a collection of cookies that is used
 * in both the HTTP request and response objects.
 */
interface CookiesInterface
{
    /**
     * Get request cookie
     *
     * @param  string $name    Cookie name
     * @param  mixed  $default Cookie default value
     *
     * @return mixed Cookie value if present, else default
     */
    public function get($name, $default = null);

    /**
     * Set response cookie
     *
     * @param string       $name  Cookie name
     * @param string|array $value Cookie value, or cookie properties
     *
     * @return void
     */
    public function set($name, $value);

    /**
     * Convert to `Set-Cookie` headers
     *
     * Retrieve an array of well-formed cookie headers.
     *
     * @return string[]
     */
    public function toHeaders();

    /**
     * Parse HTTP request `Cookie:` header and extract
     * into a PHP associative array.
     *
     * @param string $header The raw HTTP request `Cookie:` header
     *
     * @return array Associative array of cookie names and values
     *
     * @throws InvalidArgumentException if the cookie data cannot be parsed
     */
    public static function parseHeader($header);
}
