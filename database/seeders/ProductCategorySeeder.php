<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Batteries & Power', 'description' => 'Lithium batteries, AGM, battery boxes and power storage solutions'],
            ['name' => 'Solar Panels', 'description' => 'Rigid, flexible and portable solar panels'],
            ['name' => 'Chargers & Controllers', 'description' => 'MPPT solar controllers, B2B chargers and mains battery chargers'],
            ['name' => 'Inverters', 'description' => 'Pure sine wave inverters and combined inverter/chargers'],
            ['name' => 'Mains & Hookup', 'description' => 'Shore power inlets, hookup cables, RCD/MCB consumer units and 240V distribution'],
            ['name' => 'Monitoring & Control', 'description' => 'Battery monitors, shunt sensors, displays and system control panels'],
            ['name' => 'Cabling & Protection', 'description' => 'Cables, fuses, circuit breakers, busbars and DC distribution'],
            ['name' => 'Lighting & 12V', 'description' => 'Interior lights, switches, USB sockets and 12V accessories'],
            ['name' => 'Fittings & Accessories', 'description' => 'Mounts, cable glands, vents, fixings and miscellaneous hardware'],
        ];

        foreach ($categories as $category) {
            ProductCategory::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($category['name'])],
                $category
            );
        }
    }
}
