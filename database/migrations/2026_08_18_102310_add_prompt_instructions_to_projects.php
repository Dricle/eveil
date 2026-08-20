<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * House style, in the user's own words: the tone to write in, the language to
 * write it in, the words never to use. It is a per-project instruction because
 * it describes how this product talks, and it is honoured by the agents that
 * WRITE: an extractor's output is a set of fields nobody reads as prose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('prompt_instructions')->nullable()->after('default_language');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('prompt_instructions');
        });
    }
};
