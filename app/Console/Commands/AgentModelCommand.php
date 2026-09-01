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
 * regardless: it is how you change a model over SSH on a self-hosted box, and
 * how you script it.
 */
class AgentModelCommand extends Command
{
    protected $signature = 'eveil:agent-model {agent? : Agent slug, e.g. target-profile-deriver}
                                              {--model= : Model id to use}
                                              {--provider= : Provider name, defaults to anthropic}
                                              {--timeout= : Seconds before the call is abandoned}
                                              {--reset : Drop the stored mapping and fall back to the conservative default}';

    protected $description = 'Show or change which model each agent runs on';

    public function handle(AgentSettings $agents): int
    {
        $agent = $this->argument('agent');

        if ($agent === null) {
            $this->table(
                ['agent', 'provider', 'model', 'timeout', 'source', 'tokens in / out', 'calls'],
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
            $this->components->info("{$agent} dropped back to the default mapping.");

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

        // `credit_prices` is priced per agent, not per model: a call on
        // Opus and the same call on Sonnet cost the customer the same
        // credits until somebody adds a new row. The row is harmless where
        // nothing reads it (self-hosted), so the warning fires on every
        // edition rather than needing an edition check to decide whether it
        // applies.
        if ($this->option('model')) {
            $this->components->warn("Changing the model does not change credit_prices: it stays calibrated on the old one until you price {$agent} again for {$agents->model($agent)}.");
        }

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
            number_format((float) (clone $runs)->sum('tokens_in')).' / '.number_format((float) (clone $runs)->sum('tokens_out')),
            (string) (clone $runs)->count(),
        ];
    }
}
