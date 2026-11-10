<?php

namespace Database\Seeders;

use App\Models\Inspection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with sample inspections.
     */
    public function run(): void
    {
        Inspection::factory()->completed()->create();
        Inspection::factory()->inProgress()->create();
        Inspection::factory()->assigned()->count(2)->create();
        Inspection::factory()->count(3)->create();
    }
}
