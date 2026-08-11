<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instance-scope settings (ADR-003): the AI provider key (story 1.3), the
 * per-agent provider/model mapping (ADR-026), retention windows (ADR-018) and
 * the credentials canary (ADR-012) all live here. Superadmin-only — no
 * organization admin or member ever reads this table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();

            // Encrypted with CREDENTIALS_KEY, not APP_KEY (ADR-012).
            $table->boolean('is_encrypted')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
