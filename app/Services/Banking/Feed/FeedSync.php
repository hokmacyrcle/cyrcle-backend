<?php

namespace App\Services\Banking\Feed;

use App\Models\BusinessBankAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Pulls new transactions for one connected account into the review list. Safe to run again and again. */
class FeedSync
{
    /** @return int how many new transactions were added */
    public static function run(BusinessBankAccount $acct, BankFeed $feed, string $connectionRef): int
    {
        $today = Carbon::now('Australia/Sydney');
        $last = DB::table('bank_transactions')->where('bank_account_id', $acct->id)->where('source', 'feed')->max('txn_date');
        // First sync reaches back 90 days. Later syncs overlap by a week so late postings are not missed.
        $since = $last ? Carbon::parse($last)->subDays(7)->toDateString() : $today->copy()->subDays(90)->toDateString();
        // Statements already imported from CSV stay as they are: the feed only adds lines after them.
        $csvEnd = DB::table('bank_transactions')->where('bank_account_id', $acct->id)->where('source', 'csv')->max('txn_date');

        $lines = $feed->transactions($connectionRef, (string) $acct->feed_reference, $since);
        $new = [];
        foreach ($lines as $l) {
            if ($csvEnd && $l['date'] <= $csvEnd) {
                continue;
            }
            $new[] = [
                'company_id' => $acct->company_id, 'bank_account_id' => $acct->id,
                'txn_date' => $l['date'], 'description' => mb_substr((string) $l['description'], 0, 500) ?: '(no description)',
                'amount' => round((float) $l['amount'], 2), 'balance_after' => $l['balance'] ?? null,
                'status' => 'review', 'fingerprint' => sha1('feed|' . $feed->name() . '|' . $l['id']),
                'source' => 'feed', 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        $before = DB::table('bank_transactions')->where('bank_account_id', $acct->id)->count();
        foreach (array_chunk($new, 500) as $chunk) {
            DB::table('bank_transactions')->insertOrIgnore($chunk);
        }
        $added = DB::table('bank_transactions')->where('bank_account_id', $acct->id)->count() - $before;

        $acct->last_synced_at = now();
        $acct->save();
        return $added;
    }
}
