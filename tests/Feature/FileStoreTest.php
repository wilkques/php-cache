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

    public function testForever()
    {
        $this->assertTrue(
            Cache::forever('forever-key', 'permanent')
        );

        $this->assertEquals('permanent', Cache::get('forever-key'));
    }

    public function testHugeTtlDoesNotCorruptTheStoredExpirationTimestamp()
    {
        // Before the fix, expiration() had no overflow cap and put() never
        // padded the timestamp to a fixed width, so a TTL large enough to
        // push time()+$seconds past 9999999999 (10 digits) produced an
        // 11+ digit timestamp with nothing separating it from the
        // serialized payload that follows it. getPayload()'s fixed
        // substr($contents, 0, 10)/substr($contents, 10) split would then
        // read the wrong slice as the payload, unserialize() would fail,
        // and get() would silently return null instead of the cached
        // value — this asserts that no longer happens.
        $this->assertTrue(
            Cache::put('huge-ttl-key', 'still-intact', 99999999999)
        );

        $this->assertEquals('still-intact', Cache::get('huge-ttl-key'));
    }

    public function testIncrementAndDecrement()
    {
        Cache::put('counter', 5, 60);

        $this->assertEquals(6, Cache::increment('counter'));
        $this->assertEquals(6, Cache::get('counter'));

        $this->assertEquals(4, Cache::decrement('counter', 2));
        $this->assertEquals(4, Cache::get('counter'));
    }

    public function testIncrementOnMissingKeyStartsFromZero()
    {
        $this->assertEquals(1, Cache::increment('missing-counter'));
        $this->assertEquals(1, Cache::get('missing-counter'));
    }

    public function testAddOnlySetsWhenKeyIsAbsentOrExpired()
    {
        $this->assertTrue(Cache::add('add-key', 'first', 60));
        $this->assertFalse(Cache::add('add-key', 'second', 60));
        $this->assertEquals('first', Cache::get('add-key'));
    }

    public function testPullReturnsTheValueAndRemovesTheKey()
    {
        Cache::put('pull-key', 'value', 60);

        $this->assertEquals('value', Cache::pull('pull-key'));
        $this->assertNull(Cache::get('pull-key'));
    }

    public function testClear()
    {
        Cache::put('123', '456');

        Cache::clear();

        $this->assertNull(Cache::get('123'));
    }
}
