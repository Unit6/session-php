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
 * Testing Sessions
 *
 * Creating and sending an client session.
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{
    private $session;

    public function setUp()
    {
        $this->session = null;
    }

    public function tearDown()
    {
        unset($this->session);
    }

    public function testEverythingIsOK()
    {
        $this->assertTrue(true);
    }
}