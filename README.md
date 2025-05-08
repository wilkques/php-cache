# File System

[![Latest Stable Version](https://poser.pugx.org/wilkques/cache/v/stable)](https://packagist.org/packages/wilkques/cache)
[![License](https://poser.pugx.org/wilkques/cache/license)](https://packagist.org/packages/wilkques/cache)

## Installation
`composer require wilkques/cache`

## How to use
```php
$cache = new \Wilkques\Cache\Cache;

// create cache
$cache->put('<key>', '<value>', '<expire secord>');

// get cache
$resolve = $cache->get('<key>');

var_dump(
    $resolve
);
```