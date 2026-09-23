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
        $data = Cache::remember('123', 5, function () {
            return '456';
        });

        $this->assertEquals('456', $data);

        $this->assertEquals('456', Cache::get('123'));

        sleep(6);

        $this->assertNull(Cache::get('123'));
    }

    public function testRememberWithFalsyValueDoesNotRecomputeEachCall()
    {
        $calls = 0;

        $first = Cache::remember('falsy-key', 5, function () use (&$calls) {
            $calls++;

            return 0;
        });

        $second = Cache::remember('falsy-key', 5, function () use (&$calls) {
            $calls++;

            return 999;
        });

        $this->assertSame(0, $first);
        $this->assertSame(0, $second);
        $this->assertEquals(1, $calls);
    }

    public function testClear()
    {
        Cache::put('123', '456');

        Cache::clear();

        $this->assertNull(Cache::get('123'));
    }
}
