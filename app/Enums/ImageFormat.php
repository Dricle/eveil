<?php

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

/**
 * The shape an `ImageGenerator` picture is delivered in, set by where it ends
 * up: a feed post, a story, an ad's landscape slot.
 *
 * Each format is drawn at the closest ratio the provider can draw
 * (`drawnAt()`), then cropped around the centre to its exact one. Gemini
 * draws most of them natively, `laravel/ai` passing it any ratio, so it
 * needs little or no crop; every other provider gets 1:1, 2:3 or 3:2 only.
 * The prompt says the edges may be trimmed, so the subject stays clear of
 * them.
 */
enum ImageFormat: string
{
    case Square = 'square';
    case Portrait = 'portrait';
    case Story = 'story';
    case Landscape = 'landscape';
    case Wide = 'wide';

    /**
     * Width over height, exactly.
     *
     * @return array{int, int}
     */
    public function ratio(): array
    {
        return match ($this) {
            self::Square => [1, 1],
            self::Portrait => [4, 5],
            self::Story => [9, 16],
            self::Landscape => [191, 100],
            self::Wide => [16, 9],
        };
    }

    /**
     * The ratio the model draws before the crop: the closest one the
     * provider knows. Gemini's own list: 1:1, 2:3, 3:2, 3:4, 4:3, 4:5, 5:4,
     * 9:16, 16:9 and 21:9, so only 1.91:1 is still cropped, from 16:9.
     */
    public function drawnAt(Lab|string|null $provider = null): string
    {
        if ($provider === Lab::Gemini) {
            return match ($this) {
                self::Square => '1:1',
                self::Portrait => '4:5',
                self::Story => '9:16',
                self::Landscape, self::Wide => '16:9',
            };
        }

        return match ($this) {
            self::Square => '1:1',
            self::Portrait, self::Story => '2:3',
            self::Landscape, self::Wide => '3:2',
        };
    }

    /**
     * How the prompt names the shape.
     */
    public function describe(): string
    {
        [$width, $height] = $this->ratio();

        return match ($this) {
            self::Square => 'square (1:1)',
            self::Portrait => 'portrait (4:5)',
            self::Story => 'tall, full-screen vertical (9:16)',
            self::Landscape => 'landscape (1.91:1)',
            self::Wide => "wide ({$width}:{$height})",
        };
    }
}
