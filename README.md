# PHP Cache

[![Latest Stable Version](https://poser.pugx.org/wilkques/cache/v/stable)](https://packagist.org/packages/wilkques/cache)
[![License](https://poser.pugx.org/wilkques/cache/license)](https://packagist.org/packages/wilkques/cache)

English | [繁體中文](README_ZH.md)

A minimal file-based cache with a `put()`/`get()`/`remember()` API modeled after Laravel's cache facade (a small, hand-picked subset — not a full port; see "Differences from Laravel" below).

## Installation

`composer require wilkques/cache`

## Getting started

`Cache::make()` (and the `cache()` global helper called with no arguments) always return the **same shared instance** for the lifetime of the PHP process, resolved through `Wilkques\Container\Container`. Under a persistent worker (PHP-FPM, a long-running CLI daemon) that's the whole worker's lifetime, not just one request — relevant mainly for the `array` driver below, since the file store's actual data lives on disk regardless of which `Cache`/`File` object instance touches it.

```php
use Wilkques\Cache\Cache;

// store a value for 1 hour (default) or a custom number of seconds
Cache::put('key', 'value');
Cache::put('key', 'value', 3600);

// store a value forever
Cache::forever('key', 'value');

// retrieve a value (null if missing or expired)
Cache::get('key');

// check whether a non-expired value exists
Cache::has('key');

// get the cached value, or compute + store it if missing/expired
Cache::remember('key', 3600, function () {
    return expensive_computation();
});

// remove a single key
Cache::forgot('key');

// wipe the entire cache directory
Cache::clear();

// only store if the key doesn't already exist (or has expired)
Cache::add('key', 'value', 3600);

// get the value and remove the key in one call
Cache::pull('key');

// atomically-ish bump a numeric value, preserving its remaining TTL
Cache::increment('visits');
Cache::increment('visits', 5);
Cache::decrement('visits');

// TTL also accepts a DateInterval or a DateTime/DateTimeImmutable instant
Cache::put('key', 'value', new DateInterval('PT1H'));
Cache::put('key', 'value', new DateTime('+1 hour'));

// coordinate access to something across processes/requests
Cache::driver('file')->lock('import-job', 10)->get(function () {
    // only one process/request runs this at a time
});

// a process-local, in-memory store — see "Drivers" below
Cache::driver('array')->put('key', 'value', 60);
```

The `cache()` global helper is a shortcut for the same thing:

```php
cache('key');            // same as Cache::get('key')
cache('key', 'default');  // same as Cache::get('key', 'default')
cache();                  // same as Cache::make() — the Cache instance itself
```

## API

| Method | Description | Example |
| --- | --- | --- |
| `put($key, $value, $secord = null)` | Store a value. `$secord` is seconds until expiry — an `int`, `null` (defaults to 1 hour), a `DateInterval`, or a `DateTime`/`DateTimeImmutable`/any object exposing `getTimestamp()`. | `Cache::put('key', 'value', 3600);` |
| `forever($key, $value)` | Store a value with no expiration. | `Cache::forever('key', 'value');` |
| `get($key, $default = false)` | Retrieve a value; `null` if the key is missing or expired (see the note on `$default` below). | `Cache::get('key'); // 'value' or null` |
| `has($key)` | Whether a non-expired value exists for `$key`. | `Cache::has('key'); // bool` |
| `forgot($key)` | Remove a single key. (Named `forgot`, not `forget` — that's the actual method name.) | `Cache::forgot('key');` |
| `remember($key, $expire, $callback)` | Return the cached value if present; otherwise call `$callback`, store its return value for `$expire` seconds, and return it. Correctly distinguishes "not cached" from "cached but falsy" — caching `0`/`false`/`''`/`null`/`[]` via `remember()` won't recompute on every call. | `Cache::remember('key', 3600, function () { return compute(); });` |
| `add($key, $value, $secord = null)` | Store a value only if the key doesn't already exist (or is expired). **Not** race-condition-safe on its own (see note below) — wrap it in `lock()` if that matters. | `Cache::add('key', 'value', 3600); // bool` |
| `pull($key, $default = false)` | Retrieve a value and remove it in the same call. | `Cache::pull('key');` |
| `increment($key, $value = 1)` / `decrement($key, $value = 1)` | Add/subtract from a numeric value, preserving its remaining TTL. A missing/expired key is treated as `0`, and the result is then cached **forever** (matches Laravel's `FileStore::increment()`). | `Cache::increment('visits'); // int` |
| `lock($name, $seconds = 0, $owner = null)` | Get a `Lock` for coordinating access to `$name` across processes/requests (file store only — see "Locking" below). | `Cache::driver('file')->lock('job', 10);` |
| `restoreLock($name, $owner)` | Get a `Lock` for `$name` under a previously-generated owner token, so a different call site can release the same lock. | `Cache::driver('file')->restoreLock('job', $owner);` |
| `clear()` | Delete the entire cache directory (or, for the array driver, empty its in-memory storage). | `Cache::clear();` |
| `driver($driver = 'file')` | Resolve (and cache) a store by name — `'file'` or `'array'` (see "Drivers" below). | `Cache::driver('file');` |
| `make()` *(static)* | Resolve the shared `Cache` instance via the container. | `Cache::make();` |

> **Note on `get()`'s `$default`:** a miss or expired key still resolves internally to a `'data' => null` entry rather than an absent key, and the array lookup underneath treats a *present* `null` value as "the key exists" — so the `$default` you pass to `get()` (and `pull()`, which is built on it) is never actually returned; a miss always yields `null`. Don't rely on `$default` doing anything today.

> **Note on `add()`:** unlike Laravel's `FileStore::add()`, this itself is a plain check-then-set (`has()` followed by `put()`) — it isn't race-condition-safe against a concurrent writer between those two calls. If you need real cross-process mutual exclusion, wrap the whole operation in `lock()` instead (see "Locking" below) rather than relying on `add()` alone.

## Locking

The file store's `lock()` returns a `Lock` backed by a real `flock()` file lock (a small standalone primitive — `Wilkques\Filesystem\Filesystem` itself has no locking API, so this doesn't go through it):

```php
$lock = Cache::driver('file')->lock('import-job', 10); // held for up to 10s

// run a callback while holding the lock; always released afterwards
// (even if the callback throws), and returns the callback's result
$result = $lock->get(function () {
    return do_the_import();
});

// or acquire/release manually — release in both the success and failure
// paths, since this package's PHP 5.3 floor has no try/finally
if ($lock->acquire()) {
    try {
        do_the_import();
    } catch (\Exception $e) {
        $lock->release();

        throw $e;
    }

    $lock->release();
}
```

A lock that failed to acquire (another process already holds it) returns `false` from `acquire()`/`get()` rather than blocking or throwing — there's no wait-with-timeout polling loop like Laravel's `Lock::block()`. To let a different call site release a lock acquired elsewhere, pass its owner token to `restoreLock()`:

```php
$owner = $lock->getOwner();
// ... later, possibly in a different request ...
Cache::driver('file')->restoreLock('import-job', $owner)->release();
```

## Drivers

| Driver | `Cache::driver('...')` | Notes |
| --- | --- | --- |
| File (default) | `'file'` | Persists to disk under the configured directory (see below); shared across requests/processes. |
| Array | `'array'` | Process-local, in-memory only — never touches disk, lost when the process exits. **Not** the same as "per-request": under a persistent worker (PHP-FPM, a long-running CLI daemon) the process outlives any single request, so data written during one request is still there on the next one handled by that same worker, until the process itself restarts. Good for tests, or short-lived CLI scripts; if you need real per-request isolation under FPM, this isn't it. Supports the same `put`/`get`/`has`/`forgot`/`forever`/`add`/`pull`/`increment`/`decrement`/`remember`/`clear` API as the file store, but has no `lock()`/`restoreLock()`. |

Redis/Memcached/etc. drivers aren't implemented — see "Differences from Laravel" below.

## Configuring the storage location

The file store defaults to `./storage/cache` with no forced file permissions. To use a different directory or permission mode, reconfigure the resolved store directly:

```php
Cache::driver('file')
    ->setDirectory('/var/cache/myapp')
    ->setFilePermission(0644);
```

## Differences from Laravel's cache

This package is intentionally much smaller in scope than `Illuminate\Cache`. Compared against Laravel's real `FileStore`/`Repository` source, still notably missing:

- Redis/Memcached/DynamoDB/etc. drivers — the only drivers are `file` and the in-memory `array` (see "Drivers" above). Not implemented on purpose: this package has no way to verify a driver it can't actually connect to and run tests against.
- `lock()`'s blocking-with-timeout variant (Laravel's `Lock::block()`) — `acquire()`/`get()` here only ever try once and return `false` immediately if the lock is already held, there's no polling-wait loop.
- Cache tagging (`Cache::tags(...)`) and cache events.

If you need any of the above, reach for a full cache library instead.

## Testing

```
composer install
vendor/bin/phpunit
```

## License

MIT
