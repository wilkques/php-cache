<?php

namespace Wilkques\Cache;

use Wilkques\Container\Container;

class Cache
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var \Wilkques\Cache\Drivers\Driver
     */
    protected $driver;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Resolve (and share) the Cache instance.
     *
     * Bound as a singleton so repeated calls return the exact same
     * instance instead of a fresh one each time — same pattern
     * wilkques/console's Console::make() uses. Without this, every
     * make()/cache() call built a brand new Cache -> Driver -> Store
     * chain: harmless for the file store (its state lives on disk, not
     * in the object), but it silently broke any in-memory driver, and
     * contradicted this class's own documented "shared instance"
     * behavior. Found and fixed while adding the array driver, which
     * would otherwise have lost its data between every single call.
     *
     * @return static
     */
    public static function make()
    {
        $container = \Wilkques\Container\Container::getInstance();

        if (!$container->bound(__CLASS__)) {
            $container->singleton(__CLASS__);
        }

        return $container->make(__CLASS__);
    }

    /**
     * @return \Wilkques\Cache\Drivers\Driver
     */
    public function newDriver()
    {
        if ($this->driver) {
            return $this->driver;
        }

        return $this->driver = $this->container->make('\\Wilkques\\Cache\\Drivers\\Driver');
    }

    public function __call($method, $arguments)
    {
        $driver = $this->newDriver();

        // choise driver
        if ($method == 'driver') {
            return call_user_func_array(array($driver, 'driver'), $arguments);
        }

        $store = $driver->driver();

        return call_user_func_array(array($store, $method), $arguments);
    }

    public static function __callStatic($method, $arguments)
    {
        $instance = static::make();

        return call_user_func_array(array($instance, $method), $arguments);
    }
}
