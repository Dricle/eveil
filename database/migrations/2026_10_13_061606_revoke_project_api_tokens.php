<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // API tokens moved from the project to the organization. A token
        // made for one project is revoked rather than widened to every
        // project of its organization behind its owner's back: the CRM it
        // was handed to was trusted with one project, not all of them.
        DB::table('personal_access_tokens')->where('tokenable_type', 'App\Models\Project')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
