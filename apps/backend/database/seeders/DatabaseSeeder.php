<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed only explicitly configured operational demo accounts.
     * Synthetic learning/content datasets are test-only and must be invoked
     * explicitly by the test or acceptance harness that owns them.
     */
    public function run(): void
    {
        $this->call([
            DemoStudentSeeder::class,
            DemoAdminSeeder::class,
        ]);
    }
}
