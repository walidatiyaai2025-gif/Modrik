<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment('testing') && ! (bool) config('modrik.reference_data.enabled')) {
            return;
        }

        $this->call([
            LearningSliceSeeder::class,
            DemoStudentSeeder::class,
            DemoAdminSeeder::class,
        ]);
    }
}
