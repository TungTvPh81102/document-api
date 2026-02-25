<?php

namespace Database\Seeders;

use App\Models\DynamicService\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'EMAIL', 'name' => 'Email & Account'],
            ['code' => 'DOMAIN', 'name' => 'Domain/Hosting'],
            ['code' => 'MES', 'name' => 'MES/SFCS System'],
            ['code' => 'ERP', 'name' => 'ERP System'],
            ['code' => 'HR', 'name' => 'Human Resources'],
            ['code' => 'ADMIN', 'name' => 'Administrative'],
        ];

        foreach ($categories as $category) {
            ServiceCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category
            );
        }
    }
}
