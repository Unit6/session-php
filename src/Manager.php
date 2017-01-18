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

use BadMethodCallException;
use InvalidArgumentException;

/**
 * Session Manager
 *
 * Control how sessions are created and accessed.
 */
class Manager
{
    /**
     * PHP_SESSION_DISABLED
     *
     * Session state if sessions are disabled.
     *
     * @var string
     */
    const STATUS_DISABLED = 'disabled';

    /**
     * PHP_SESSION_NONE
     *
     * Session state if sessions are enabled, but none exists.
     *
     * @var string
     */
    const STATUS_NONE = 'none';

    /**
     * PHP_SESSION_ACTIVE
     *
     * Session state if sessions are enabled, and one exists.
     *
     * @var string
     */
    const STATUS_ACTIVE = 'active';

    /**
     * Expiration On Request
     *
     * Automatically expire variable on next session load.
     *
     * @var string
     */
    const EXPIRE_ON_REQUEST = 'request';

    /**
     * Expiration On Get
     *
     * Automatically expire variable on first get.
     *
     * @var string
     */
    const EXPIRE_ON_GET = 'get';

    /**
     * Session Handler
     *
     * Determines whether it is the native $_SESSION, file, database or cookie.
     *
     * @var Handler\HandlerInterface
     */
    protected $handler;

    /**
     * Session Options
     *
     * @var array
     */
    protected $options;

    /**
     * Session Rotation Interval
     *
     * @var mixed
     */
    protected $rotationInterval;

    /**
     * Session Rotation Timer
     *
     * @var integer
     */
    protected $rotationTimer;

    /**
     * Create new session
     *
     * @param HandlerInterface $handler
     * @param array                   $options
     */
    public function __construct(HandlerInterface $handler, array $options = [])
    {
        // Store the handler and options.
        $this->handler = $handler;
        $this->options = $options;

        // Create the containers
        $this->reset();

        // Tell the driver who manages the session, and what the containers are.
        $this->handler->setInstances($this, $this->data);

        // Any namespace defined?
        if (isset($options['namespace'])) {
            $this->setNamespace($options['namespace']);
        }

        // Set the session rotation interval and the default rotation timer.
        if ( ! isset($options['rotation_time'])) {
            $this->rotationInterval = 300;
        } elseif ( ! is_numeric($options['rotation_time']) ||
            $options['rotation_time'] <= 0 ||
            $options['rotation_time'] === false) {
            $this->rotationInterval = false;
        } else {
            $this->rotationInterval = (int) $options['rotation_time'];
            $this->rotationTimer = time() + $this->rotationInterval;
        }
    }

    /**
     * Magic method, captures calls to the containers and the handler
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if (is_callable([$this->data, $method])) { // data method?
            return call_user_func_array([$this->data, $method], $arguments);
        } elseif (substr($method, 0, 3) == 'get' && is_callable([$this->handler, $method])) { // handler method?
            return call_user_func_array([$this->handler, $method], $arguments);
        }

        throw new BadMethodCallException(sprintf('Undefined session method: "%s"', $method));
    }

    /**
     * Session Handler
     *
     * @return HandlerInterface
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * Session Data
     *
     * @return Collection
     */
    public function &getData()
    {
        return $this->data;
    }

    /**
     * Session Identifier
     *
     * @return string
     */
    public function getId()
    {
        return $this->getHandler()->getId();
    }

    /**
     * Session Name
     *
     * @return string
     */
    public function getName()
    {
        return $this->getHandler()->getName();
    }

    /**
     * Session Status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->getHandler()->getStatus();
    }

    /**
     * Session Rotation Timer
     *
     * @return integer
     */
    public function getRotationTimer()
    {
        return $this->rotationTimer;
    }

    /**
     * Set Rotation Timer for Session
     *
     * @param integer $interval
     *
     * @return void
     */
    public function setRotationTimer($interval)
    {
        $this->rotationTimer = time() + $interval;
    }

    /**
     * Session Rotation Interval
     *
     * @return mixed
     */
    public function getRotationInterval()
    {
        return $this->rotationInterval;
    }

    /**
     * Is Rotation Due
     *
     * @return boolean
     */
    public function isRotationDue()
    {
        return ($this->getRotationInterval() && $this->getRotationTimer() < time());
    }

    /**
     * Creates New Session
     *
     * @return boolean
     */
    public function create()
    {
        // Reset the data containers ...
        $this->reset();

        // .. and create a new session.
        return $this->getHandler()->create();
    }

    /**
     * Starts Session
     *
     * @return boolean
     */
    public function start()
    {
        return $this->getHandler()->start();
    }

    /**
     * Reads the session data into the session store
     *
     * @return boolean
     */
    public function read()
    {
        return $this->getHandler()->read();
    }

    /**
     * Writes the container data to the session store
     *
     * @return boolean
     */
    public function write()
    {
        if ($this->isRotationDue()) {
            $this->rotate();
        }

        return $this->getHandler()->write();
    }

    /**
     * Stops a current session
     *
     * @return boolean
     */
    public function stop()
    {
        if ($this->isRotationDue()) {
            $this->rotate();
        }

        return $this->getHandler()->stop();
    }

    /**
     * Session rotation
     *
     * Rotates the session id, and reset the rotation timer if needed
     *
     * @return void
     */
    public function rotate()
    {
        // update the session id rotation timer
        if ($interval = $this->getRotationInterval()) {
            $this->setRotationTimer($interval);
        }

        // regenerate the session id
        $this->getHandler()->regenerateId();
    }

    /**
     * Destroys the current session
     *
     * @return void
     */
    public function destroy()
    {
        $this->reset();

        return $this->getHandler()->destroy($this);
    }

    /**
     * Returns the current session namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->options['namespace'];
    }

    /**
     * Sets the session namespace
     *
     * @param string $name
     */
    public function setNamespace($name)
    {
        $this->options['namespace'] = $name;
        $this->getData()->setNamespace($this->options['namespace']);
    }

    /**
     * Resets the data and flash data containers
     */
    protected function reset()
    {
        $this->data = new Collection();
    }
}
