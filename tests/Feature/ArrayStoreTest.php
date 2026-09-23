<?php

namespace Wilkques\Config\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wilkques\Cache\Cache;
use Wilkques\Cache\Stores\ArrayStore;

class ArrayStoreTest extends TestCase
{
    /**
     * Cache::make() is a singleton (see Cache::make()'s docblock), so the
     * ArrayStore instance — and its in-memory data — persists across every
     * test in the whole PHPUnit run, not just within one test method. Each
     * test below uses its own never-reused key name, but every test still
     * clears first anyway (same as FileStoreTest.php), as cheap insurance
     * against a future test reusing a key and silently depending on run
     * order. This is a plain method call rather than a setUp() override
     * deliberately — see FileStoreTest.php's class docblock for why
     * setUp() itself isn't safe to override in a PHP-5.3-compatible way
     * here.
     */
    public function testDriverUseArray()
    {
        Cache::driver('array')->clear();

        $this->assertTrue(
            Cache::driver('array') instanceof ArrayStore
        );
    }

    public function testPutAndGet()
    {
        $store = Cache::driver('array');

        $store->clear();

        $this->assertTrue($store->put('key', 'value', 60));
        $this->assertEquals('value', $store->get('key'));
    }

    public function testGetOnMissingKeyReturnsNull()
    {
        $store = Cache::driver('array');

        $store->clear();

        $this->assertNull($store->get('missing-key'));
    }

    public function testExpiredValueIsTreatedAsMissing()
    {
        $store = Cache::driver('array');

        $store->clear();

        $store->put('expiring-key', 'value', -1);

        $this->assertNull($store->get('expiring-key'));
        $this->assertFalse($store->has('expiring-key'));
    }

    public function testForever()
    {
        $store = Cache::driver('array');

        $store->clear();

        $this->assertTrue($store->forever('forever-key', 'permanent'));
        $this->assertEquals('permanent', $store->get('forever-key'));
    }

    public function testHas()
    {
        $store = Cache::driver('array');

        $store->clear();

        $store->put('has-key', 'value', 60);

        $this->assertTrue($store->has('has-key'));
        $this->assertFalse($store->has('missing-key'));
    }

    public function testForgot()
    {
        $store = Cache::driver('array');

        $store->clear();

        $store->put('forgot-key', 'value', 60);

        $this->assertTrue($store->forgot('forgot-key'));
        $this->assertNull($store->get('forgot-key'));
        $this->assertFalse($store->forgot('forgot-key'));
    }

    public function testAddOnlySetsWhenKeyIsAbsentOrExpired()
    {
        $store = Cache::driver('array');

        $store->clear();

        $this->assertTrue($store->add('add-key', 'first', 60));
        $this->assertFalse($store->add('add-key', 'second', 60));
        $this->assertEquals('first', $store->get('add-key'));
    }

    public function testPullReturnsTheValueAndRemovesTheKey()
    {
        $store = Cache::driver('array');

        $store->clear();

        $store->put('pull-key', 'value', 60);

        $this->assertEquals('value', $store->pull('pull-key'));
        $this->assertNull($store->get('pull-key'));
    }

    public function testIncrementAndDecrement()
    {
        $store = Cache::driver('array');

        $store->clear();

        $store->put('counter', 5, 60);

        $this->assertEquals(6, $store->increment('counter'));
        $this->assertEquals(6, $store->get('counter'));

        $this->assertEquals(4, $store->decrement('counter', 2));
        $this->assertEquals(4, $store->get('counter'));
    }

    public function testIncrementOnMissingKeyStartsFromZero()
    {
        $store = Cache::driver('array');

        $store->clear();

        $this->assertEquals(1, $store->increment('missing-counter'));
        $this->assertEquals(1, $store->get('missing-counter'));
    }

    public function testRememberWithFalsyValueDoesNotRecomputeEachCall()
    {
        $store = Cache::driver('array');

        $store->clear();

        $calls = 0;

        $first = $store->remember('falsy-key', 60, function () use (&$calls) {
            $calls++;

            return 0;
        });

        $second = $store->remember('falsy-key', 60, function () use (&$calls) {
            $calls++;

            return 999;
        });

        $this->assertSame(0, $first);
        $this->assertSame(0, $second);
        $this->assertEquals(1, $calls);
    }

    public function testPutAcceptsADateIntervalTtl()
    {
        $store = Cache::driver('array');

        $store->clear();

        $this->assertTrue(
            $store->put('date-interval-key', 'value', new \DateInterval('PT1H'))
        );

        $this->assertEquals('value', $store->get('date-interval-key'));
    }

    public function testClear()
    {
        $store = Cache::driver('array');

        $store->put('clear-key', 'value', 60);

        $store->clear();

        $this->assertNull($store->get('clear-key'));
    }
}
