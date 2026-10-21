<?php

namespace App\Ai\Agents;

use App\Ai\AgentSettings;
use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\OutOfCredit;
use App\Ai\ProviderCredentials;
use App\Enums\AgentRunStatus;
use App\Enums\BrandAssetKind;
use App\Enums\ImageFormat;
use App\Models\AgentRun;
use App\Models\Project;
use GdImage;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Image as ImageFile;
use Laravel\Ai\Image;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stringable;
use Throwable;

/**
 * Draws the images Eveil publishes under a business's name, whatever they
 * are for: a social post (from the visual brief `SocialPostWriter` wrote
 * beside an Instagram caption), an ad's visual. The caller says what the
 * picture goes with and what it shows; the rules every one of them follows
 * live here.
 *
 * An agent class although it never chats: that is what gives it its own line
 * on the agents settings screen (provider, model, timeout) and its own
 * credit price, keyed on its slug like every other agent.
 *
 * Metered here rather than by `RecordsAgentRun`: `laravel/ai`'s image events
 * carry no agent, so they cannot say which project a picture was drawn for.
 * Same order as the listener all the same: ask the guard before the
 * provider is called, charge only once it answered.
 */
class ImageGenerator extends EveilAgent
{
    /**
     * @param  string  $purpose  what the image goes with, in a few words: "an Instagram post"
     * @param  string  $brief  what it should show
     * @param  string|null  $note  what the user asked to change after seeing an earlier image
     * @param  ImageFormat  $format  the shape it is delivered in, set by where it ends up
     * @param  string|null  $userInstructions  the user's own box for where it ends up (that network's, say)
     * @param  array<int, Media>  $references  the project's own images to draw from (`BrandAssetKind`): its logo, illustrations, style examples
     */
    public function __construct(
        Project $project,
        private string $purpose,
        private string $brief,
        private ?string $note = null,
        private ImageFormat $format = ImageFormat::Square,
        private ?string $userInstructions = null,
        private array $references = [],
    ) {
        parent::__construct($project);
    }

    /**
     * Only an image provider can answer, so a line switched to every agent's
     * provider at once (`SwitchAgentProvider`) must not take this one along.
     */
    public static function generatesImages(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        A {$this->format->describe()} image a business publishes under its own
        name, on social media or in an ad. Make the kind of visual the brief
        and the user's instructions ask for: a designed graphic (an ad layout, a
        poster with a headline, a magazine page, a collage), a photograph with
        designed elements set into it (a hanging sign or a chalkboard carrying
        the headline, a brush-stroke banner, an info panel with an icon, a
        sticker, a megaphone or other prop), an illustration, or a plain
        photograph.
        It carries one idea, the one in the brief below. Keep what
        matters well inside the frame: the edges may be trimmed to fit the
        exact shape.

        Text only when the brief or the user's own instructions ask for it: a
        headline, dates, a short sentence, the site's address. Then exactly the
        words given, every one of them and nothing more, spelled exactly as
        written, laid out like a designer would: a clear hierarchy, the
        headline largest, everything legible on a phone. Otherwise no text at
        all. Never invent a logo or a brand mark of your
        own. No watermarks. No real, recognisable people. Organised around one
        idea: a dense, bold layout is fine as long as its hierarchy reads at a
        glance. The kind of visual someone would actually stop on in a feed.
        PROMPT.$this->referencesSection().$this->userInstructionsSection();
    }

    /**
     * What each attached reference image is, in the order they are sent:
     * the business's own images, the source of truth for how it looks.
     */
    private function referencesSection(): string
    {
        if ($this->references === []) {
            return '';
        }

        $lines = [];

        foreach (array_values($this->references) as $index => $reference) {
            $kind = BrandAssetKind::tryFrom((string) $reference->getCustomProperty('kind')) ?? BrandAssetKind::Example;
            $description = trim((string) $reference->getCustomProperty('description'));
            $lines[] = ($index + 1).". {$kind->role()}.".($description === '' ? '' : " It shows: {$description}");
        }

        return "\n\nYou are given reference images, in this order. They are this business's own, and the source of truth for how it looks: use them rather than inventing.\n\n".implode("\n", $lines);
    }

    /**
     * The user's own instructions for how this business's images look, in
     * their words: brand colours, a style, text they always want. Placed last
     * and overriding, like every box a user fills in themselves.
     */
    private function userInstructionsSection(): string
    {
        $instructions = trim((string) $this->userInstructions);

        if ($instructions === '') {
            return '';
        }

        return <<<PROMPT


            The user's own instructions. Where they disagree with anything above,
            follow these:

            {$instructions}
            PROMPT;
    }

    /**
     * Draws the image and returns it as JPEG bytes, the one format Instagram
     * accepts. Storing it is the caller's business: it belongs to whatever
     * model it is attached to.
     */
    public function generate(): string
    {
        $prompt = $this->buildPrompt();

        $run = $this->run ?? AgentRun::create([
            'project_id' => $this->project->id,
            'agent' => static::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $run->update([
            'status' => AgentRunStatus::Running,
            'provider' => app(AgentSettings::class)->providerName(static::slug()),
            'model' => $this->model(),
            'input' => ['prompt' => $prompt],
        ]);

        $startedAt = microtime(true);
        $guard = app(SpendGuardInterface::class);
        $refusal = $guard->refusal($this->project, static::slug());

        if ($refusal !== null) {
            $run->update(['status' => AgentRunStatus::Failed, 'error' => $refusal]);

            throw new OutOfCredit($refusal);
        }

        try {
            $response = Image::of($prompt)
                ->attachments(array_map(
                    fn (Media $reference): ImageFile => ImageFile::fromStorage($reference->getPathRelativeToRoot(), $reference->disk),
                    array_values($this->references),
                ))
                ->size($this->format->drawnAt())
                ->timeout($this->timeout())
                ->generate($this->provider(), $this->model());

            $jpeg = $this->jpeg($response->firstImage()->content());
        } catch (Throwable $e) {
            $run->update([
                'status' => AgentRunStatus::Failed,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $run->update([
            'status' => AgentRunStatus::Succeeded,
            'provider' => $response->meta->provider ?? $run->provider,
            'model' => $response->meta->model ?? $run->model,
            'output' => ['bytes' => strlen($jpeg)],
            'tokens_in' => $response->usage->inputTokens,
            'tokens_out' => $response->usage->outputTokens,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $guard->charge($this->project, static::slug(), $run->id);

        return $jpeg;
    }

    /**
     * The configured provider alone, without the failover `EveilAgent` adds:
     * the other provider with a key is as likely as not one that draws
     * nothing, and failing over to it turns a clear error into a confusing one.
     */
    public function provider(): Lab|array|string
    {
        app(ProviderCredentials::class)->apply();

        return app(AgentSettings::class)->provider(static::slug());
    }

    private function buildPrompt(): string
    {
        $prompt = $this->instructions()."\n\nIt goes with {$this->purpose}. What it should show:\n\n{$this->brief}";

        if ($this->note !== null && trim($this->note) !== '') {
            $prompt .= "\n\nThe user saw an earlier image for it and asked for this change: ".trim($this->note);
        }

        return $prompt;
    }

    /**
     * Whatever the provider returned, cropped around the centre to the
     * format's exact ratio and re-encoded as JPEG. GD rather than trusting the
     * provider: some return PNG or WebP whatever they are asked for, and none
     * draws 4:5 or 1.91:1 at all.
     */
    private function jpeg(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The image provider returned something that is not an image.');
        }

        $image = $this->crop($image);

        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * The largest centred area at the format's ratio: only ever trims, never
     * stretches.
     */
    private function crop(GdImage $image): GdImage
    {
        [$ratioWidth, $ratioHeight] = $this->format->ratio();
        $width = imagesx($image);
        $height = imagesy($image);

        $targetWidth = min($width, (int) round($height * $ratioWidth / $ratioHeight));
        $targetHeight = min($height, (int) round($width * $ratioHeight / $ratioWidth));

        if ($targetWidth === $width && $targetHeight === $height) {
            return $image;
        }

        $cropped = imagecrop($image, [
            'x' => intdiv($width - $targetWidth, 2),
            'y' => intdiv($height - $targetHeight, 2),
            'width' => $targetWidth,
            'height' => $targetHeight,
        ]);

        return $cropped instanceof GdImage ? $cropped : $image;
    }
}
