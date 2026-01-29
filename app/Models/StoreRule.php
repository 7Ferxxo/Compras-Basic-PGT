<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreRule extends Model
{
    protected $table = 'store_rules';

    protected $primaryKey = 'store_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'requires_residential_address',
        'residential_fee_per_item',
        'requires_american_card',
        'american_card_surcharge_rate',
    ];

    protected $casts = [
        'requires_residential_address' => 'bool',
        'residential_fee_per_item' => 'decimal:2',
        'requires_american_card' => 'bool',
        'american_card_surcharge_rate' => 'decimal:4',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}

