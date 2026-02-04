<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    protected $table = 'purchase_requests';

    protected $fillable = [
        'code',
        'client_name',
        'client_code',
        'contact_channel',
        'payment_method',
        'account_email',
        'account_password_enc',
        'store_id',
        'store_custom_name',
        'item_link',
        'item_options',
        'item_quantity',
        'quoted_total',
        'residential_charge',
        'american_card_charge',
        'notes',
        'status',
        'sent_note',
        'sent_at',
        'source_system',
        'source_reference',
        'receipt_sent_at',
        'receipt_send_error',
        'receipt_send_attempts',
    ];

    protected $casts = [
        'quoted_total' => 'decimal:2',
        'residential_charge' => 'decimal:2',
        'american_card_charge' => 'decimal:2',
        'item_quantity' => 'int',
        'sent_at' => 'datetime',
        'receipt_sent_at' => 'datetime',
        'receipt_send_attempts' => 'int',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'request_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RequestLog::class, 'request_id');
    }
}

