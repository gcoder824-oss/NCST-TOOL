<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Resource;

class EventResourceSeeder extends Seeder
{
    public function run(): void
    {
        $facilities = [
            "Gymnasium",
            "Multipurpose Hall",
            "HTMD bar",
            "Library"
        ];

        foreach ($facilities as $index => $item) {
            Resource::firstOrCreate(
                ['name' => $item],
                [
                    'asset_code' => 'FAC-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'type' => 'facility',
                    'category' => 'Event Venue',
                    'status' => 'available'
                ]
            );
        }

        $equipment = [
            "Event Speakers System",
            "LED Wall Display",
            "Wireless Microphone Set",
            "Event Lighting Kit",
            "Staging Platform",
            "Audio Mixing Console"
        ];

        foreach ($equipment as $index => $item) {
            Resource::firstOrCreate(
                ['name' => $item],
                [
                    'asset_code' => 'EVT-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'type' => 'equipment',
                    'category' => 'Event Equipment',
                    'status' => 'available'
                ]
            );
        }
    }
}
