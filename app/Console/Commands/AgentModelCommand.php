<?php

namespace App\Console\Commands;

use App\Ai\AgentSettings;
use App\Enums\AgentType;
use App\Models\AgentRun;
use Illuminate\Console\Command;

/**
 * Reads and writes the per-agent provider/model mapping (ADR-026), which lives
 * in the database so changing a model is a settings change and not a deploy.
 *
 * This is the command-line half of the superadmin settings screen — an
 * instance-scope setting, never visible to an organization admin or member. The
 * screen itself arrives with Epic 1, once there is an interface to put it in.
 */
class AgentModelCommand extends Command
{
    protected $signature = 'eveil:agent-model {agent? : planner, extractor, qualifier, writer or classifier}
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
                collect(AgentType::cases())->map(fn (AgentType $type): array => $this->row($type, $agents))->all(),
            );

            return self::SUCCESS;
        }

        $type = AgentType::tryFrom($agent);

        if ($type === null) {
            $this->components->error("Unknown agent [{$agent}]. One of: ".implode(', ', array_column(AgentType::cases(), 'value')));

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            $agents->reset($type);
            $this->components->info("{$type->value} is back on the shipped default.");

            return self::SUCCESS;
        }

        if (! $this->option('model') && ! $this->option('provider') && ! $this->option('timeout')) {
            $this->components->warn('Nothing to change. Pass --model, --provider, --timeout or --reset.');

            return self::SUCCESS;
        }

        $agents->save($type, [
            'provider' => (string) ($this->option('provider') ?: $agents->provider($type)),
            'model' => (string) ($this->option('model') ?: $agents->model($type)),
            'timeout' => (int) ($this->option('timeout') ?: $agents->timeout($type)),
        ]);

        $this->components->info("{$type->value} now runs on {$agents->provider($type)} / {$agents->model($type)}.");

        // The credit grid is calibrated on a specific model mix (ADR-019), so
        // in cloud this and `credit_prices` are one operation, never two.
        $this->components->warn('In cloud, adjust credit_prices in the same move — the grid is calibrated on the model mix.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function row(AgentType $type, AgentSettings $agents): array
    {
        $runs = AgentRun::query()->where('type', $type->value);

        return [
            $type->value,
            $agents->provider($type),
            $agents->model($type),
            (string) $agents->timeout($type),
            $agents->isOverridden($type) ? '<fg=cyan>database</>' : 'default',
            '$'.number_format((float) (clone $runs)->sum('cost'), 4),
            (string) (clone $runs)->count(),
        ];
    }
}
