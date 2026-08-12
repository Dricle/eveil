<?php

namespace App\Console\Commands;

use App\Ai\AgentSettings;
use App\Models\AgentRun;
use Illuminate\Console\Command;

/**
 * Reads and writes the per-agent provider/model mapping, which lives
 * in the database so changing a model is a settings change and not a deploy.
 *
 * This is an instance-scope setting, never visible to an organization admin or
 * member. A settings screen will front the same values; this command stays
 * regardless — it is how you change a model over SSH on a self-hosted box, and
 * how you script it.
 */
class AgentModelCommand extends Command
{
    protected $signature = 'eveil:agent-model {agent? : Agent slug, e.g. target-profile-deriver}
                                              {--model= : Model id to use}
                                              {--provider= : Provider name, defaults to anthropic}
                                              {--timeout= : Seconds before the call is abandoned}
                                              {--reset : Drop the override and fall back to the shipped default}';

    protected $description = 'Show or change which model each agent runs on';

    public function handle(AgentSettings $agents): int
    {
        $agent = $this->argument('agent');

        if ($agent === null) {
            $this->table(
                ['agent', 'provider', 'model', 'timeout', 'source', 'spent', 'calls'],
                collect($agents->known())->map(fn (string $slug): array => $this->row($slug, $agents))->all(),
            );

            return self::SUCCESS;
        }

        if (! in_array($agent, $agents->known(), true)) {
            $this->components->error("Unknown agent [{$agent}]. One of: ".implode(', ', $agents->known()));

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            $agents->reset($agent);
            $this->components->info("{$agent} is back on the shipped default.");

            return self::SUCCESS;
        }

        if (! $this->option('model') && ! $this->option('provider') && ! $this->option('timeout')) {
            $this->components->warn('Nothing to change. Pass --model, --provider, --timeout or --reset.');

            return self::SUCCESS;
        }

        $agents->save($agent, [
            'provider' => (string) ($this->option('provider') ?: $agents->providerName($agent)),
            'model' => (string) ($this->option('model') ?: $agents->model($agent)),
            'timeout' => (int) ($this->option('timeout') ?: $agents->timeout($agent)),
        ]);

        $this->components->info(sprintf(
            '%s now runs on %s / %s.',
            $agent,
            $agents->providerName($agent),
            $agents->model($agent) ?? "the provider's default",
        ));

        // The credit grid is calibrated on a specific model mix, so
        // in cloud this and `credit_prices` are one operation, never two.
        $this->components->warn('In cloud, adjust credit_prices in the same move — the grid is calibrated on the model mix.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function row(string $agent, AgentSettings $agents): array
    {
        $runs = AgentRun::query()->where('agent', $agent);

        return [
            $agent,
            $agents->providerName($agent),
            $agents->model($agent) ?? '<fg=gray>provider default</>',
            (string) $agents->timeout($agent),
            $agents->isOverridden($agent) ? '<fg=cyan>database</>' : 'default',
            '$'.number_format((float) (clone $runs)->sum('cost'), 4),
            (string) (clone $runs)->count(),
        ];
    }
}
