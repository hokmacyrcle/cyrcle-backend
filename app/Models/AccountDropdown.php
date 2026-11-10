<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountDropdown extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'parent_id',
        'company_id',
    ];

    /**
     * Scope to get by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
