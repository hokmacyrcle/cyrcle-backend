<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\ChartAccount;
use App\Services\Banking\ChartDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Chart of accounts: list, add, edit, archive and restore.
 * A business gets the standard chart the first time it opens this page.
 * Accounts are archived, never deleted, so old records keep their account.
 */
class ChartAccountController extends Controller
{
    use SalesSupport;

    private function find($b, $id): ?ChartAccount
    {
        return ChartAccount::where('company_id', $b->company_id)->find($id);
    }

    private function row(ChartAccount $a, array $names): array
    {
        return [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'type' => $a->type,
            'detail' => $a->detail,
            'gst_code' => $a->gst_code,
            'gst_label' => ChartDefaults::GST[$a->gst_code][0] ?? $a->gst_code,
            'description' => $a->description,
            'parent_id' => $a->parent_id,
            'parent_name' => $a->parent_id ? ($names[$a->parent_id] ?? null) : null,
            'locked' => (bool) $a->locked,
            'active' => (bool) $a->active,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!ChartAccount::where('company_id', $b->company_id)->exists()) {
            ChartDefaults::seed((int) $b->company_id);
        }
        $all = ChartAccount::where('company_id', $b->company_id)->orderBy('code')->orderBy('name')->get();
        $names = $all->pluck('name', 'id')->all();

        return response()->json([
            'data' => $all->map(fn ($a) => $this->row($a, $names))->all(),
            'types' => ChartDefaults::types(),
            'gst_codes' => ChartDefaults::gstCodes(),
        ]);
    }

    /** Checks shared by add and edit. Returns the clean values or an error response. */
    private function checked(Request $request, $b, ?ChartAccount $current)
    {
        $locked = $current && $current->locked;
        $v = Validator::make($request->all(), [
            'code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:' . implode(',', array_keys(ChartDefaults::TYPES))],
            'detail' => ['required', 'string', 'max:40'],
            'gst_code' => ['required', 'in:' . implode(',', array_keys(ChartDefaults::GST))],
            'description' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
        ], [
            'code.regex' => 'Use letters, numbers, dots or dashes only.',
            'code.max' => 'The code can be up to 10 characters.',
            'name.required' => 'Give the account a name.',
            'type.required' => 'Choose a type.', 'type.in' => 'Choose one of the listed types.',
            'detail.required' => 'Choose a detail type.',
            'gst_code.required' => 'Choose a GST code.', 'gst_code.in' => 'Choose one of the listed GST codes.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        $d['name'] = trim(preg_replace('/\s+/', ' ', $d['name']));
        $d['code'] = isset($d['code']) && trim($d['code']) !== '' ? trim($d['code']) : null;
        $d['description'] = isset($d['description']) && trim($d['description']) !== '' ? trim($d['description']) : null;

        $errors = [];
        if ($locked && ($d['type'] !== $current->type || $d['detail'] !== $current->detail)) {
            $errors['type'] = ['This is a standard account, so its type cannot be changed.'];
        }
        if (!in_array($d['detail'], ChartDefaults::TYPES[$d['type']][1], true)) {
            $errors['detail'] = ['Choose a detail type that belongs to ' . strtolower(ChartDefaults::TYPES[$d['type']][0]) . '.'];
        }
        $same = ChartAccount::where('company_id', $b->company_id)->when($current, fn ($q) => $q->where('id', '!=', $current->id));
        if ((clone $same)->whereRaw('LOWER(name) = ?', [mb_strtolower($d['name'])])->exists()) {
            $errors['name'] = ['You already have an account with this name.'];
        }
        if ($d['code'] !== null && (clone $same)->where('code', $d['code'])->exists()) {
            $errors['code'] = ['You already use this code.'];
        }
        if (!empty($d['parent_id'])) {
            $p = $this->find($b, $d['parent_id']);
            if (!$p || ($current && $p->id === $current->id)) {
                $errors['parent_id'] = ['Choose a different parent account.'];
            } elseif ($p->type !== $d['type']) {
                $errors['parent_id'] = ['The parent account must be the same type.'];
            } elseif ($p->parent_id) {
                $errors['parent_id'] = ['Choose a main account. Sub-accounts can only go one level deep.'];
            } elseif ($current && ChartAccount::where('parent_id', $current->id)->exists()) {
                $errors['parent_id'] = ['This account already has sub-accounts, so it cannot be a sub-account.'];
            }
        } else {
            $d['parent_id'] = null;
        }
        if ($errors) {
            return $this->failure('Please fix the highlighted fields.', 422, $errors);
        }
        return $d;
    }

    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $d = $this->checked($request, $b, null);
        if ($d instanceof JsonResponse) {
            return $d;
        }
        $a = ChartAccount::create($d + ['company_id' => $b->company_id, 'locked' => false, 'active' => true]);
        $names = ChartAccount::where('company_id', $b->company_id)->pluck('name', 'id')->all();

        return response()->json(['data' => $this->row($a, $names), 'message' => 'Account added.'], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$a = $this->find($b, $id)) {
            return $this->failure('Account not found.', 404);
        }
        $d = $this->checked($request, $b, $a);
        if ($d instanceof JsonResponse) {
            return $d;
        }
        $a->update($d);
        $names = ChartAccount::where('company_id', $b->company_id)->pluck('name', 'id')->all();

        return response()->json(['data' => $this->row($a->fresh(), $names), 'message' => 'Account saved.']);
    }

    public function archive(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$a = $this->find($b, $id)) {
            return $this->failure('Account not found.', 404);
        }
        $archive = $request->boolean('archived', true);
        if ($archive && $a->locked) {
            return $this->failure('This is a standard account that Cyrcle needs, so it cannot be archived.', 422);
        }
        if ($archive && ChartAccount::where('parent_id', $a->id)->where('active', true)->exists()) {
            return $this->failure('Archive its sub-accounts first.', 422);
        }
        if (!$archive && $a->parent_id && ChartAccount::where('id', $a->parent_id)->where('active', false)->exists()) {
            return $this->failure('Restore the main account first.', 422);
        }
        $a->update(['active' => !$archive]);
        $names = ChartAccount::where('company_id', $b->company_id)->pluck('name', 'id')->all();

        return response()->json(['data' => $this->row($a->fresh(), $names), 'message' => $archive ? 'Account archived.' : 'Account restored.']);
    }
}
