<?php

namespace App\Ai;

use Laravel\Ai\Ai;
use Laravel\Ai\Enums\Lab;
use Throwable;

/**
 * The model ids a provider names for itself: its default, its cheapest and its
 * smartest text model.
 *
 * That is the whole catalogue `laravel/ai` publishes — there is no enum of
 * model ids, and there could not be one that stayed right for a week. So these
 * are SUGGESTIONS on the settings screen, not the allowed set: the field stays
 * free text, or the model released this morning would be unreachable until a
 * package release caught up with it.
 */
class ModelCatalogue
{
    /**
     * @return array<string, array<int, string>>
     */
    public function suggestions(): array
    {
        return collect(Lab::cases())
            ->mapWithKeys(fn (Lab $lab): array => [$lab->value => $this->for($lab->value)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function for(string $provider): array
    {
        try {
            // Throws rather than returning null for a provider that generates
            // no text, which is why the whole lookup sits inside the try.
            $text = Ai::textProvider($provider);

            $models = [$text->defaultTextModel(), $text->cheapestTextModel(), $text->smartestTextModel()];
        } catch (Throwable) {
            // A provider with no configuration cannot be built, an image or
            // embedding-only one is not a text provider at all, and a
            // self-hosted endpoint names no model until somebody configures
            // one. None of that is an error here: the line simply offers
            // nothing to pick from, and the field is still free text.
            return [];
        }

        return collect($models)->unique()->values()->all();
    }
}
