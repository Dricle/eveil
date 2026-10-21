<?php

namespace App\Enums;

/**
 * The shape an `ImageGenerator` picture is delivered in, set by where it ends
 * up: a feed post, a story, an ad's landscape slot.
 *
 * Image models draw three ratios only (`laravel/ai` passes 1:1, 2:3 and 3:2
 * to every provider), so each format is drawn at the closest of those
 * (`drawnAt()`) and cropped around the centre to its exact ratio. The prompt
 * says so, so the subject stays clear of the edges.
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
     * The ratio the model draws before the crop: the closest one it knows.
     */
    public function drawnAt(): string
    {
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
