<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a researcher notes down while browsing, apart from "I should reply
 * here": a discussion worth an article. Filled by the same Reddit triage
 * pass that picks reply opportunities, so reading a thread costs one
 * judgment whatever it turns out to be good for. `kind` leaves room for the
 * next thing worth noting (a feature idea) without a second table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // article.
            $table->string('kind');
            // reddit.
            $table->string('source');
            // The thread permalink for a Reddit idea.
            $table->string('source_ref');
            $table->text('title');
            // What the idea actually is, in one sentence: the article's angle.
            $table->text('angle');

            // open|used|dismissed. `used` points at what it became.
            $table->string('status')->default('open');
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // One idea per thread per kind: a thread re-read on the next
            // scan must not note the same idea twice.
            $table->unique(['project_id', 'kind', 'source_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ideas');
    }
};
