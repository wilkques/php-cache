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
        return (new \Wilkques\Container\Container)->make('\Wilkques\Cache\Cache');
    }

    public function __call($method, $arguments)
    {
        /** @var \Wilkques\Cache\Connections\Connection */
        $connection = $this->container->make('\Wilkques\Cache\Connections\Connection');

        if ($method == 'driver') {
            return call_user_func_array(array($connection, 'driver'), $arguments);
        }

        $store = $connection->driver();

        return call_user_func_array(array($store, $method), $arguments);
    }

    public static function __callStatic($method, $arguments)
    {
        $instance = static::make();

        return call_user_func_array(array($instance, $method), $arguments);
    }
}
