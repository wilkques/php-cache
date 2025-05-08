<?php

namespace Wilkques\Cache;

class Cache
{
    /**
     * @param string $driver
     * 
     * @return FileStore
     */
    public static function driver($driver = 'file')
    {
        switch ($driver) {
            case 'file':
            default:
                return new FileStore;
                break;
        }
    }
}
