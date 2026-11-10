<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'country_code',
        'subscription_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Relationships
    // Company relationship removed - companies are now linked via user_id in companies table

    /**
     * Get the company ID from the current token
     * Now uses cache-based approach for simplicity
     */
    public function getCompanyIdFromToken()
    {
        try {
            $token = request()->bearerToken();
            if ($token) {
                return \Cache::get("user_company_{$token}");
            }
        } catch (\Exception $e) {
            \Log::error('Error getting company ID from token: ' . $e->getMessage());
        }
        
        return null;
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_id', 'plan_id');
    }
}
