<?php

namespace App\Services\Video;

use App\Ai\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;

/**
 * The voice and the music of a video, from ElevenLabs, when the instance has
 * a key for it (App Settings, or `ELEVENLABS_API_KEY`). Without one, the
 * videos stay with their type and bundled sounds: a voice over is a bonus,
 * never a requirement.
 */
class VideoAudio
{
    /**
     * Multilingual, so a voice line reads in whatever language the site is in.
     */
    private const VOICE_MODEL = 'eleven_multilingual_v2';

    /**
     * The narrators a video can pick from, ElevenLabs' own premade voices,
     * there on every account. Each video picks its own, to fit the brand and
     * not to sound like the last one.
     *
     * @var array<string, array{id: string, sound: string}>
     */
    public const NARRATORS = [
        'Sarah' => ['id' => 'EXAVITQu4vr4xnSDxMaL', 'sound' => 'female, mature, reassuring and confident, American'],
        'Matilda' => ['id' => 'XrExE9yKIg1WjnnlVkGX', 'sound' => 'female, upbeat and professional, American'],
        'Jessica' => ['id' => 'cgSgspJ2msm6clMCkdW9', 'sound' => 'female, young, playful and bright, American'],
        'Alice' => ['id' => 'Xb7hH8MSUJpSbSDYk0k2', 'sound' => 'female, clear and engaging, British'],
        'Eric' => ['id' => 'cjVigY5qzO86Huf0OWal', 'sound' => 'male, smooth and trustworthy, American'],
        'Brian' => ['id' => 'nPczCjzI2devNBz1zQrb', 'sound' => 'male, deep and resonant, American'],
        'Liam' => ['id' => 'TX3LPaxmHKxFdv7VOQHJ', 'sound' => 'male, young and energetic, American'],
        'Daniel' => ['id' => 'onwK4e9ZLuTAKqWW03F9', 'sound' => 'male, steady broadcaster, British'],
        'River' => ['id' => 'SAz9YHcvj6GT2YYXdXww', 'sound' => 'neutral, calm and relaxed, American'],
    ];

    /**
     * Read by whoever the script did not name, or named wrong.
     */
    public const DEFAULT_NARRATOR = 'Sarah';

    public function __construct(private ProviderCredentials $credentials) {}

    public function available(): bool
    {
        return $this->credentials->isConfigured(Lab::ElevenLabs->value);
    }

    /**
     * One spoken line, as MP3 bytes, read by one of `NARRATORS`.
     */
    public function speak(string $text, string $narrator): string
    {
        return Audio::of($text)
            ->voice((self::NARRATORS[$narrator] ?? self::NARRATORS[self::DEFAULT_NARRATOR])['id'])
            ->timeout(60)
            ->generate(Lab::ElevenLabs, self::VOICE_MODEL)
            ->content();
    }

    /**
     * An instrumental track of about `$seconds`, as MP3 bytes. Not through
     * `laravel/ai`, which has no music: ElevenLabs' own endpoint.
     */
    public function compose(string $prompt, float $seconds): string
    {
        $this->credentials->apply();

        $response = Http::withHeaders(['xi-api-key' => (string) config('ai.providers.eleven.key')])
            ->timeout(180)
            ->post('https://api.elevenlabs.io/v1/music', [
                'prompt' => "{$prompt}. Instrumental, no vocals.",
                // Its shortest track is 10 seconds; the renderer trims and fades it.
                'music_length_ms' => (int) round(max(10, $seconds + 1) * 1000),
            ]);

        if (! $response->successful()) {
            throw new VideoRenderFailed("ElevenLabs could not make the music: HTTP {$response->status()} {$response->body()}");
        }

        return $response->body();
    }
}
