<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dropdown extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'parent_id',
    ];

    // Self-referencing relationship for hierarchical dropdowns
    public function parent()
    {
        return $this->belongsTo(Dropdown::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Dropdown::class, 'parent_id');
    }

    // Scope to get dropdowns by type
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Scope to get only parent items (no parent_id)
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    // Scope to get only child items (has parent_id)
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }
}
