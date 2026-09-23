<?php

namespace Wilkques\Cache\Stores;

use Wilkques\Cache\Concerns\InteractsWithTime;

/**
 * A process-local, in-memory cache store — data lives only as long as the
 * PHP process does (a single request under php-fpm, or the lifetime of a
 * long-lived CLI/worker process). Useful for tests and for memoizing
 * within a single request/process; it is NOT shared across requests or
 * processes the way the file store is.
 */
class ArrayStore
{
    /**
     * @var array<string, array{value: mixed, expiresAt: int|null}>
     */
    protected $storage = array();

    /**
     * @param string|int $key
     * @param mixed $value
     * @param int|\DateInterval|\DateTime|null $secord
     *
     * @return bool
     */
    public function put($key, $value, $secord = null)
    {
        $secord = InteractsWithTime::resolveSeconds($secord);

        // Mirrors Store\File's convention: a literal 0 means forever
        // (stored as a null expiresAt here, since there's no fixed-width
        // text format to pad/cap for in memory); null/omitted defaults to
        // 1 hour, same as the file store's default.
        $expiresAt = $secord === 0 ? null : time() + ($secord === null ? 3600 : $secord);

        $this->storage[$key] = array('value' => $value, 'expiresAt' => $expiresAt);

        return true;
    }

    /**
     * @param string|int $key
     * @param mixed $value
     *
     * @return bool
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
    }

    /**
     * @param string|int $key
     * @param mixed $value
     * @param int|\DateInterval|\DateTime|null $secord
     *
     * @return bool
     */
    public function add($key, $value, $secord = null)
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->put($key, $value, $secord);
    }

    /**
     * @param string|int|null $key
     * @param mixed|false $default
     *
     * @return mixed|false
     */
    public function get($key = null, $default = false)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        $item = $this->storage[$key];

        if ($item['expiresAt'] !== null && $item['expiresAt'] <= time()) {
            unset($this->storage[$key]);

            return null;
        }

        return $item['value'];
    }

    /**
     * @param string|int|null $key
     * @param mixed|false $default
     *
     * @return mixed|false
     */
    public function pull($key = null, $default = false)
    {
        $value = $this->get($key, $default);

        $this->forgot($key);

        return $value;
    }

    /**
     * @param string|int $key
     *
     * @return bool
     */
    public function has($key)
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $item = $this->storage[$key];

        if ($item['expiresAt'] !== null && $item['expiresAt'] <= time()) {
            unset($this->storage[$key]);

            return false;
        }

        return true;
    }

    /**
     * @param string|int $key
     *
     * @return bool
     */
    public function forgot($key)
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);

            return true;
        }

        return false;
    }

    /**
     * Increment a value, preserving its remaining TTL — a missing/expired
     * key is treated as 0 and the result is then cached forever afterwards,
     * same as Store\File::increment().
     *
     * @param string|int $key
     * @param int $value
     *
     * @return int
     */
    public function increment($key, $value = 1)
    {
        $current = 0;

        $expiresAt = null;

        if (isset($this->storage[$key])) {
            $item = $this->storage[$key];

            if ($item['expiresAt'] === null || $item['expiresAt'] > time()) {
                $current = (int) $item['value'];

                $expiresAt = $item['expiresAt'];
            }
        }

        $newValue = $current + $value;

        $this->storage[$key] = array('value' => $newValue, 'expiresAt' => $expiresAt);

        return $newValue;
    }

    /**
     * @param string|int $key
     * @param int $value
     *
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * @param string $key
     * @param int|\DateInterval|\DateTime|null $expire
     * @param callback $callback
     *
     * @return mixed
     */
    public function remember($key, $expire, $callback)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();

        $this->put($key, $value, $expire);

        return $value;
    }

    /**
     * @return void
     */
    public function clear()
    {
        $this->storage = array();
    }
}
