<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailCode;
use App\Models\Company;
use App\Rules\ValidAbn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * Steps 2-4 of firm sign-up (step 1 is AuthController::register).
 * All routes are behind auth:api. Same response shape as the rest of the Auth controllers.
 */
class FirmSignupController extends Controller
{
    private const CODE_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function sendCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return $this->ok('Email already verified', ['email_verified' => true]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('email_verification_codes')->where('email', $user->email)->delete();
        DB::table('email_verification_codes')->insert([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new VerifyEmailCode($code, $user->first_name));
        } catch (\Throwable $e) {
            report($e);
            return $this->fail(500, 'We could not send the code. Please try again.');
        }

        return $this->ok('Code sent', ['email' => $user->email, 'expires_in_minutes' => self::CODE_MINUTES]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return $this->ok('Email already verified', ['email_verified' => true]);
        }

        $v = Validator::make($request->all(), ['code' => ['required', 'digits:6']]);
        if ($v->fails()) {
            return $this->fail(400, 'Enter the 6-digit code', $v->errors());
        }

        $row = DB::table('email_verification_codes')->where('email', $user->email)->first();
        if (!$row || Carbon::parse($row->expires_at)->isPast()) {
            return $this->fail(400, 'That code has expired. Ask for a new one.');
        }
        if ($row->attempts >= self::MAX_ATTEMPTS) {
            return $this->fail(429, 'Too many attempts. Ask for a new code.');
        }
        if (!Hash::check($request->code, $row->code_hash)) {
            DB::table('email_verification_codes')->where('id', $row->id)->increment('attempts');
            return $this->fail(400, 'That code is not right. Check it and try again.');
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        DB::table('email_verification_codes')->where('email', $user->email)->delete();

        return $this->ok('Email verified', ['email_verified' => true]);
    }

    public function registerFirm(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->email_verified_at) {
            return $this->fail(403, 'Verify your email first.');
        }
        if (Company::where('user_id', $user->id)->where('company_type', 'Subscriber')->exists()) {
            return $this->fail(409, 'You have already registered a firm.');
        }

        $v = Validator::make($request->all(), [
            'firm_name' => ['required', 'string', 'max:255'],
            'abn' => ['required', new ValidAbn],
            'acn' => ['nullable', 'regex:/^\d{9}$/'],
            'state' => ['required', 'in:NSW,VIC,QLD,WA,SA,TAS,ACT,NT'],
            'address_line' => ['required', 'string', 'max:255'],
            'gst_registered' => ['boolean'],
            'client_count_band' => ['required', 'in:1-10,11-50,51-200,200+'],
            'staff_count_band' => ['required', 'in:1-3,4-10,11-25,25+'],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'You need to accept the Terms and Privacy Policy.',
            'acn.regex' => 'ACN must be 9 digits.',
        ]);
        if ($v->fails()) {
            return $this->fail(400, 'Validation failed', $v->errors());
        }

        $abn = preg_replace('/\s+/', '', $request->abn);
        $acn = $request->acn ? preg_replace('/\s+/', '', $request->acn) : null;

        $company = DB::transaction(function () use ($request, $user, $abn, $acn) {
            $company = Company::create([
                'company_name' => $request->firm_name,
                'company_abn' => $abn,
                'company_acn' => $acn,
                'gst_registered' => (bool) $request->gst_registered,
                'company_type' => 'Subscriber',
                'user_id' => $user->id,
            ]);
            // status/state/etc. are not mass-assignable on purpose: set explicitly.
            $company->forceFill([
                'status' => 'pending',
                'state' => $request->state,
                'address_line' => $request->address_line,
                'client_count_band' => $request->client_count_band,
                'staff_count_band' => $request->staff_count_band,
                'terms_accepted_at' => now(),
            ])->save();
            return $company;
        });

        return $this->ok('Request received', [
            'firm_status' => 'pending',
            'company_id' => $company->company_id,
        ], 201);
    }

    private function ok(string $message, array $data = [], int $code = 200): JsonResponse
    {
        return response()->json(['status' => true, 'status_code' => $code, 'message' => $message, 'data' => $data], 200);
    }

    private function fail(int $code, string $message, $errors = null): JsonResponse
    {
        // The existing auth endpoints return HTTP 200 with status_code in the body; keep that contract.
        return response()->json(['status' => false, 'status_code' => $code, 'message' => $message, 'errors' => $errors], 200);
    }
}
