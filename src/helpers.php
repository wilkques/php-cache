<?php

if (!function_exists('cache')) {
    /**
     * @param string $key
     * @param mixed|null $default
     * 
     * @return \Wilkques\Cache\Cache|mixed
     */
    function cache($key = null, $default = null)
    {
        $cache = \Wilkques\Cache\Cache::make();

        if ($key) {
            return $cache->get($key, $default);
        }

        return $cache;
    }
}