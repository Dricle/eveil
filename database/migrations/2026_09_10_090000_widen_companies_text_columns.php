<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `name`, `website`, `industry`, `size` and `location` are model-written or
 * crawled text with no length the schema should be guessing at: a `varchar(255)`
 * qualification verdict overran it and failed the whole `Qualifier::store()`
 * insert (see the discovery job crash it caused). No `doctrine/dbal`
 * installed, so plain SQL rather than `Blueprint::change()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE companies ALTER COLUMN name TYPE text');
        DB::statement('ALTER TABLE companies ALTER COLUMN website TYPE text');
        DB::statement('ALTER TABLE companies ALTER COLUMN industry TYPE text');
        DB::statement('ALTER TABLE companies ALTER COLUMN size TYPE text');
        DB::statement('ALTER TABLE companies ALTER COLUMN location TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE companies ALTER COLUMN name TYPE varchar(255)');
        DB::statement('ALTER TABLE companies ALTER COLUMN website TYPE varchar(255)');
        DB::statement('ALTER TABLE companies ALTER COLUMN industry TYPE varchar(255)');
        DB::statement('ALTER TABLE companies ALTER COLUMN size TYPE varchar(255)');
        DB::statement('ALTER TABLE companies ALTER COLUMN location TYPE varchar(255)');
    }
};
