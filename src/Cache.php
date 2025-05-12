<?php

namespace Wilkques\Cache;

use Wilkques\Container\Container;
use Wilkques\Helpers\Arrays;

class Cache
{
    /**
     * @var string
     */
    protected $driver = 'file';

    /**
     * driver resolver
     * 
     * @var array
     */
    protected $store = array();

    /**
     * @var Container
     */
    protected $container;

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

    /**
     * @param string $driver
     * 
     * @return FileStore
     */
    public function driver($driver = 'file')
    {
        switch ($driver) {
            case 'file':
            default:
                Arrays::set($this->store, $driver, $this->container->make('\Wilkques\Cache\FileStore'));
                break;
        }

        return Arrays::get($this->store, $driver);
    }

    public function __call($method, $arguments)
    {
        $store = $this->driver($this->driver);
        
        return call_user_func_array(array($store, $method), $arguments);
    }

    public static function __callStatic($method, $arguments)
    {
        $instance = static::make();

        return call_user_func_array(array($instance, $method), $arguments);
    }
}
