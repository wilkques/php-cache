<?php

namespace Wilkques\Config\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wilkques\Cache\Cache;

class FileStoreTest extends TestCase
{
    /**
     * Several tests below reuse literal keys (e.g. '123') across methods.
     * That was always a latent cross-test collision risk since the file
     * store's data lives on disk, not in per-test PHP state — it just
     * never surfaced under PHPUnit's default defined-order execution.
     * Confirmed directly: running this suite with --order-by=random
     * reliably fails testRemember() when testPut()/testGet() (which
     * write '123' with the 1-hour default TTL) happen to run between
     * testGetExpirePass() and testRemember() — remember() then sees an
     * already-cached, not-yet-expired '123' and returns it without ever
     * writing its own 5-second TTL, so the later assertNull() after
     * sleep(6) fails.
     *
     * Fixed with an explicit Cache::clear() at the top of every test that
     * writes state, rather than a setUp() override: this suite's
     * composer.json pins "phpunit/phpunit": "*", so composer resolves a
     * different PHPUnit major depending on the PHP version running it —
     * old ones declare TestCase::setUp() with no return type, PHPUnit 10+
     * requires overrides to repeat ": void", and that's compile-time
     * syntax PHP 5.3 (this package's floor) can't parse at all. Explicit
     * per-test clear() calls sidestep the whole problem instead of
     * needing a version-dispatching TestCase subclass for it.
     */
    public function testPut()
    {
        Cache::clear();

        $this->assertTrue(
            Cache::put('123', '456')
        );
    }

    public function testGet()
    {
        Cache::clear();

        Cache::put('123', '456');

        $this->assertEquals(
            '456',
            Cache::get('123')
        );
    }

    public function testGetExpirePass()
    {
        Cache::clear();

        Cache::put('123', '456', 5);

        sleep(6);

        $this->assertNull(
            Cache::get('123')
        );
    }

    public function testRemember()
    {
        Cache::clear();

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
        Cache::clear();

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
        Cache::clear();

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
        Cache::clear();

        $this->assertTrue(
            Cache::put('huge-ttl-key', 'still-intact', 99999999999)
        );

        $this->assertEquals('still-intact', Cache::get('huge-ttl-key'));
    }

    public function testIncrementAndDecrement()
    {
        Cache::clear();

        Cache::put('counter', 5, 60);

        $this->assertEquals(6, Cache::increment('counter'));
        $this->assertEquals(6, Cache::get('counter'));

        $this->assertEquals(4, Cache::decrement('counter', 2));
        $this->assertEquals(4, Cache::get('counter'));
    }

    public function testIncrementOnMissingKeyStartsFromZero()
    {
        Cache::clear();

        $this->assertEquals(1, Cache::increment('missing-counter'));
        $this->assertEquals(1, Cache::get('missing-counter'));
    }

    public function testAddOnlySetsWhenKeyIsAbsentOrExpired()
    {
        Cache::clear();

        $this->assertTrue(Cache::add('add-key', 'first', 60));
        $this->assertFalse(Cache::add('add-key', 'second', 60));
        $this->assertEquals('first', Cache::get('add-key'));
    }

    public function testPullReturnsTheValueAndRemovesTheKey()
    {
        Cache::clear();

        Cache::put('pull-key', 'value', 60);

        $this->assertEquals('value', Cache::pull('pull-key'));
        $this->assertNull(Cache::get('pull-key'));
    }

    public function testPutAcceptsADateIntervalTtl()
    {
        Cache::clear();

        $this->assertTrue(
            Cache::put('date-interval-key', 'value', new \DateInterval('PT1H'))
        );

        $this->assertEquals('value', Cache::get('date-interval-key'));
    }

    public function testPutAcceptsADateTimeTtl()
    {
        Cache::clear();

        $future = new \DateTime('+1 hour');

        $this->assertTrue(
            Cache::put('date-time-key', 'value', $future)
        );

        $this->assertEquals('value', Cache::get('date-time-key'));
    }

    public function testPutWithAnAlreadyPastDateTimeExpiresImmediately()
    {
        Cache::clear();

        $past = new \DateTime('-1 hour');

        Cache::put('past-date-time-key', 'value', $past);

        $this->assertNull(Cache::get('past-date-time-key'));
    }

    public function testLockCanBeAcquiredAndReleased()
    {
        $lock = Cache::driver('file')->lock('my-lock', 10);

        $this->assertTrue($lock->acquire());
        $this->assertTrue($lock->release());
    }

    public function testLockCannotBeAcquiredTwiceConcurrently()
    {
        $first = Cache::driver('file')->lock('shared-lock', 10);
        $second = Cache::driver('file')->lock('shared-lock', 10);

        $this->assertTrue($first->acquire());
        $this->assertFalse($second->acquire());

        $first->release();

        $this->assertTrue($second->acquire());

        $second->release();
    }

    public function testLockGetRunsTheCallbackAndReleasesAfterwards()
    {
        $lock = Cache::driver('file')->lock('callback-lock', 10);

        $ran = false;

        $result = $lock->get(function () use (&$ran) {
            $ran = true;

            return 'callback-result';
        });

        $this->assertTrue($ran);
        $this->assertSame('callback-result', $result);

        // released by get() afterwards, so a fresh lock on the same name
        // can be acquired again immediately
        $again = Cache::driver('file')->lock('callback-lock', 10);

        $this->assertTrue($again->acquire());

        $again->release();
    }

    public function testRestoreLockUsesTheSameOwnerToken()
    {
        $lock = Cache::driver('file')->lock('restorable-lock', 10);

        $lock->acquire();

        $restored = Cache::driver('file')->restoreLock('restorable-lock', $lock->getOwner());

        $this->assertSame($lock->getOwner(), $restored->getOwner());

        $lock->release();
    }

    public function testClear()
    {
        Cache::put('123', '456');

        Cache::clear();

        $this->assertNull(Cache::get('123'));
    }
}
