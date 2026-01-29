<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAttachment extends Model
{
    protected $table = 'request_attachments';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'type',
        'original_name',
        'stored_name',
        'mime_type',
        'size_bytes',
        'uploaded_at',
    ];

    protected $casts = [
        'size_bytes' => 'int',
        'uploaded_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }
}

