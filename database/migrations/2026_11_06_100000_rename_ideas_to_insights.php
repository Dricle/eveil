<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ideas` becomes `insights`, the feed of everything Eveil noticed while reading,
 * read or unread. The recommendations move in from their JSON list in
 * `projects.knowledge_base`, so the feed, the Dashboard card and the API all
 * read one table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ponytail: the table's own index and sequence keep their `ideas_` names, harmless.
        Schema::rename('ideas', 'insights');

        Schema::table('insights', function (Blueprint $table) {
            // Where to read the source: a thread, a page. Null when there is none.
            $table->text('url')->nullable();
            // Per kind. acquisition|feature: {impact, effort}. competitor_mention:
            // {competitor, signal, subreddit}.
            $table->jsonb('details')->nullable();
            $table->timestamp('read_at')->nullable();
        });

        // Already listed on the SEO page: starting them unread would bury the
        // feed's first visit under ideas the user has seen.
        DB::table('insights')->update(['read_at' => now()]);

        $statuses = ['proposed' => 'open', 'done' => 'used', 'archived' => 'dismissed'];

        DB::table('projects')->whereNotNull('knowledge_base')->orderBy('id')->each(function (object $project) use ($statuses): void {
            $knowledgeBase = json_decode((string) $project->knowledge_base, true);
            $recommendations = is_array($knowledgeBase['recommendations'] ?? null) ? $knowledgeBase['recommendations'] : [];

            foreach ($recommendations as $recommendation) {
                if (! is_array($recommendation) || ! isset($recommendation['key'], $recommendation['idea'])) {
                    continue;
                }

                $kind = ($recommendation['kind'] ?? null) === 'feature' ? 'feature' : 'acquisition';
                $status = $statuses[$recommendation['status'] ?? 'proposed'] ?? 'open';

                DB::table('insights')->insertOrIgnore([
                    'project_id' => $project->id,
                    'kind' => $kind,
                    'source' => $kind === 'feature' ? 'competitors' : 'website',
                    'source_ref' => (string) $recommendation['key'],
                    'title' => (string) $recommendation['idea'],
                    'angle' => (string) ($recommendation['evidence'] ?? ''),
                    'details' => json_encode([
                        'impact' => $recommendation['impact'] ?? null,
                        'effort' => $recommendation['effort'] ?? null,
                    ]),
                    'status' => $status,
                    // A decided one has been read, by definition.
                    'read_at' => $status === 'open' ? null : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        // `-` on a key that is not there is a no-op. No `?` operator: PDO reads it as a placeholder.
        DB::statement("UPDATE projects SET knowledge_base = knowledge_base - 'recommendations' WHERE knowledge_base IS NOT NULL");
    }

    public function down(): void
    {
        DB::table('insights')->whereIn('kind', ['acquisition', 'feature', 'competitor_mention'])->delete();

        Schema::table('insights', function (Blueprint $table) {
            $table->dropColumn(['url', 'details', 'read_at']);
        });

        Schema::rename('insights', 'ideas');
    }
};
