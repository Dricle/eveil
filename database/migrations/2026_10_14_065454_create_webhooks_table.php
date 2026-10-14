<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound webhooks: where a project tells its CRM, or the n8n/Zapier/Make
 * flow in front of it, that something happened. Per project, not per
 * organization: an agency's client A must never receive client B's leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);

            // ["reply.received", "status.changed"]: the events this endpoint
            // receives, values of `App\Enums\WebhookEvent`.
            $table->jsonb('events');

            // The HMAC key the receiver checks the signature with. Encrypted
            // with CREDENTIALS_KEY via `EncryptedCredential`, shown once.
            $table->text('secret');

            // The last attempt, whatever it was, so the screen can say an
            // endpoint is failing before the user wonders where their leads went.
            $table->timestamp('last_delivered_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->text('last_error')->nullable();

            // Since when every attempt has failed, null while it works. A day of
            // that sets `disabled_at`: nothing is sent until a test succeeds.
            $table->timestamp('failing_since')->nullable();
            $table->timestamp('disabled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
