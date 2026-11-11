<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartAccount extends Model
{
    protected $table = 'chart_accounts';

    protected $fillable = ['company_id', 'code', 'name', 'type', 'detail', 'gst_code', 'description', 'parent_id', 'locked', 'active'];

    protected $casts = ['locked' => 'boolean', 'active' => 'boolean'];
}
