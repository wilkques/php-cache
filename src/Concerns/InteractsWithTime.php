<?php

namespace Wilkques\Cache\Concerns;

/**
 * A plain static helper rather than a trait: traits are PHP 5.4+ only, and
 * this package's floor is PHP 5.3 (confirmed against the real 5.3.10
 * interpreter — a trait here fails to parse at all on it).
 */
class InteractsWithTime
{
    /**
     * Convert a TTL into a whole number of seconds from now.
     *
     * Accepts a plain int/null (unchanged — the existing "seconds, or
     * null for the driver's default" behavior), a \DateInterval, or any
     * DateTime-like object exposing getTimestamp() (\DateTime,
     * \DateTimeImmutable, or — on PHP 5.5+ — anything implementing
     * \DateTimeInterface).
     *
     * Deliberately duck-typed via method_exists() rather than
     * `instanceof \DateTimeInterface`: that interface doesn't exist
     * before PHP 5.5, and this package's floor is PHP 5.3. `instanceof`
     * against an unresolvable class name is safe in PHP (evaluates to
     * false, no autoload/fatal), so this isn't a correctness issue on
     * 5.3 either way — the duck-typed check is just clearer about why.
     *
     * @param int|\DateInterval|\DateTime|null $ttl
     *
     * @return int|null
     */
    public static function resolveSeconds($ttl)
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            $now = new \DateTime();

            $future = clone $now;

            $future->add($ttl);

            return $future->getTimestamp() - $now->getTimestamp();
        }

        if (is_object($ttl) && method_exists($ttl, 'getTimestamp')) {
            return $ttl->getTimestamp() - time();
        }

        return (int) $ttl;
    }
}
