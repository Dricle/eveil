<?php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cheap guard against a factory definition that no other test happens to
 * exercise: a wrong column name or a missing required field would otherwise
 * only surface the first time a feature needs that model.
 */
it('can create every model from its factory', function (string $model) {
    expect($model::factory()->create())->toBeInstanceOf($model);
})->with(function () {
    // Datasets are resolved before the application boots, so no `app_path()`.
    return collect([
        ['dir' => __DIR__.'/../../app/Models', 'namespace' => 'App\\Models\\'],
        ['dir' => __DIR__.'/../../app/Cloud/Models', 'namespace' => 'App\\Cloud\\Models\\'],
    ])
        ->flatMap(fn (array $group): array => collect(glob($group['dir'].'/*.php'))
            ->map(fn (string $path): string => $group['namespace'].basename($path, '.php'))
            ->all())
        ->filter(fn (string $model): bool => is_subclass_of($model, Model::class)
            && in_array(HasFactory::class, class_uses_recursive($model), strict: true))
        ->values()
        ->all();
});
