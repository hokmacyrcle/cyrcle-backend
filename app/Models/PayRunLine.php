<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayRunLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tax_overridden' => 'boolean', 'tax_confirmed' => 'boolean',
    ];
}
