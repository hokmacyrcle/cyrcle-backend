<?php

namespace App\Services\Banking\Feed;

/** Picks the bank feed provider named in config/banking.php. Returns null when the feed is switched off. */
class BankFeeds
{
    public static function driver(): ?BankFeed
    {
        switch (config('banking.feed_driver')) {
            case 'acsiss': return new AcsissFeed();
            case 'demo': return new DemoFeed();
            default: return null;
        }
    }

    /** Opens the provider that made an existing connection, even if the setting has since changed. */
    public static function named(string $name): ?BankFeed
    {
        $d = self::driver();
        if ($d && $d->name() === $name) return $d;
        return $name === 'acsiss' ? new AcsissFeed() : ($name === 'demo' ? new DemoFeed() : null);
    }
}
