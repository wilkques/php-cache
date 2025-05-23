<?php

namespace Wilkques\Config\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wilkques\Cache\Cache;
use Wilkques\Cache\Stores\File;

class CacheTest extends TestCase
{
    /**
     * @param Config $config
     */
    public function testDriverUseFile()
    {
        $this->assertTrue(
            Cache::driver() instanceof File
        );
    }
}