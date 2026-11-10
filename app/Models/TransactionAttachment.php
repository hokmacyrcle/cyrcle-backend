<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionAttachment extends Model
{
    use HasFactory;

    protected $primaryKey = 'attachment_id';

    protected $fillable = [
        'transaction_id',
        'file_name',
        'original_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Relationships
    public function transaction()
    {
        return $this->belongsTo(InvoiceTransaction::class, 'transaction_id', 'transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessors
    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }
}
