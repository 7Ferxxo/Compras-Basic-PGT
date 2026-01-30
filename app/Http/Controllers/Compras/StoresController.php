<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Store;

class StoresController extends Controller
{
    public function index()
    {
        $items = Store::query()
            ->with('rules')
            ->orderBy('id')
            ->get()
            ->map(function (Store $store) {
                $rules = $store->rules;
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'requires_residential_address' => (bool) ($rules?->requires_residential_address ? false),
                    'residential_fee_per_item' => (float) ($rules?->residential_fee_per_item ? 2.0),
                    'requires_american_card' => (bool) ($rules?->requires_american_card ? false),
                    'american_card_surcharge_rate' => (float) ($rules?->american_card_surcharge_rate ? 0.03),
                ];
            })
            ->values();

        return response()->json(['items' => $items]);
    }
}

