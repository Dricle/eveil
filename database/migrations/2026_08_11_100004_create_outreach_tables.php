<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sending, suppression and replies.
 *
 * Mail leaves the user's own mailbox over plain SMTP/IMAP
 * and must be indistinguishable from something they typed: no
 * tracking, no unsubscribe link, no Eveil URL. Opt-out is a "reply STOP"
 * sentence, which makes reply classification a compliance mechanism rather
 * than a metric.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            // The ORGANIZATION owns the mailbox. Credentials, limit, signature.
            // Which projects may send through it is granted separately, in
            // `email_account_project`.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('from_name');
            $table->string('from_email');

            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port');
            $table->string('smtp_username');
            $table->text('smtp_password'); // encrypted with CREDENTIALS_KEY
            $table->string('smtp_encryption')->nullable();

            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port');
            $table->string('imap_username');
            $table->text('imap_password'); // encrypted with CREDENTIALS_KEY
            $table->string('imap_encryption')->nullable();

            // The only trailing block allowed in a message body.
            $table->text('signature')->nullable();

            $table->unsignedSmallInteger('daily_limit')->default(30);
            $table->timestamp('ramp_up_started_at')->nullable();

            $table->string('status')->default('active'); // active|paused|error
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        /**
         * Which mailboxes a project may send through.
         *
         * A pivot rather than a nullable `project_id`, which could only say
         * "one project" or "all projects". "All" is the dangerous half: adding
         * a project would silently grant it the founder's personal address,
         * and that address carries a reputation nobody wants lent out by
         * default. A new project starts unable to send until someone attaches
         * a mailbox on purpose: the safe failure.
         *
         * The daily limit stays on the mailbox and NOT here: a shared address
         * has one quota that every project draws on, because one quota is what
         * the receiving server sees.
         */
        Schema::create('email_account_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['email_account_id', 'project_id']);
            $table->index('project_id');
        });

        /**
         * Three layers, three scopes. Every pre-send check reads all
         * three:
         *   opt_out → project (or organization once escalated)
         *   bounce  → email account
         *   toxic   → instance-wide, and never fed by client behaviour
         */
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('layer'); // opt_out|bounce|toxic

            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('email_account_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('email')->nullable();
            $table->string('domain')->nullable();

            $table->string('reason');
            $table->string('source')->nullable();
            $table->timestamp('created_at');

            $table->index(['layer', 'email']);
            $table->index(['layer', 'domain']);
            $table->index(['project_id', 'email']);
            $table->index(['organization_id', 'email']);
            $table->index(['email_account_id', 'email']);
        });

        /**
         * What we have learned about the mail servers behind a domain.
         *
         * Keyed on the MX HOST rather than the recipient domain, which is where
         * the leverage is: one row for `protection.outlook.com` covers every
         * company on Microsoft, and Google and Microsoft between them host a
         * large share of business email.
         *
         * This replaced nine hardcoded provider names, which had no Proton,
         * Zoho, Fastmail, GMX, OVH, Infomaniak or any corporate Exchange. A
         * miss there is not a wrong answer: the probe returns nothing and we
         * answer `unknown` either way. It just costs the timeout each time.
         */
        Schema::create('mail_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('host')->unique();

            // Conversations completed, and how many ended without a verdict.
            // Both, because one silence is greylisting and five is a policy.
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('refusals')->default(0);

            // Set for the providers everyone already knows refuse; never
            // overwritten by what we observe, and editable by a superadmin.
            $table->boolean('is_locked')->default(false);

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('draft'); // draft|active|paused|completed|archived
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('campaign_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type'); // email|wait
            $table->unsignedInteger('delay_hours')->nullable();
            $table->jsonb('config')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'position']);
        });

        Schema::create('step_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_step_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('body');

            // Null means the body is generated per lead in the prospect's own
            // language; a value marks a hand-written or translated variant
            // cached per (template, language) pair.
            $table->string('language', 5)->nullable();

            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();

            $table->index(['campaign_step_id', 'language']);
        });

        Schema::create('campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            // Pinned for the whole sequence so the thread stays coherent, and
            // so mailbox rotation never splits one conversation.
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('current_step_position')->default(0);
            $table->string('status')->default('pending'); // pending|running|paused|completed|failed|stopped

            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason')->nullable();

            $table->timestamps();

            $table->unique(['campaign_id', 'lead_id']);
            $table->index(['status', 'next_action_at']);
        });

        // A lead surfaced by two target profiles is not contacted twice: at most
        // one live campaign membership per lead. Partial unique index: the
        // second target profile records the overlap without re-engaging.
        DB::statement("CREATE UNIQUE INDEX campaign_leads_one_active_per_lead ON campaign_leads (lead_id) WHERE status IN ('pending', 'running', 'paused')");

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();

            $table->string('direction'); // outbound|inbound

            // Reply attribution: our Message-ID on the way out, matched against
            // In-Reply-To / References on the way back in.
            $table->string('message_id')->unique();
            $table->string('in_reply_to')->nullable();

            $table->string('subject');
            $table->text('body');

            // Set on inbound messages only. `auto_reply` must never
            // pause a campaign; `unsubscribe` is the sole opt-out channel and
            // errs toward suppressing.
            $table->string('classification')->nullable(); // interested|not_now|wrong_person|not_interested|unsubscribe|auto_reply

            $table->string('status')->nullable(); // queued|sent|bounced|failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('in_reply_to');
            $table->index(['lead_id', 'created_at']);
            $table->index(['direction', 'classification']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('campaign_leads');
        Schema::dropIfExists('step_variants');
        Schema::dropIfExists('campaign_steps');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('mail_hosts');
        Schema::dropIfExists('email_account_project');
        Schema::dropIfExists('email_accounts');
    }
};
