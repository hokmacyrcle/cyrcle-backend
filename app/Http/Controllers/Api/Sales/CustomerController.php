<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Contact;
use App\Rules\ValidAbn;
use App\Services\Sales\CustomerInsights;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Customers are the people and businesses you invoice. They live in the contacts table,
 * limited to the business the user is working in. Suppliers are not shown here.
 */
class CustomerController extends Controller
{
    use SalesSupport;

    private const STATES = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];

    private function customers(int $companyId)
    {
        return Contact::where('company_id', $companyId)->where(function ($w) {
            $w->whereNull('contact_category')->orWhereNotIn('contact_category', ['Supplier', 'Vendor']);
        });
    }

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $q = $this->customers((int) $b->company_id)
            ->where('contact_status', $request->query('status') === 'archived' ? 'Inactive' : 'Active');

        if ($s = trim((string) $request->query('search', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('contact_first_name', 'like', "%{$s}%")
                    ->orWhere('contact_last_name', 'like', "%{$s}%")
                    ->orWhere('contact_company_name', 'like', "%{$s}%")
                    ->orWhere('contact_email_address', 'like', "%{$s}%")
                    ->orWhere('contact_abn', 'like', "%{$s}%");
            });
        }

        // The invoice and quote forms ask for the whole list in one go (no paging) to fill the customer picker.
        if ($request->boolean('all')) {
            $rows = $q->orderBy('contact_company_name')->orderBy('contact_first_name')->limit(500)->get()
                ->map(fn (Contact $c) => ['id' => $c->contact_id, 'name' => $this->contactName($c), 'abn' => $c->contact_abn])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
            return response()->json(['data' => $rows]);
        }

        $page = $q->orderBy('contact_company_name')->orderBy('contact_first_name')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 50));

        $insights = (new CustomerInsights())->forContacts((int) $b->company_id, $page->getCollection()->pluck('contact_id')->all());

        return response()->json([
            'data' => $page->getCollection()->map(fn (Contact $c) => $this->row($c) + ['payment' => $insights[$c->contact_id] ?? null])->values(),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    private function row(Contact $c): array
    {
        return [
            'id' => $c->contact_id,
            'name' => $this->contactName($c),
            'type' => $c->contact_type,
            'abn' => $c->contact_abn,
            'email' => $c->contact_email_address,
            'phone' => $c->contact_phone_number ?: $c->contact_mobile_number,
            'status' => $c->contact_status === 'Inactive' ? 'archived' : 'active',
        ];
    }

    public function show($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $c = $this->customers((int) $b->company_id)->where('contact_id', $id)->first();
        if (!$c) {
            return $this->failure('Customer not found.', 404);
        }
        return response()->json(['data' => $this->full($c)]);
    }

    private function full(Contact $c): array
    {
        return $this->row($c) + [
            'first_name' => $c->contact_first_name,
            'last_name' => $c->contact_last_name,
            'business_name' => $c->contact_company_name,
            'mobile' => $c->contact_mobile_number,
            'address_line' => $c->contact_address_line_1,
            'suburb' => $c->contact_city,
            'state' => $c->contact_state,
            'postcode' => $c->contact_post_code,
        ];
    }

    private function rules(Request $request): array
    {
        $type = $request->input('type');
        return [
            'type' => ['required', 'in:Individual,Company,Sole Trader'],
            'business_name' => [$type === 'Company' ? 'required' : 'nullable', 'string', 'max:255'],
            'first_name' => [$type === 'Company' ? 'nullable' : 'required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'abn' => ['nullable', new ValidAbn],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'regex:/^[0-9 +()\-]{8,20}$/'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'suburb' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'in:' . implode(',', self::STATES)],
            'postcode' => ['nullable', 'digits:4'],
        ];
    }

    private function messages(): array
    {
        return [
            'type.required' => 'Choose who you are invoicing.',
            'business_name.required' => 'Enter the business name.',
            'first_name.required' => 'Enter a first name.',
            'email.email' => 'Enter a valid email address.',
            'phone.regex' => 'Enter a valid phone number.',
            'state.in' => 'Choose a state or territory.',
            'postcode.digits' => 'Postcode must be 4 digits.',
        ];
    }

    private function apply(Contact $c, Request $r): void
    {
        $c->contact_type = $r->type;
        $c->contact_company_name = $r->business_name ?: null;
        $c->contact_first_name = $r->type === 'Company' ? ($r->first_name ?: null) : $r->first_name;
        $c->contact_last_name = $r->last_name ?: null;
        $c->contact_abn = $r->abn ? preg_replace('/\s+/', '', $r->abn) : null;
        $c->contact_email_address = $r->email ?: null;
        $c->contact_phone_number = $r->phone ?: null;
        $c->contact_address_line_1 = $r->address_line ?: null;
        $c->contact_city = $r->suburb ?: null;
        $c->contact_state = $r->state ?: null;
        $c->contact_post_code = $r->postcode ?: null;
        $c->contact_country = 'Australia';
    }

    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $v = Validator::make($request->all(), $this->rules($request), $this->messages());
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $c = new Contact();
        $c->company_id = $b->company_id;
        $c->contact_status = 'Active';
        $c->contact_category = null;
        $this->apply($c, $request);
        $c->save();
        return response()->json(['data' => $this->full($c), 'message' => 'Customer added.'], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $c = $this->customers((int) $b->company_id)->where('contact_id', $id)->first();
        if (!$c) {
            return $this->failure('Customer not found.', 404);
        }
        $v = Validator::make($request->all(), $this->rules($request), $this->messages());
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $this->apply($c, $request);
        $c->save();
        return response()->json(['data' => $this->full($c), 'message' => 'Customer saved.']);
    }

    /** Customers are archived, not deleted, so old invoices keep their customer. */
    public function setArchived(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $c = $this->customers((int) $b->company_id)->where('contact_id', $id)->first();
        if (!$c) {
            return $this->failure('Customer not found.', 404);
        }
        $archive = $request->boolean('archived', true);
        $c->contact_status = $archive ? 'Inactive' : 'Active';
        $c->save();
        return response()->json(['data' => $this->row($c), 'message' => $archive ? 'Customer archived.' : 'Customer restored.']);
    }
}
