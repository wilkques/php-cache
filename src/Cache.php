<?php

namespace Wilkques\Cache;

class Cache
{
    /**
     * @var string
     */
    protected $driver = 'file';

    /** @var static */
    protected static $instance;

    /**
     * driver resolver
     * 
     * @var array
     */
    protected $store = array();

    /**
     * @return static
     */
    public static function make()
    {
        if (static::$instance) {
            return static::$instance;
        }

        static::$instance = new static;

        return static::$instance;
    }

    /**
     * @param string $driver
     * 
     * @return FileStore
     */
    public static function driver($driver = 'file')
    {
        $instance = static::make();

        switch ($driver) {
            case 'file':
            default:
                $resolver = new FileStore();

                $instance->store[$driver] = $resolver;
                break;
        }

        return $resolver;
    }

    public function __call($method, $arguments)
    {
        if (empty($this->store)) {
            $store = static::driver($this->driver);
        
            return call_user_func_array(array($store, $method), $arguments);
        }

        $store = $this->store[$this->driver];
        
        return call_user_func_array(array($store, $method), $arguments);
    }

    public static function __callStatic($method, $arguments)
    {
        $instance = static::make();

        return call_user_func_array(array($instance, $method), $arguments);
    }
}
