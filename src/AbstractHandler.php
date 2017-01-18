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
 * Define the shared components of a session handler.
 */
abstract class AbstractHandler implements HandlerInterface
{
    /**
     * Handler Options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Handler Defaults
     *
     * @var array
     */
    protected $defaults = [
        'match_ip'          => false,
        'match_ua'          => true,
        'cookie_domain'     => '',
        'cookie_path'       => '/',
        'cookie_secure'     => false,
        'cookie_http_only'  => null,
        'expire_on_close'   => false,
        'expiration_time'   => 7200,
        'rotation_time'     => 300,
        'namespace'         => false,
        'post_cookie_name'  => '',
        'http_header_name'  => 'Session-Id',
        'enable_cookie'     => true,
    ];

    /**
     * Session Manager
     *
     * @var Manager
     */
    protected $manager;

    /**
     * Session Data
     *
     * @var Collection
     */
    protected $data;

    /**
     * Session Expiration
     *
     * @var integer
     */
    protected $expiration = null;

    /**
     * Session Identifier
     *
     * @var string
     */
    protected $id;

    /**
     * Session Name
     *
     * @var string
     */
    protected $name;

    /**
     * Create a handler
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        // Make sure we've got all the config values.
        $options = array_merge($this->defaults, $options);

        // Set the expiration inactivity timer, if invalid, default to 7200 seconds (2 hours).
        if (     isset($options['expiration_time']) &&
            is_numeric($options['expiration_time']) &&
                       $options['expiration_time'] > 0) {
            $this->setExpire($options['expiration_time']);
        } else {
            $this->setExpire(7200);
        }

        // Store the options passed.
        $this->options = $options;
    }

    /**
     * Regenerates the session id
     *
     * @return void
     */
    public function regenerateId()
    {
        // generate a new random session ID.
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $id = '';

        for ($i=0; $i < 32; $i++)
        {
            $id .= substr($chars, mt_rand(0, strlen($chars) -1), 1);
        }

        $this->setId($id);
    }

    /**
     * Sets the manager that manages this driver and container instances for this session
     *
     * @param Manager    $manager
     * @param Collection $data
     *
     * @return void
     */
    public function setInstances(Manager $manager, Collection $data)
    {
        $this->manager = $manager;
        $this->data = $data;
    }

    /**
     * Returns the global expiration of the entire session
     *
     * @return integer
     */
    public function getExpire()
    {
        return $this->expiration;
    }

    /**
     * Sets the global expiration of the entire session
     *
     * @param integer $expiry
     *
     * @return void
     */
    public function setExpire($expiry)
    {
        $this->expiration = $expiry;
    }

    /**
     * Returns the session ID for this session
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the session ID for this session
     *
     * @param string  $id
     *
     * @return void
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Returns the global name of this session
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the global name of this session
     *
     * @param string $name
     *
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Finds the current session ID
     *
     * @return void
     */
    protected function findById()
    {
        // check for a posted session id
        if ( ! empty($this->options['post_cookie_name']) &&
            isset($_POST[$this->options['post_cookie_name']])) {
            $this->id = $_POST[$this->options['post_cookie_name']];
        } elseif ( ! empty($this->options['enable_cookie']) &&
            isset($_COOKIE[$this->name])) {  // else check for a regular cookie
            $this->id = $_COOKIE[$this->name];
        } elseif (isset($_GET[$this->name])) { // else check for a session id in the URL
            $this->id = $_GET[$this->name];
        }

        // TODO: else check the HTTP headers for the session id
        return $this->id ?: null;
    }

    /**
     * Initialize Session
     *
     * Processes the session payload.
     *
     * @param array $payload
     *
     * @return boolean
     */
    protected function initialize(array $payload)
    {
        if ( ! $this->isValid($payload)) {
            return false;
        }

        // Restore the session ID.
        $this->setId($payload['security']['id']);

        // Restore the last session id rotation timer ...
        $this->manager->setRotationTimer($payload['security']['rt']);

        // ... and store the data.
        $this->data->replace($payload['data']);

        return true;
    }

    /**
     * Finalize Session
     *
     * Processes the session payload for the handler to store.
     *
     * @return array
     */
    protected function finalize()
    {
        // Calculate the expiration.
        $expiration = $this->expiration > 0 ? $this->expiration + time() : 0;

        // Make sure we have a sessionId.
        if ($this->getId() === null) {
            $this->regenerateId();
        }

        // Return the assembled payload.
        return [
            'data' => $this->data->all(),
            'security' => [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'ua' => $_SERVER['HTTP_USER_AGENT'],
                'ex' => $expiration,
                'rt' => $this->manager->getRotationTimer(),
                'id' => $this->getId(),
            ],
        ];
    }


    /**
     * Sets a cookie. Note that all cookie values must be strings and no
     * automatic serialization will be performed!
     *
     * @param string $name
     * @param string $value
     *
     * @return boolean
     */
    protected function setCookie($name, $value)
    {
        // Add the current time so we have an offset.
        $expiration = $this->expiration > 0 ? $this->expiration + time() : 0;

        return setcookie($name, $value, $expiration, $this->options['cookie_path'], $this->options['cookie_domain'], $this->options['cookie_secure'], $this->options['cookie_http_only']);
    }

    /**
     * Deletes a cookie by making the value null and expiring it
     *
     * @param string $name
     *
     * @return boolean
     */
    protected function deleteCookie($name)
    {
        // Remove the cookie.
        unset($_COOKIE[$name]);

        // Nullify the cookie and make it expire.
        return setcookie($name, null, -86400, $this->options['cookie_path'], $this->options['cookie_domain'], $this->options['cookie_secure'], $this->options['cookie_http_only']);
    }

    /**
     * Validate Session Payload
     *
     * @param array $payload
     *
     * @return boolean
     */
    protected function isValid(array $payload)
    {
        // Check the payload is well-formed.
        if ( ! isset($payload['security'], $payload['data'])) {
            return false;
        }

        // Check the users IP address.
        if ($this->options['match_ip'] && ($payload['security']['ip'] !== $_SERVER['REMOTE_ADDR'])) {
            return false;
        }

        // Check the user agent.
        if ($this->options['match_ua'] && ($payload['security']['ua'] !== $_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        // Check the session expriation time.
        if ($payload['security']['ex'] && ($payload['security']['ex'] <= time())) {
            return false;
        }

        // Check the session identifier.
        if ($payload['security']['id'] !== $this->getId()) {
            return false;
        }

        return true;
    }
}
