<?php

namespace Wilkques\Cache\Stores;

use Wilkques\Helpers\Arrays;
use Wilkques\Filesystem\Filesystem;

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
     * @param int|null $secord
     * 
     * @return bool
     */
    public function put($key, $value, $secord = null)
    {
        $this->ensureCacheDirectoryExists($path = $this->path($key));

        $result = $this->filesystem->put($path, $this->expiration($secord) . serialize($value), true);

        return $result !== false && $result > 0;
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
        if (is_null($this->getFilePermission()) ||
            intval($this->filesystem->chmod($path), 8) == $this->getFilePermission()) {
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
     * 
     * @return mixed|false
     */
    public function get($key = null)
    {
        return Arrays::get($this->getPayload($key), 'data');
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
        if (!$secords) {
            $secords = 3600;
        }

        return time() + $secords;
    }

    /**
     * @param string $key
     * @param int $expire
     * @param callback $callback
     * 
     * @return bool
     */
    public function remember($key, $expire, $callback)
    {
        if ($cache = $this->get($key)) {
            return $cache;
        }
        
        $this->put($key, $callback, $expire);

        return $this->get($key);
    }
}
