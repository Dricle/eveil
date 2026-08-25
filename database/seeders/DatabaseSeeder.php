<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ships in both editions: the head start a fresh install gets before
        // it has learned anything of its own. Also run standalone from
        // `composer setup` and the deploy entrypoint, which is why it is its
        // own seeder rather than three calls duplicated here.
        $this->call(InstallSeeder::class);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
