<?php

namespace Database\Factories;

use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscoveryTask>
 */
class DiscoveryTaskFactory extends Factory
{
    protected $model = DiscoveryTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'discovery_run_id' => DiscoveryRun::factory(),
            'kind' => DiscoveryTaskKind::Probe,
            'status' => DiscoveryTaskStatus::Pending,
            'payload' => ['source' => 'web_search', 'probe' => ['query' => 'friteries namur']],
        ];
    }
}
