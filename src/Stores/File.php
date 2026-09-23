<?php

namespace Wilkques\Cache\Stores;

use Wilkques\Helpers\Arrays;
use Wilkques\Helpers\Strings;
use Wilkques\Filesystem\Filesystem;
use Wilkques\Cache\Concerns\InteractsWithTime;
use Wilkques\Cache\Lock;

class File
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Octal representation of the cache file permissions.
     *
     * @var int|null
     */
    protected $filePermission;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @param string $fileName
     * @param string $directory
     */
    public function __construct(Filesystem $filesystem, $directory = './storage/cache', $filePermission = null)
    {
        $this->setDirectory($directory);

        $this->setFilePermission($filePermission);

        $this->filesystem = $filesystem;
    }

    /**
     * @param string $directory
     * 
     * @return static
     */
    public function setDirectory($directory = './storage/cache')
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param int $filePermission
     * 
     * @return static
     */
    public function setFilePermission($filePermission)
    {
        $this->filePermission = $filePermission;

        return $this;
    }

    /**
     * @return int
     */
    public function getFilePermission()
    {
        return $this->filePermission;
    }

    /**
     * Get the full path for the given cache key.
     *
     * @param  string  $key
     * @return string
     */
    protected function path($key)
    {
        $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);

        return $this->getDirectory() . '/' . implode('/', $parts) . '/' . $hash;
    }

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

        $this->ensureCacheDirectoryExists($path = $this->path($key));

        $expiration = Strings::padLeft((string) $this->expiration($secord), 10, '0');

        $result = $this->filesystem->put($path, $expiration . serialize($value), true);

        return $result !== false && $result > 0;
    }

    /**
     * Store an item in the cache if the key doesn't already exist (and isn't
     * expired).
     *
     * Note: unlike Laravel's FileStore::add(), this itself is a plain
     * check-then-set — it isn't race-condition-safe against a concurrent
     * writer between the has() check and the put() call. If you need real
     * cross-process mutual exclusion, wrap the whole operation in lock()
     * instead: `$this->lock('key')->get(function () use (...) { ... });`.
     *
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
     * Create the file cache directory if necessary.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensureCacheDirectoryExists($path)
    {
        $directory = dirname($path);

        if (! $this->filesystem->exists($directory)) {
            $this->filesystem->makeDirectory($directory, 0777, true, true);

            // We're creating two levels of directories (e.g. 7e/24), so we check them both...
            $this->ensurePermissionsAreCorrect($directory);
            $this->ensurePermissionsAreCorrect(dirname($directory));
        }
    }

    /**
     * Ensure the created node has the correct permissions.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensurePermissionsAreCorrect($path)
    {
        if (
            is_null($this->getFilePermission()) ||
            intval($this->filesystem->chmod($path), 8) == $this->getFilePermission()
        ) {
            return;
        }

        $this->filesystem->chmod($path, $this->getFilePermission());
    }

    /**
     * Get a default empty payload for the cache.
     *
     * @return array
     */
    protected function emptyPayload()
    {
        return array('data' => null, 'time' => null);
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forgot($key)
    {
        if ($this->filesystem->exists($file = $this->path($key))) {
            return $this->filesystem->delete($file);
        }

        return false;
    }

    /**
     * Retrieve an item and expiry time from the cache by key.
     *
     * @param  string  $key
     * @return array
     */
    protected function getPayload($key)
    {
        $path = $this->path($key);

        // If the file doesn't exist, we obviously cannot return the cache so we will
        // just return null. Otherwise, we'll get the contents of the file and get
        // the expiration UNIX timestamps from the start of the file's contents.
        try {
            $expire = substr(
                $contents = $this->filesystem->get($path, true),
                0,
                10
            );
        } catch (\Exception $e) {
            return $this->emptyPayload();
        }

        // If the current time is greater than expiration timestamps we will delete
        // the file and return null. This helps clean up the old files and keeps
        // this directory much cleaner for us as old files aren't hanging out.
        if ($this->currentTime() >= $expire) {
            $this->forgot($key);

            return $this->emptyPayload();
        }

        try {
            $data = unserialize(substr($contents, 10));
        } catch (\Exception $e) {
            $this->forgot($key);

            return $this->emptyPayload();
        }

        // Next, we'll extract the number of seconds that are remaining for a cache
        // so that we can properly retain the time for things like the increment
        // operation that may be performed on this cache on a later operation.
        $time = $expire - $this->currentTime();

        return compact('data', 'time');
    }

    /**
     * @return int
     */
    protected function currentTime()
    {
        $dataTime = new \DateTime();

        return $dataTime->getTimestamp();
    }

    /**
     * @param string|int|null $key
     * @param mixed|false $default
     * 
     * @return mixed|false
     */
    public function get($key = null, $default = false)
    {
        return Arrays::get($this->getPayload($key), 'data', $default);
    }

    /**
     * Retrieve an item from the cache and delete it.
     *
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
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        $path = $this->path($key);

        // If the file doesn't exist, we obviously cannot return the cache so we will
        // just return null. Otherwise, we'll get the contents of the file and get
        // the expiration UNIX timestamps from the start of the file's contents.
        try {
            $expire = substr(
                $this->filesystem->get($path, true),
                0,
                10
            );
        } catch (\Exception $e) {
            return false;
        }

        // If the current time is greater than expiration timestamps we will delete
        // the file and return null. This helps clean up the old files and keeps
        // this directory much cleaner for us as old files aren't hanging out.
        if ($this->currentTime() >= $expire) {
            $this->forgot($key);

            return false;
        }

        return true;
    }

    /**
     * @param int $secords
     *
     * @return int
     */
    public function expiration($secords = null)
    {
        // A literal 0 (as opposed to omitted/null) means "forever" — this is
        // how forever() below requests it. The stored expiration timestamp is
        // always padded/read as a fixed 10-character field (see put() /
        // getPayload()), so it's capped at the largest 10-digit value instead
        // of being allowed to grow past it and corrupt that fixed-width split.
        if ($secords === 0) {
            return 9999999999;
        }

        if (!$secords) {
            $secords = 3600;
        }

        $time = time() + $secords;

        return $time > 9999999999 ? 9999999999 : $time;
    }

    /**
     * Store an item in the cache indefinitely.
     *
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
     * Increment the value of an item in the cache, preserving its remaining
     * TTL (a missing/expired key is treated as 0 and cached forever
     * afterwards — same as Laravel's FileStore::increment()).
     *
     * @param string|int $key
     * @param int $value
     *
     * @return int
     */
    public function increment($key, $value = 1)
    {
        $raw = $this->getPayload($key);

        $newValue = ((int) $raw['data']) + $value;

        // $raw['time'] is always present (even for a miss, via
        // emptyPayload()) but null in that case — Arrays::get()'s $default
        // only kicks in for an absent key, not a present null value, so the
        // null has to be handled explicitly here instead of via its 3rd arg.
        $this->put($key, $newValue, is_null($raw['time']) ? 0 : $raw['time']);

        return $newValue;
    }

    /**
     * Decrement the value of an item in the cache.
     *
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
     * @return bool
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
        $this->filesystem->deleteDirectory($this->getDirectory());
    }

    /**
     * Get a lock instance for coordinating access to $name across
     * processes/requests.
     *
     * @param string $name
     * @param int $seconds How long the lock is considered held for; 0 means
     *                     no expiry (released only via release()/
     *                     forceRelease()).
     * @param string|null $owner
     *
     * @return Lock
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        $lockDirectory = $this->getDirectory() . '/locks';

        if (!$this->filesystem->exists($lockDirectory)) {
            $this->filesystem->makeDirectory($lockDirectory, 0777, true, true);
        }

        return new Lock($lockDirectory . '/' . sha1($name), $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance for $name under a previously-generated owner
     * token, so a different call site/process can release the same lock.
     *
     * @param string $name
     * @param string $owner
     *
     * @return Lock
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}
