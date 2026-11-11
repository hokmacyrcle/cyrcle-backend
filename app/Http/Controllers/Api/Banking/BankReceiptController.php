<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankReceipt;
use App\Models\BusinessBankAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Receipts: upload photos or PDFs, add the details, view or delete them.
 * Files are kept on the private disk and only given out through view(), which checks the business.
 */
class BankReceiptController extends Controller
{
    use SalesSupport;

    private const TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];

    private function find($b, $id): ?BankReceipt
    {
        return BankReceipt::where('company_id', $b->company_id)->find($id);
    }

    private function row(BankReceipt $r, $accounts, $people): array
    {
        return [
            'id' => $r->id,
            'file_name' => $r->original_name,
            'is_image' => str_starts_with($r->mime, 'image/'),
            'size' => (int) $r->size,
            'description' => $r->description,
            'date' => $r->receipt_date ? $r->receipt_date->toDateString() : null,
            'amount' => $r->amount === null ? null : $this->money($r->amount),
            'gst' => $r->gst === null ? null : $this->money($r->gst),
            'account_id' => $r->bank_account_id,
            'account_name' => $r->bank_account_id ? ($accounts[$r->bank_account_id] ?? null) : null,
            'created_by' => $r->uploaded_by ? ($people[$r->uploaded_by] ?? null) : null,
            'created_at' => $r->created_at ? $r->created_at->toIso8601String() : null,
        ];
    }

    private function names($rows): array
    {
        $b = $this->business();
        $accounts = BusinessBankAccount::where('company_id', $b->company_id)->pluck('name', 'id')->all();
        $ids = collect($rows)->pluck('uploaded_by')->filter()->unique()->all();
        $people = [];
        foreach (User::whereIn('id', $ids)->get() as $u) {
            $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            $people[$u->id] = $name !== '' ? $name : ($u->name ?: $u->email);
        }
        return [$accounts, $people];
    }

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $q = BankReceipt::where('company_id', $b->company_id);
        if ($s = trim((string) $request->query('search', ''))) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $s) . '%';
            $q->where(fn ($w) => $w->where('description', 'like', $like)->orWhere('original_name', 'like', $like));
        }
        $page = $q->orderByDesc('created_at')->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 25), 1), 100));
        [$accounts, $people] = $this->names($page->items());

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => $this->row($r, $accounts, $people))->all(),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** Upload one or more files. Each file becomes a receipt; details can be added afterwards. */
    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $v = Validator::make($request->all(), [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:10240', 'mimes:' . implode(',', self::TYPES)],
        ], [
            'files.required' => 'Choose at least one receipt.',
            'files.max' => 'Upload up to 20 receipts at a time.',
            'files.*.max' => 'Each receipt must be under 10 MB.',
            'files.*.mimes' => 'Receipts can be PDF, JPG, PNG, WEBP or HEIC files.',
            'files.*.file' => 'That file could not be read. Try again.',
        ]);
        if ($v->fails()) {
            return $this->failure('Some files could not be uploaded.', 422, $v->errors()->toArray());
        }
        $user = auth()->guard('api')->user() ?? $request->user();
        $made = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store('receipts/' . $b->company_id, 'local');   // private disk, not web-readable
            $made[] = BankReceipt::create([
                'company_id' => $b->company_id,
                'uploaded_by' => optional($user)->id,
                'file_path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }
        [$accounts, $people] = $this->names($made);
        $n = count($made);

        return response()->json([
            'data' => collect($made)->map(fn ($r) => $this->row($r, $accounts, $people))->all(),
            'message' => $n === 1 ? 'Receipt uploaded.' : "$n receipts uploaded.",
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$r = $this->find($b, $id)) {
            return $this->failure('Receipt not found.', 404);
        }
        $v = Validator::make($request->all(), [
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'gst' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'account_id' => ['nullable', 'integer'],
        ], ['date.date' => 'Enter a valid date.', 'amount.numeric' => 'Enter the amount as a number.', 'gst.numeric' => 'Enter the GST as a number.']);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        if (isset($d['amount'], $d['gst']) && (float) $d['gst'] > (float) $d['amount']) {
            return $this->failure('Please fix the highlighted fields.', 422, ['gst' => ['GST cannot be more than the total.']]);
        }
        if (!empty($d['account_id']) && !BusinessBankAccount::where('company_id', $b->company_id)->where('id', $d['account_id'])->exists()) {
            return $this->failure('Please fix the highlighted fields.', 422, ['account_id' => ['Choose one of your accounts.']]);
        }
        $r->update([
            'description' => isset($d['description']) && trim($d['description']) !== '' ? trim($d['description']) : null,
            'receipt_date' => $d['date'] ?? null,
            'amount' => $d['amount'] ?? null,
            'gst' => $d['gst'] ?? null,
            'bank_account_id' => $d['account_id'] ?? null,
        ]);
        [$accounts, $people] = $this->names([$r]);

        return response()->json(['data' => $this->row($r->fresh(), $accounts, $people), 'message' => 'Receipt saved.']);
    }

    /** Send the file itself, for viewing in the browser. */
    public function view(Request $request, $id)
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!($r = $this->find($b, $id)) || !Storage::disk('local')->exists($r->file_path)) {
            return $this->failure('That receipt file is not available.', 404);
        }
        return Storage::disk('local')->response($r->file_path, $r->original_name, [
            'Content-Type' => $r->mime,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function destroy($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$r = $this->find($b, $id)) {
            return $this->failure('Receipt not found.', 404);
        }
        Storage::disk('local')->delete($r->file_path);
        $r->delete();
        return response()->json(['message' => 'Receipt deleted.']);
    }
}
