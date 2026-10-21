<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third-party services an organization connected through Composio: one row
 * per connected account, whatever the service. Composio holds the token;
 * the row holds the reference to it and what Eveil knows of its state.
 *
 * Organization-owned like a mailbox: one connection serves every project the
 * feature using it is granted to. The Composio key is the organization's
 * own too: its connections live in its own Composio project, under its own
 * quota, and no organization ever reaches another's. What each service does once connected (an
 * Instagram account becoming a `social_accounts` row, for one) hangs off this
 * row, so revoking the connection takes it all down at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // The Composio toolkit slug, a `Toolkit` case: instagram, ...
            $table->string('toolkit');
            // Composio's connected account id: a reference, not the token,
            // and useless without the instance's Composio key.
            $table->string('connected_account_id')->unique();
            // pending|active|error: pending from the moment the user is sent
            // to authorize until they come back.
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'toolkit']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            // A Composio PROJECT key, encrypted with CREDENTIALS_KEY.
            $table->text('composio_api_key')->nullable();
            // Toolkit slug => Composio auth config id, created on first use
            // and belonging to that key's project: a new key forgets them.
            $table->jsonb('composio_auth_configs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['composio_api_key', 'composio_auth_configs']);
        });

        Schema::dropIfExists('integrations');
    }
};
