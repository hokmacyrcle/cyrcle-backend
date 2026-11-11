<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BusinessBankAccount;
use App\Services\Banking\Feed\BankFeeds;
use App\Services\Banking\Feed\FeedSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Connect a business's bank through the feed provider, choose its accounts, and keep them up to date. */
class BankFeedController extends Controller
{
    use SalesSupport;

    private function notSetUp(): JsonResponse
    {
        return $this->failure('Bank connection is being set up and is not switched on yet. You can import a CSV statement meanwhile.', 503);
    }

    private function link($companyId, $id)
    {
        return DB::table('bank_feed_links')->where('company_id', $companyId)->where('id', $id)->first();
    }

    public function status(): JsonResponse
    {
        $d = BankFeeds::driver();
        return response()->json(['data' => ['configured' => (bool) ($d && $d->configured()), 'demo' => $d && $d->name() === 'demo']]);
    }

    public function institutions(Request $request): JsonResponse
    {
        $d = BankFeeds::driver();
        if (!$d || !$d->configured()) {
            return $this->notSetUp();
        }
        try {
            return response()->json(['data' => $d->institutions(trim((string) $request->query('q', '')))]);
        } catch (Throwable $e) {
            report($e);
            return $this->failure('We could not load the list of banks. Please try again.', 502);
        }
    }

    /** Step 1: the person chose a bank. Returns where to send them to sign in (or null if no sign-in step is needed). */
    public function start(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $d = BankFeeds::driver();
        if (!$d || !$d->configured()) {
            return $this->notSetUp();
        }
        $id = trim((string) $request->input('institution_id'));
        if ($id === '') {
            return $this->failure('Choose a bank.', 422, ['institution_id' => ['Choose a bank.']]);
        }
        $name = mb_substr((string) $request->input('institution_name'), 0, 150);
        try {
            $r = $d->start($id, (string) $request->input('return_url', ''));
        } catch (Throwable $e) {
            report($e);
            return $this->failure('We could not start the connection. Please try again.', 502);
        }
        $user = auth()->guard('api')->user() ?? $request->user();
        $linkId = DB::table('bank_feed_links')->insertGetId([
            'company_id' => $b->company_id, 'provider' => $d->name(), 'connection_ref' => $r['connection_ref'],
            'institution_id' => $id, 'institution_name' => $name ?: null, 'status' => 'pending',
            'created_by' => optional($user)->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['data' => ['link_id' => $linkId, 'url' => $r['url']]], 201);
    }

    /** Step 2: after the bank sign-in, list the accounts that were found. */
    public function accounts(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $link = $this->link($b->company_id, $id);
        if (!$link || $link->status === 'disconnected') {
            return $this->failure('Connection not found.', 404);
        }
        $d = BankFeeds::named($link->provider);
        if (!$d || !$d->configured()) {
            return $this->notSetUp();
        }
        try {
            $found = $d->accounts($link->connection_ref);
        } catch (Throwable $e) {
            report($e);
            return $this->failure('We could not read the accounts from your bank. Please try again.', 502);
        }
        $have = BusinessBankAccount::where('company_id', $b->company_id)->where('feed_provider', $d->name())->pluck('feed_reference')->flip();
        $list = array_map(fn ($a) => $a + ['already_connected' => isset($have[$a['ref']])], $found['accounts']);
        return response()->json(['data' => [
            'institution_name' => $link->institution_name, 'consent_expires' => $found['consent_expires'], 'accounts' => $list,
        ]]);
    }

    /** Step 3: the chosen accounts become bank accounts here, and their transactions are brought in. */
    public function connect(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $link = $this->link($b->company_id, $id);
        if (!$link || $link->status === 'disconnected') {
            return $this->failure('Connection not found.', 404);
        }
        $d = BankFeeds::named($link->provider);
        if (!$d || !$d->configured()) {
            return $this->notSetUp();
        }
        $chosen = (array) $request->input('accounts', []);
        if (!$chosen) {
            return $this->failure('Choose at least one account to connect.', 422, ['accounts' => ['Choose at least one account.']]);
        }
        try {
            $found = $d->accounts($link->connection_ref);
        } catch (Throwable $e) {
            report($e);
            return $this->failure('We could not read the accounts from your bank. Please try again.', 502);
        }
        $byRef = [];
        foreach ($found['accounts'] as $a) {
            $byRef[$a['ref']] = $a;
        }

        $added = 0; $imported = 0; $skipped = 0;
        foreach ($chosen as $c) {
            $a = $byRef[$c['ref'] ?? ''] ?? null;
            $type = $c['type'] ?? '';
            if (!$a || !isset(BusinessBankAccount::TYPES[$type])) {
                $skipped++;
                continue;
            }
            $acct = BusinessBankAccount::where('company_id', $b->company_id)->where('feed_provider', $d->name())->where('feed_reference', $a['ref'])->first();
            if ($acct) {
                $skipped++;
                continue;
            }
            $name = trim((string) ($c['name'] ?? '')) ?: ($link->institution_name . ' ' . $a['name']);
            $acct = BusinessBankAccount::create([
                'company_id' => $b->company_id, 'name' => mb_substr($name, 0, 100), 'account_type' => $type,
                'bsb' => $type === 'credit_card' ? null : ($a['bsb'] ?? null), 'account_number' => $a['mask'] ?? null, 'active' => true,
                'feed_provider' => $d->name(), 'feed_reference' => $a['ref'],
            ]);
            $acct->feed_link_id = $link->id;
            $acct->bank_balance = $a['balance'] ?? null;
            $acct->save();
            $added++;
            try {
                $imported += FeedSync::run($acct, $d, $link->connection_ref);
            } catch (Throwable $e) {
                report($e);
            }
        }
        DB::table('bank_feed_links')->where('id', $link->id)->update([
            'status' => 'connected', 'consent_expires_at' => $found['consent_expires'], 'updated_at' => now(),
        ]);
        if (!$added) {
            return $this->failure('Those accounts are already connected.', 422);
        }
        return response()->json(['data' => ['accounts_added' => $added, 'transactions' => $imported, 'consent_expires' => $found['consent_expires']],
            'message' => $added . ' account' . ($added === 1 ? '' : 's') . ' connected.'], 201);
    }

    /** The Update button: fetch the latest transactions for one connected account. */
    public function sync(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $acct = BusinessBankAccount::where('company_id', $b->company_id)->where('active', true)->find($id);
        $link = $acct && $acct->feed_link_id ? $this->link($b->company_id, $acct->feed_link_id) : null;
        if (!$acct || !$link || $link->status !== 'connected') {
            return $this->failure('This account is not connected to a bank.', 404);
        }
        $d = BankFeeds::named($link->provider);
        if (!$d || !$d->configured()) {
            return $this->notSetUp();
        }
        try {
            $n = FeedSync::run($acct, $d, $link->connection_ref);
        } catch (Throwable $e) {
            report($e);
            return $this->failure('We could not get the latest transactions. Please try again.', 502);
        }
        return response()->json(['data' => ['imported' => $n], 'message' => $n ? $n . ' new transaction' . ($n === 1 ? '' : 's') . '.' : 'Already up to date.']);
    }

    /** Stop the feed. Accounts and the transactions already brought in stay; only new ones stop arriving. */
    public function disconnect(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $link = $this->link($b->company_id, $id);
        if (!$link) {
            return $this->failure('Connection not found.', 404);
        }
        $d = BankFeeds::named($link->provider);
        try {
            if ($d && $d->configured()) {
                $d->revoke($link->connection_ref);
            }
        } catch (Throwable $e) {
            report($e);
            return $this->failure('We could not disconnect at the bank. Please try again.', 502);
        }
        DB::table('bank_feed_links')->where('id', $link->id)->update(['status' => 'disconnected', 'updated_at' => now()]);
        BusinessBankAccount::where('company_id', $b->company_id)->where('feed_link_id', $link->id)->update(['feed_provider' => null, 'feed_reference' => null, 'feed_link_id' => null]);
        return response()->json(['message' => 'Bank disconnected.']);
    }
}
