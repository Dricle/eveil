<?php

namespace App\Enums;

/**
 * What an image in a project's visual identity library is, which decides
 * how the image generator uses it:
 * - the logo, placed in the images by the model itself, faithfully;
 * - an official illustration, reused instead of inventing one, when the
 *   writer picks it for a post;
 * - an example of the brand's visual style, followed for palette and look;
 * - a screenshot of the product itself, filmed in the videos that go out
 *   with a post, never handed to the image generator.
 */
enum BrandAssetKind: string
{
    case Logo = 'logo';
    case Illustration = 'illustration';
    case Example = 'example';
    case Screenshot = 'screenshot';

    /**
     * How the image generator is told what a reference is.
     */
    public function role(): string
    {
        return match ($this) {
            self::Logo => "the business's real logo: include it, exactly as it is, unless the user's instructions say otherwise; never redraw or alter its letters, shapes or colours",
            self::Illustration => 'one of the business\'s official illustrations: reuse it as it is rather than inventing a similar one',
            self::Example => 'an example of the brand\'s visual style: follow its palette, typography and look, without copying its content',
            self::Screenshot => 'a screenshot of the product itself: show it as it is, never redraw it',
        };
    }
}
