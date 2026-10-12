<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Resource;
use Illuminate\Support\Facades\DB;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        // Remove old non-facility items so we replace them cleanly
        Resource::whereIn('type', ['equipment', 'consumable', 'kit'])->delete();

        $equipments = [
            "Digital Multimeter", "LAN Cable Tester", "Tone Generator & Probe (Cable Tracer)",
            "POST Test Card (Diagnostic Card)", "Digital oscilloscopes", "function/signal generators",
            "Temperature-controlled soldering stations", "desoldering pumps", "laser distance meters",
            "surveying tripods/rods", "Portable drafting board with parallel rule", "T-Square",
            "Triangle", "circle/architectural templates", "steel measuring tapes", "fold-out wooden rules"
        ];

        $consumables = [
            "RJ45", "Keystone jacks", "UTP cable", "zip ties", "velcro cable straps",
            "Thermal paste", "Resistors", "capacitors", "transistors", "LEDs",
            "integrated circuits (ICs)", "breadboards", "jumper wires", "Screws",
            "bolts", "Soldering wire"
        ];

        $kits = [
            "Precision screwdriver set", "Network Kit", "Socket wrench sets",
            "Arduino kit", "Technical Drafting & Sketching Kit"
        ];

        $codeCounter = 1;

        foreach ($equipments as $item) {
            Resource::create([
                'name' => $item,
                'asset_code' => 'EQ-' . str_pad($codeCounter++, 4, '0', STR_PAD_LEFT),
                'type' => 'equipment',
                'category' => 'Tools & Equipment',
                'stock' => 10,
                'status' => 'available'
            ]);
        }

        $codeCounter = 1;
        foreach ($consumables as $item) {
            Resource::create([
                'name' => $item,
                'asset_code' => 'CS-' . str_pad($codeCounter++, 4, '0', STR_PAD_LEFT),
                'type' => 'consumable',
                'category' => 'Supplies',
                'stock' => 50,
                'status' => 'available'
            ]);
        }

        $codeCounter = 1;
        foreach ($kits as $item) {
            Resource::create([
                'name' => $item,
                'asset_code' => 'KT-' . str_pad($codeCounter++, 4, '0', STR_PAD_LEFT),
                'type' => 'kit',
                'category' => 'Sets & Kits',
                'stock' => 5,
                'status' => 'available'
            ]);
        }
    }
}
