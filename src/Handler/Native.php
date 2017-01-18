<?php
/*
 * This file is part of the Session package.
 *
 * (c) Unit6 <team@unit6websites.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Unit6\Session\Handler;

use Unit6\Session\AbstractHandler;
use Unit6\Session\Manager;

/**
 * Session driver using PHP native sessions
 *
 * NOTE: this driver is thread-safe, PHP flock's the session file which will
 * cause concurrent/async requests to be processed synchroneously. This may
 * have an impact on performance of ajax applications in particular.
 */
class Native extends AbstractHandler
{
    /**
     * Default Options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Create new Native Session Handler
     *
     * @param array $options
     */
    public function __construct($name, array $options = [])
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Native session handler requires a name');
        }

        $this->name = $name;
        $options = array_merge($this->options, $options);

        parent::__construct($options);

        // NOTE: Parse the parameters for session cookies AFTER running
        //       parent constructor for setting global defaults.
        $this->setCookieDefaults($options);
    }

    /**
     * Set Session Cookie Params
     *
     * @param array $options
     *
     * @return void
     */
    public function setCookieDefaults(array $options = [])
    {
        // get default the cookie params
        $params = session_get_cookie_params();

        // update them with any config passed
        if (isset($options['cookie_domain'])) {
            $params['domain'] = $options['cookie_domain'];
        }

        if (isset($options['cookie_path'])) {
            $params['path'] = $options['cookie_path'];
        }

        if (isset($options['cookie_secure']) && $options['cookie_secure']) {
            $params['secure'] = true;
        }

        if (isset($options['cookie_http_only']) && $options['cookie_http_only']) {
            $params['httponly'] = true;
        }

        if (isset($options['expire_on_close']) && $options['expire_on_close']) {
            $params['lifetime'] = 0;
        }

        session_set_cookie_params($this->expiration, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        // Set the session ID for this session.
        session_id($id);

        return parent::setId($id);
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        // Set the global name of this session
        session_name($name);

        return parent::setName($name);
    }

    /**
     * Check Session Status
     *
     * PHP_SESSION_DISABLED (0): if sessions are disabled.
     * PHP_SESSION_NONE (1): if sessions are enabled, but none exists.
     * PHP_SESSION_ACTIVE (2): if sessions are enabled, and one exists.
     *
     * @return string
     */
    public function getStatus()
    {
        $status = null;
        switch (session_status()) {
            case PHP_SESSION_DISABLED:
                $status = Manager::STATUS_DISABLED;
                break;
            case PHP_SESSION_NONE:
                $status = Manager::STATUS_NONE;
                break;
            case PHP_SESSION_ACTIVE:
                $status = Manager::STATUS_ACTIVE;
                break;
            default:
                break;
        }

        return $status;
    }

    /**
     * Check Session Status
     *
     * Determine whether or not the current session status is active.
     *
     * @see http://php.net/session_status
     *
     * @return boolean
     */
    public function isActive()
    {
        return ($this->getStatus() === Manager::STATUS_ACTIVE);
    }

    /**
     * {@inheritdoc}
     */
    public function create()
    {
        if ( ! $this->isActive()) {
            $this->start();
        }

        // Regenerate the session ID and flush any existing sessions.
        if ($result = session_regenerate_id($deleteOldSession = true)) {
            $this->setId(session_id());
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        // Start the native session.
        if ( ! $this->isActive()) {
            session_start();
        }

        // Update the session ID.
        $this->setId(session_id());

        // Read any existing session payload.
        return $this->read();
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        // Abort if we don't have an active session.
        if ( ! $this->isActive()) {
            return false;
        }

        // Check session name exists.
        if ( ! isset($_SESSION[$this->name])) {
            return false;
        }

        // Fetch the payload from the native session global.
        $payload = $_SESSION[$this->name];

        return $this->initialize($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function write()
    {
        // Not implemented in the native driver, flush the data through a stop/start.
        $stop = $this->stop();
        $start = $this->start();

        // Only return true if both succeeded.
        return ($stop && $start);
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        // Abort if we don't have an active session.
        if ( ! $this->isActive()) {
            return false;
        }

        $payload = $this->finalize();

        $_SESSION[$this->name] = $payload;

        session_write_close();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy()
    {
        // Abort if we don't have an active session.
        if ( ! $this->isActive()) {
            return false;
        }

        // Delete the session cookie if present.
        if (ini_get('session.use_cookies')) {
            $this->deleteCookie(session_name());
        }

        // Unset all native session data ...
        session_unset();

        // ... and kill the session.
        return session_destroy();
    }

    /**
     * Regerate the session, rotate the session ID
     *
     * @return void
     */
    public function regenerateId()
    {
        // Regenerate the session ID ...
        session_regenerate_id();

        // ... and update the stored ID.
        $this->setId(session_id());
    }

    /**
     * {@inheritdoc}
     */
    public function setExpire($expiry)
    {
        // Set the expiry on the session cache.
        session_cache_expire($expiry);

        // Set the cookie expiry to match the expiration.
        session_set_cookie_params($expiry);

        return parent::setExpire($expiry);
    }
}
