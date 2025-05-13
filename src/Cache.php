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

        $container->singleton('\Wilkques\Cache\Cache', function () use ($container) {
            return $container->make('\Wilkques\Cache\Cache');
        });

        return $container->get('\Wilkques\Cache\Cache');
    }

    public function __call($method, $arguments)
    {
        /** @var \Wilkques\Cache\Connections\Connection */
        $driver = $this->container->make('\Wilkques\Cache\Drivers\Driver');

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
