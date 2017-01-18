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

use InvalidArgumentException;

/**
 * Session Collection
 *
 * Use for interacting with session data.
 */
class Collection
{
    /**
     * The source data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Expiration State of New
     *
     * Variable is stored in the current request.
     *
     * @var string
     */
    const STATE_NEW = 'new';

    /**
     * Expiration State of Loaded
     *
     * Variable is loaded from the session data store.
     *
     * @var string
     */
    const STATE_LOADED = 'loaded';

    /**
     * Expiration State of Expired
     *
     * Variable is expired, and will be removed on save.
     *
     * @var string
     */
    const STATE_EXPIRED = 'expired';

    /**
     * Expiration Data Key
     *
     * Key value used to store expiration information.
     *
     * @var string
     */
    const EXPIRATION_KEY = '__EXPIRATION__';

    /**
     * Container Namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Supported Expiration States
     *
     * @var array
     */
    public static $expirationMethods = [
        Manager::EXPIRE_ON_REQUEST,
        Manager::EXPIRE_ON_GET,
    ];

    /**
     * Set Container Namespace
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     */
    public function setNamespace($name)
    {
        $name = $name ?: '';

        if ( ! is_string($name) || empty($name)) {
            throw new InvalidArgumentException('Invalid session container namespace');
        }

        $this->namespace = $name;
    }

    /**
     * Sets session namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Add item to collection
     *
     * @param array $items Key-value array of data to append to this collection
     *
     * @return void
     */
    public function replace(array $data)
    {
        if (isset($data[static::EXPIRATION_KEY])) {
            // process the expiration settings
            foreach ($data[static::EXPIRATION_KEY] as $key => $expiration) {
                // if it was set on the last request, make it as loaded
                if ($expiration[1] === static::STATE_NEW) {
                    $data[static::EXPIRATION_KEY][$key][1] = static::STATE_LOADED;
                // if it was already loaded on the last request, and we expire on request, delete it
                } elseif ($expiration[0] === Manager::EXPIRE_ON_REQUEST &&
                          $expiration[1] === static::STATE_LOADED) {
                    unset($data[static::EXPIRATION_KEY][$key]);
                    unset($data[$key]);
                }
            }
        } else {
            // not set, create an empty one to start with
            $data[static::EXPIRATION_KEY] = [];
        }

        $this->data = $data;
    }

    /**
     * Check Container Key
     *
     * Checks if a key was set for item in container.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function has($key)
    {
        $key = $this->prefixKey($key);
        return isset($this->data[$key]);
    }

    /**
     * Get Container Value
     *
     * Returns a key's value from container
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {

            // Retrieve expiration information.
            $expiration = $this->getExpiration($key);
            $nsKey = $this->prefixKey($key);

            // If there is no expiration information, return it.
            if (empty($expiration)) {
                return $this->data[$nsKey];
            }

            // Check if it has already expired.
            if ($expiration[1] !== static::STATE_EXPIRED) {
                // If expiration should be on get, expire it.
                if ($expiration[0] === Manager::EXPIRE_ON_GET) {
                    $expiration[1] = static::STATE_EXPIRED;
                    $this->setExpiration($key, $expiration);
                }

                return $this->data[$nsKey];
            }
        }

        return $default;
    }

    /**
     * Set Container Value
     *
     * @param string  $key
     * @param mixed   $value
     * @param string  $expiry
     */
    public function set($key, $value, $expiry = null)
    {
        // Assign a default expiry method if none is given.
        if ($expiry) {
            // Validate the expiry method.
            if ( ! in_array($expiry, self::$expirationMethods)) {
                throw new InvalidArgumentException(sprintf('Invalid session flash expiration method: "%s"', $expiry));
            }

            // Store the expiration information for this key.
            $this->setExpiration($key, [$expiry, static::STATE_NEW]);
        }

        $key = $this->prefixKey($key);
        $this->data[$key] = $value;
    }

    /**
     * Deletes data from the container
     *
     * @param string $key
     *
     * @return boolean
     */
    public function delete($key)
    {
        $key = $this->prefixKey($key);

        // Remove the expiration tracking for this key.
        unset($this->data[static::EXPIRATION_KEY][$key]);

        // Remove the value associated to key.
        unset($this->data[$key]);
    }

    /**
     * Prefix the container key with the namespace currently set
     *
     * @param string $key
     *
     * @return string
     *
     * @throws  \InvalidArgumentException
     */
    protected function prefixKey($key)
    {
        // make sure we have a valid key
        if (empty($key)) {
            throw new InvalidArgumentException('Container key cannot be empty');
        }

        $ns = $this->getNamespace();

        return empty($ns) ? $key : $ns . '.' . $key;
    }

    /**
     * Set Expiration for Key
     *
     * Returns the expiry state on the given key
     *
     * @param string $key
     * @param array  $expiration [$type, $state]
     *
     * @return void
     */
    public function setExpiration($key, array $expiration = [])
    {
        $key = $this->prefixKey($key);
        $this->data[static::EXPIRATION_KEY][$key] = $expiration;
    }

    /**
     * Get Expiration of Key
     *
     * Returns the expiry state on the given key
     *
     * @param string $key
     *
     * @return array
     */
    public function getExpiration($key)
    {
        $expiration = [];
        $key = $this->prefixKey($key);

        if (isset($this->data[static::EXPIRATION_KEY][$key])) {
            $expiration = $this->data[static::EXPIRATION_KEY][$key];

            list($method, $state) = $expiration;

            $expiration[0] = (
                Manager::EXPIRE_ON_REQUEST === $method
              ? Manager::EXPIRE_ON_REQUEST
              : Manager::EXPIRE_ON_GET
            );

            $expiration[1] = (
                  static::STATE_NEW === $state
                ? static::STATE_NEW
                : (
                      static::STATE_LOADED === $state
                    ? static::STATE_LOADED
                    : static::STATE_EXPIRED
                )
            );
        }

        return $expiration;
    }

    /**
     * Keep Item
     *
     * Reset the expiry state on the given key
     *
     * @param string $key
     *
     * @return boolean
     */
    public function keep($key)
    {
        $expiration = $this->getExpiration($key);

        if (empty($expiration)) {
            return false;
        }

        $expiration[1] = static::STATE_NEW;
        $this->setExpiration($key, $expiration);

        return true;
    }

    /**
     * Returns the container's data
     *
     * @return array
     */
    public function all()
    {
        // Make a copy to leave the original container untouched.
        $data = $this->data;;

        // Delete all expired variables.
        if (isset($data[static::EXPIRATION_KEY])) {
            $expiration = $data[static::EXPIRATION_KEY];
            foreach ($expiration as $key => $expiry) {
                if ($expiry[1] === static::STATE_EXPIRED) {
                    unset($data[static::EXPIRATION_KEY][$key]);
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    /**
     * Get number of items in collection
     *
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * Remove all items from collection
     *
     * @return void
     */
    public function clear()
    {
        $this->data = [];
    }

    /**
     * Get collection keys
     *
     * @return array The collection's source data keys
     */
    public function keys()
    {
        return array_keys($this->data);
    }
}