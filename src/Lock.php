<?php

namespace Wilkques\Cache;

/**
 * A simple, file-backed exclusive lock, using PHP's native flock() rather
 * than going through Wilkques\Filesystem\Filesystem (which has no locking
 * primitive) — deliberately kept as its own small standalone primitive
 * rather than trying to graft file-locking support onto that package.
 *
 * Much smaller than Laravel's Illuminate\Cache\Lock/LockableFile: no
 * blocking-wait-with-timeout polling loop, no lock-store abstraction
 * separate from the cache store itself. Covers the common
 * "only one process/request should do this at a time" case.
 */
class Lock
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $seconds;

    /**
     * @var string
     */
    protected $owner;

    /**
     * @var resource|null
     */
    protected $handle;

    /**
     * @param string $path Full path to the lock file.
     * @param string $name Logical lock name (for informational purposes).
     * @param int $seconds How long the lock is considered held for. 0 means
     *                     no expiry — it's only released by release()/
     *                     forceRelease(), same convention as
     *                     Store\File::forever()'s 0-means-forever.
     * @param string|null $owner An existing owner token to restore a lock
     *                           under (see restoreLock()); a fresh random
     *                           token is generated when omitted.
     */
    public function __construct($path, $name, $seconds = 0, $owner = null)
    {
        $this->path = $path;

        $this->name = $name;

        $this->seconds = $seconds;

        $this->owner = $owner ?: static::generateOwner();
    }

    /**
     * @return string
     */
    public static function generateOwner()
    {
        return md5(uniqid((string) mt_rand(), true));
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Attempt to acquire the lock without blocking.
     *
     * @return bool
     */
    public function acquire()
    {
        $handle = @fopen($this->path, 'c+');

        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $expiresAt = $this->seconds > 0 ? (time() + $this->seconds) : 0;

        ftruncate($handle, 0);

        fwrite($handle, $this->owner . ':' . $expiresAt);

        fflush($handle);

        $this->handle = $handle;

        return true;
    }

    /**
     * Acquire the lock, optionally run $callback while holding it (always
     * released afterwards, even if $callback throws), and return the
     * callback's result — or, with no callback, just the bool result of
     * acquire().
     *
     * @param callable|null $callback
     *
     * @return mixed
     */
    public function get($callback = null)
    {
        $acquired = $this->acquire();

        if ($callback === null) {
            return $acquired;
        }

        if (!$acquired) {
            return false;
        }

        try {
            $result = call_user_func($callback);
        } catch (\Exception $e) {
            $this->release();

            throw $e;
        }

        $this->release();

        return $result;
    }

    /**
     * Release the lock, if this instance is the one currently holding it.
     *
     * @return bool
     */
    public function release()
    {
        if (!$this->handle) {
            return false;
        }

        @unlink($this->path);

        flock($this->handle, LOCK_UN);

        fclose($this->handle);

        $this->handle = null;

        return true;
    }

    /**
     * Release the lock file unconditionally, regardless of which process
     * (if any) currently holds it. For clearing a stuck lock manually.
     *
     * @return void
     */
    public function forceRelease()
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN);

            fclose($this->handle);

            $this->handle = null;
        }

        if (file_exists($this->path)) {
            @unlink($this->path);
        }
    }
}
