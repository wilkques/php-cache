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
     * @return static
     */
    public static function make()
    {
        $container = new Container;

        $container->singleton(__CLASS__, function (self $cache) {
            return $cache;
        });

        return $container->get(__CLASS__);
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
