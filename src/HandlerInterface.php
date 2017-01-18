<?php
/*
 * This file is part of the Session package.
 *
 * (c) Unit6 <team@unit6websites.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Unit6\Session;

/**
 * Session Handler
 *
 * Define the session handler interface.
 */
interface HandlerInterface
{
    /**
     * Session Identifier
     *
     * @return string
     */
    public function getId();

    /**
     * Session Name
     *
     * @return string
     */
    public function getName();

    /**
     * Session Status
     *
     * @return string
     */
    public function getStatus();

    /**
     * Regenerate the Session Identifier
     *
     * @return void
     */
    public function regenerateId();

    /**
     * Creates a new session
     *
     * @return boolean
     */
    public function create();

    /**
     * Starts the session
     *
     * @return boolean
     */
    public function start();

    /**
     * Reads session data
     *
     * @return boolean
     */
    public function read();

    /**
     * Writes session data
     *
     * @return boolean
     */
    public function write();

    /**
     * Stops the session
     *
     * @return boolean
     */
    public function stop();

    /**
     * Garbage collection
     *
     * @return boolean
     */
    public function gc();

    /**
     * Destroys the session
     *
     * @return boolean
     */
    public function destroy();
}