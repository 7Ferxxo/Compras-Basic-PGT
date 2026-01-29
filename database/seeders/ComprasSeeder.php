<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\StoreRule;
use Illuminate\Database\Seeder;

class ComprasSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            1 => 'WALMART',
            2 => 'AMAZON',
            3 => 'TEMU',
            4 => 'SHEIN',
            5 => 'EBAY',
            6 => 'ALIEXPRESS',
            7 => 'OTROS',
        ];

        foreach ($stores as $id => $name) {
            Store::updateOrCreate(['id' => $id], ['name' => $name]);
        }

        $rules = [
            1 => ['requires_residential_address' => true, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            2 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => true, 'american_card_surcharge_rate' => 0.03],
            3 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            4 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            5 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            6 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            7 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
        ];

        foreach ($rules as $storeId => $data) {
            StoreRule::updateOrCreate(['store_id' => $storeId], ['store_id' => $storeId, ...$data]);
        }
    }
}

