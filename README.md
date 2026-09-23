# PHP Cache

[![Latest Stable Version](https://poser.pugx.org/wilkques/cache/v/stable)](https://packagist.org/packages/wilkques/cache)
[![License](https://poser.pugx.org/wilkques/cache/license)](https://packagist.org/packages/wilkques/cache)

A minimal file-based cache with a `put()`/`get()`/`remember()` API modeled after Laravel's cache facade (a small, hand-picked subset — not a full port; see "Differences from Laravel" below).

## Installation

`composer require wilkques/cache`

## Getting started

`Cache::make()` (and the `cache()` global helper called with no arguments) always return the **same shared instance**, resolved through `Wilkques\Container\Container`.

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
| `put($key, $value, $secord = null)` | Store a value. `$secord` is seconds until expiry; omitted/`null` defaults to 1 hour. | `Cache::put('key', 'value', 3600);` |
| `forever($key, $value)` | Store a value with no expiration. | `Cache::forever('key', 'value');` |
| `get($key, $default = false)` | Retrieve a value; `null` if the key is missing or expired (see the note on `$default` below). | `Cache::get('key'); // 'value' or null` |
| `has($key)` | Whether a non-expired value exists for `$key`. | `Cache::has('key'); // bool` |
| `forgot($key)` | Remove a single key. (Named `forgot`, not `forget` — that's the actual method name.) | `Cache::forgot('key');` |
| `remember($key, $expire, $callback)` | Return the cached value if present; otherwise call `$callback`, store its return value for `$expire` seconds, and return it. Correctly distinguishes "not cached" from "cached but falsy" — caching `0`/`false`/`''`/`null`/`[]` via `remember()` won't recompute on every call. | `Cache::remember('key', 3600, function () { return compute(); });` |
| `clear()` | Delete the entire cache directory. | `Cache::clear();` |
| `driver($driver = 'file')` | Resolve (and cache) a store by name. Only `'file'` is currently implemented. | `Cache::driver('file');` |
| `make()` *(static)* | Resolve the shared `Cache` instance via the container. | `Cache::make();` |

> **Note on `get()`'s `$default`:** a miss or expired key still resolves internally to a `'data' => null` entry rather than an absent key, and the array lookup underneath treats a *present* `null` value as "the key exists" — so the `$default` you pass to `get()` is never actually returned; a miss always yields `null`. Don't rely on `$default` doing anything today.

## Configuring the storage location

The file store defaults to `./storage/cache` with no forced file permissions. To use a different directory or permission mode, reconfigure the resolved store directly:

```php
Cache::driver('file')
    ->setDirectory('/var/cache/myapp')
    ->setFilePermission(0644);
```

## Differences from Laravel's cache

This package is intentionally much smaller in scope than `Illuminate\Cache`. Compared against Laravel's real `FileStore`/`Repository` source, notably missing:

- `increment()` / `decrement()`
- `add()` (atomic put-if-absent, with file locking)
- `pull()` (get a value and remove it in one call)
- Any locking primitives (`lock()`, `restoreLock()`)
- `DateTimeInterface`/`DateInterval` TTLs — only raw seconds are accepted
- Any driver besides the file store (no Redis/Memcached/array/etc.)

If you need any of the above, reach for a full cache library instead — this package covers the common `put`/`get`/`has`/`remember`/`forever` cases and nothing more.

## Testing

```
composer install
vendor/bin/phpunit
```

## License

MIT
