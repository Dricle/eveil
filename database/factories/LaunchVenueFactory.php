<?php

namespace Database\Factories;

use App\Enums\LaunchVenueKind;
use App\Enums\LaunchVenueOrigin;
use App\Models\LaunchVenue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaunchVenue>
 */
class LaunchVenueFactory extends Factory
{
    protected $model = LaunchVenue::class;

    /**
     * A catalog venue, shared by every project.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $host = fake()->unique()->domainName();

        return [
            'project_id' => null,
            'key' => $host,
            'origin' => LaunchVenueOrigin::Catalog,
            'name' => ucfirst(fake()->word()),
            'url' => "https://{$host}",
            'kind' => LaunchVenueKind::Directory,
            'tags' => [],
        ];
    }

    /**
     * A venue the user added to one project.
     */
    public function custom(Project $project): static
    {
        return $this->state([
            'project_id' => $project->id,
            'origin' => LaunchVenueOrigin::User,
        ]);
    }
}
