<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Commitment;
use App\Models\DashboardSetting;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'TheGallery Work' => '#3B82F6',
            'Dissertation' => '#8B5CF6',
            'Training' => '#22C55E',
            'M&S' => '#F59E0B',
            'Side Builds' => '#06B6D4',
            'Life Admin' => '#64748B',
            'Parking Lot' => '#94A3B8',
        ] as $name => $colour) {
            Area::firstOrCreate(['name' => $name], [
                'colour' => $colour,
                'sort_order' => Area::count(),
            ]);
        }

        foreach ([
            ['M&S', 'Early shifts (choose four weekdays)', '04:00', '08:00'],
            ['TheGallery Work', 'Work days (choose four weekdays and hours)', '09:00', '17:00'],
        ] as [$areaName, $title, $start, $end]) {
            $area = Area::where('name', $areaName)->firstOrFail();
            // One recurring record covers four weekdays once configured.
            if (! Commitment::where('area_id', $area->id)->exists()) {
                Commitment::create([
                    'area_id' => $area->id,
                    'title' => $title,
                    'days_of_week' => [],
                    'start_time' => $start,
                    'end_time' => $end,
                    'is_enabled' => false,
                ]);
            }
        }

        DashboardSetting::firstOrCreate(['id' => 1]);
    }
}
