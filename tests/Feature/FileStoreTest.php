<?php

namespace Wilkques\Config\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wilkques\Cache\Cache;

class FileStoreTest extends TestCase
{
    /**
     * @param Config $config
     */
    public function testPut()
    {
        $this->assertTrue(
            Cache::put('123', '456')
        );
    }

    public function testGet()
    {
        Cache::put('123', '456');

        $this->assertEquals(
            '456',
            Cache::get('123')
        );
    }

    public function testGetExpirePass()
    {
        Cache::put('123', '456', 5);

        sleep(6);

        $this->assertNull(
            Cache::get('123')
        );
    }

    public function testRemember()
    {
        $data = Cache::remember('123', 5, '456');

        $this->assertEquals('456', $data);

        sleep(6);

        $this->assertNull($data);
    }
}
