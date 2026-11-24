<?php

namespace App\Services\Banking\Feed;

use Carbon\Carbon;

/**
 * A simulated bank, ONLY for showing the connect screens before the real provider is ready.
 * Everything it returns is made up. Switch it on with BANK_FEED_DRIVER=demo; never in production.
 */
class DemoFeed implements BankFeed
{
    private const BANKS = [
        'cba' => 'Commonwealth Bank', 'anz' => 'ANZ', 'westpac' => 'Westpac', 'nab' => 'NAB',
        'stgeorge' => 'St.George Bank', 'bankwest' => 'Bankwest', 'macquarie' => 'Macquarie Bank', 'bendigo' => 'Bendigo Bank',
    ];

    private const SHOPS = ['COFFEE CLUB', 'OFFICEWORKS', 'TELSTRA DIRECT DEBIT', 'UBER TRIP', 'WOOLWORTHS', 'BUNNINGS', 'ADOBE SYSTEMS', 'QANTAS AIRWAYS', 'SHELL COLES EXPRESS', 'AMAZON AU'];

    public function name(): string { return 'demo'; }
    public function configured(): bool { return true; }

    public function institutions(string $search): array
    {
        $out = [];
        foreach (self::BANKS as $id => $name) {
            if ($search === '' || stripos($name, $search) !== false) {
                $out[] = ['id' => $id, 'name' => $name];
            }
        }
        return $out;
    }

    public function start(string $institutionId, string $returnUrl): array
    {
        return ['connection_ref' => 'demo-' . $institutionId . '-' . bin2hex(random_bytes(4)), 'url' => null];
    }

    public function accounts(string $connectionRef): array
    {
        return [
            'consent_expires' => Carbon::now('Australia/Sydney')->addDays(90)->toDateString(),
            'accounts' => [
                ['ref' => $connectionRef . ':1', 'name' => 'Business Everyday', 'mask' => '4821', 'bsb' => '000-000', 'type' => 'everyday', 'balance' => 5384.55],
                ['ref' => $connectionRef . ':2', 'name' => 'Business Savings', 'mask' => '9930', 'bsb' => '000-000', 'type' => 'savings', 'balance' => 12000.00],
            ],
        ];
    }

    public function transactions(string $connectionRef, string $accountRef, string $since): array
    {
        mt_srand(crc32($accountRef));
        $today = Carbon::now('Australia/Sydney');
        $lines = [];
        for ($i = 0; $i < 24; $i++) {
            $date = $today->copy()->subDays(1 + $i * 2 + mt_rand(0, 1))->toDateString();
            $in = $i % 7 === 3;
            $amount = $in ? mt_rand(40000, 250000) / 100 : -mt_rand(500, 30000) / 100;
            $desc = $in ? 'DEPOSIT CUSTOMER ' . mt_rand(1000, 9999) : self::SHOPS[mt_rand(0, count(self::SHOPS) - 1)];
            $lines[] = ['id' => $accountRef . '#' . $i, 'date' => $date, 'description' => $desc, 'amount' => $amount, 'balance' => null];
        }
        mt_srand();
        return array_values(array_filter($lines, fn ($l) => $l['date'] >= $since));
    }

    public function revoke(string $connectionRef): void { }
}
