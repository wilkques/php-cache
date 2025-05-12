<?php

namespace Wilkques\Cache\Connections;

use Wilkques\Container\Container;
use Wilkques\Helpers\Arrays;

class Connection
{
    /**
     * @var Container
     */
    protected $container;

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
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $driver
     * 
     * @return FileStore
     */
    public function driver($driver = 'file')
    {
        $this->driver = $driver;

        switch ($driver) {
            case 'file':
            default:
                if ($this->hasnotStore()) {
                    Arrays::set($this->store, $driver, $this->container->make('\Wilkques\Cache\Stores\File'));
                }
                break;
        }

        return $this->store();
    }

    /**
     * @param string|null $driver
     * 
     * @return FileStore
     */
    public function store($driver = null)
    {
        $driver = $driver ?: $this->driver;

        return Arrays::get($this->store, $driver);
    }

    /**
     * @param string|null $driver
     * 
     * @return bool
     */
    public function hasStore($driver = null)
    {
        return (bool) $this->store($driver);
    }

    /**
     * @param string|null $driver
     * 
     * @return bool
     */
    public function hasnotStore($driver = null)
    {
        return ! $this->hasStore($driver);
    }
}
