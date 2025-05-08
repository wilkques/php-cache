# File System

[![Latest Stable Version](https://poser.pugx.org/wilkques/cache/v/stable)](https://packagist.org/packages/wilkques/cache)
[![License](https://poser.pugx.org/wilkques/cache/license)](https://packagist.org/packages/wilkques/cache)

## Installation
`composer require wilkques/cache`

## How to use
```php
// create cache
\Wilkques\Cache\Cache::put('<key>', '<value>', '<expire secord>');

// get cache
$resolve = \Wilkques\Cache\Cache::get('<key>');

var_dump(
    $resolve
);
```