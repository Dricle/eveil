<?php

namespace App\Ai\Agents;

use App\Ai\AgentSettings;
use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\OutOfCredit;
use App\Ai\ProviderCredentials;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use GdImage;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Image;
use RuntimeException;
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
     */
    public function __construct(Project $project, private string $purpose, private string $brief)
    {
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
        return <<<'PROMPT'
        A square image a business publishes under its own name, on social media
        or in an ad. It illustrates one idea, the one in the brief below; it is
        not a product screenshot.

        No words, letters, numbers, logos or watermarks anywhere in the image:
        generated lettering comes out misspelled. No real, recognisable
        people. One clear subject, uncluttered, with natural light and colour,
        the kind of picture someone would actually stop on in a feed.
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
                ->square()
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
        return $this->instructions()."\n\nIt goes with {$this->purpose}. What it should show:\n\n{$this->brief}";
    }

    /**
     * Whatever format the provider returned, re-encoded as JPEG. GD rather
     * than trusting the provider's format: some return PNG or WebP whatever
     * they are asked for.
     */
    private function jpeg(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The image provider returned something that is not an image.');
        }

        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }
}
