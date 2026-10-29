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
     * ponytail: one calm, clear narrator for every project. A per-project
     * voice is a setting to add the day someone asks for one.
     */
    private const VOICE = 'cjVigY5qzO86Huf0OWal';

    public function __construct(private ProviderCredentials $credentials) {}

    public function available(): bool
    {
        return $this->credentials->isConfigured(Lab::ElevenLabs->value);
    }

    /**
     * One spoken line, as MP3 bytes.
     */
    public function speak(string $text): string
    {
        return Audio::of($text)
            ->voice(self::VOICE)
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
