<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ads budget belongs to the project, not to each account: the user says
 * how much the product may spend in a month, and the agent decides where it
 * goes. An account only says whether Eveil may use it.
 *
 * Existing budgets carry over: a project's budget is the sum of what its
 * accounts had in the currency of the first one with a budget, and an
 * account that had a budget is switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // In micros of `ads_currency`. Zero means Eveil spends nothing on
            // ads for this project.
            $table->unsignedBigInteger('ads_monthly_budget_micros')->default(0)->after('ads_autonomy_level');
            // ISO code. Only accounts billed in it are used: budgets are never
            // converted between currencies.
            $table->string('ads_currency', 3)->nullable()->after('ads_monthly_budget_micros');
        });

        Schema::table('ad_accounts', function (Blueprint $table) {
            // Whether Eveil may run campaigns here. One sign-in can reach
            // several accounts, some of them not this product's.
            $table->boolean('enabled')->default(false)->after('external_ref');
        });

        $funded = DB::table('ad_accounts')->where('monthly_budget_micros', '>', 0)->orderBy('id')->get();

        foreach ($funded->groupBy('project_id') as $projectId => $accounts) {
            $currency = $accounts->first()->currency;

            DB::table('projects')->where('id', $projectId)->update([
                'ads_currency' => $currency,
                'ads_monthly_budget_micros' => $accounts->where('currency', $currency)->sum('monthly_budget_micros'),
            ]);
        }

        DB::table('ad_accounts')->whereIn('id', $funded->pluck('id'))->update(['enabled' => true]);

        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->dropColumn('monthly_budget_micros');
        });
    }

    public function down(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('monthly_budget_micros')->default(0);
        });

        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['ads_monthly_budget_micros', 'ads_currency']);
        });
    }
};
