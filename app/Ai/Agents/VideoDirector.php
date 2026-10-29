<?php

namespace App\Ai\Agents;

use App\Enums\SocialPlatform;
use App\Models\Project;
use App\Services\Video\VideoAudio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Writes the short video that goes out with a post: a composition for
 * `resources/video/runtime.js`, plain JavaScript calling a small library of
 * animated bricks, which `VideoRenderer` films frame by frame.
 *
 * Code rather than a template to fill in, so two videos never come out the
 * same: the bricks keep the quality floor, the way they are put together is
 * the model's. It sees the product's screenshots, and the project's last
 * compositions so it does not repeat their structure.
 *
 * Revising, it is given its own script back, what the user said (at which
 * second), and stills of the video around that moment, so it sees what the
 * user saw. When a script failed to build, the error comes back instead and
 * the job is to fix it.
 */
class VideoDirector extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  array<string, array{description: string, width: int, height: int}>  $screenshots  asset name => what it shows, in pixels
     * @param  array<int, string>  $recentCompositions  this project's last scripts, newest first
     * @param  array<int, Image>  $images  the screenshots, in the order of `$screenshots`, then the stills
     * @param  array<int, float>  $stillTimes  the second each still was taken at
     * @param  array<int, array{feedback: string|null, at: float|null, summary: string}>  $revisions  earlier rounds with the user
     * @param  array{logo_colors?: array<int, string>, site_colors?: array<int, string>, site_fonts?: array<int, string>}  $brand  colours and fonts read from the brand's own material
     * @param  int  $exampleCount  how many style examples are attached, after the logo
     */
    public function __construct(
        Project $project,
        private SocialPlatform $platform,
        private string $caption,
        private string $brief,
        private array $screenshots,
        private bool $hasLogo,
        private array $recentCompositions = [],
        private array $images = [],
        private ?string $currentComposition = null,
        private ?string $feedback = null,
        private ?float $feedbackAt = null,
        private array $stillTimes = [],
        private array $revisions = [],
        private ?string $buildError = null,
        private bool $voiced = false,
        private array $brand = [],
        private int $exampleCount = 0,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You direct short vertical videos (1080 by 1920) that go out with a
        social post for a product. You write the video as JavaScript for a small
        animation library. Your script is the body of a function given `v`, the
        video. It runs in a browser with no network: only the calls below exist.

        THE LIBRARY

        v.theme({ background, surface, ink, muted, accent, accent2, display, body, grid })
          ALWAYS call it first, with every value: the default is a plain grey
          that belongs to no brand. Colours as CSS strings; accent and accent2
          make the gradient on **words**. display is the headline font, body
          the rest, from the fonts below. grid: true/false, a faint moving grid
          behind everything.

        Fonts installed, and nothing else:
          Grotesque and clean: 'Inter' (neutral), 'Manrope' (soft, modern),
            'Outfit' (geometric, friendly), 'Space Grotesk' (techy, quirky),
            'Archivo' (sturdy, compact), 'Sora' (rounded tech).
          Display with character: 'Bricolage Grotesque' (warm, editorial),
            'Syne' (artsy, wide), 'Unbounded' (bold, playful, very wide).
          Rounded: 'Nunito'.
          Serif: 'Fraunces' (soft, expressive), 'Playfair Display' (elegant,
            high contrast), 'Lora' (bookish, calm).
          Mono, used for small labels: 'JetBrains Mono'.

        v.scene(seconds, (s) => { ... })
          One scene. Scenes play back to back, each entering and leaving with a
          short zoom and blur. Bricks inside a scene stack top to bottom, centred.
          Every `at` below is seconds from the start of THAT scene.
          v.scene({ duration, align: 'left', justify: 'flex-start', label }, fn)
          is the long form: label puts a small uppercase line at the top.

        In any text, **double stars** paint those words with the accent gradient.

        s.slam(['Word.', 'Word.', '**Word.**'], { at, every })
          Huge words one at a time, trailer style, each held `every` seconds
          (default 0.38). The last one stays. Best as an opening hook, two to
          four words.
        s.title(text, { at, size })   headline, words arrive one by one. size
          defaults to 104 (px at 1080 wide). Keep it under about twelve words.
        s.text(text, { at, size })    a paragraph under the title, size 46.
        s.field(text, { at, label, button, speed })
          an input that types itself (speed in characters per second, default
          18), then a button that gets pressed. For "type a prompt", "paste a
          link", "search for".
        s.list(['point', 'point'], { at, every })   points with a check mark,
          popping in one after the other. Three or four, a few words each.
        s.cards([{ kicker, title, text }], { at, every })   cards dealt in one
          after the other, stacked. Two or three, short.
        s.counter({ to, from, prefix, suffix, label, at, duration, decimals })
          a big number counting up, with a label under it. Only for a real
          number from the product portrait, never an invented one. Keep the
          prefix and suffix to a symbol or two ('%', 'x', ' min'): words go in
          the label.
        s.image(name, { at, height })   one of the assets, as is.
        s.screen(name, { shots: [...], move })
          A screenshot filmed by a camera. Put it alone in its scene. Each shot:
          { at, view: [x, y, w, h], spot: [x, y, w, h], cursor: [x, y], click, caption }
          All coordinates are FRACTIONS of the image, 0 to 1, from its top left.
          `view` is what the camera frames from `at` on; it glides there in
          `move` seconds (0.7). `spot` outlines one region and dims the rest.
          `cursor` moves the pointer there, `click: true` presses it. `caption`
          is a big line under the picture. Read the screenshot carefully before
          placing anything: a spot or a click on the wrong element is the
          worst mistake you can make here.
        s.outro({ tagline, cta })   the closing card: the logo (when the project
          has one), a tagline, and a button-like call to action.
        v.sound(name, atSecondsFromVideoStart, volume)
          Extra sounds: 'pop', 'click', 'typing', 'chime', 'reveal'. Bricks
          already make their own (slam, list and cards pop, field types and
          clicks, screen clicks, outro reveals): add one only where it helps.

        HOW TO MAKE IT GOOD

        - 12 to 25 seconds in all. A hook in the first two seconds. Every scene
          long enough to READ: count about 0.35 s per word on screen, plus a
          second, and never under 2.5 s except the opening slam. A screen
          scene needs about 2.2 s per shot. The outro needs 3 s.
        - The screen is a phone held upright. A screenshot of a wide desktop
          page is unreadable whole: frame a region about 0.3 to 0.45 of the
          image wide, never the full width, and move between regions. A mobile
          screenshot can be framed whole.
        - Show the product working, not adjectives. The brief says what the
          video is for; the screenshots are what you can show. Without
          screenshots, tell it with type: slam, title, field, list, cards.
        - Few words, big. One idea per scene. Captions are four to eight words.
        - End on the outro, with the product's name or URL as the call to action.
        - Vary the structure from the project's earlier videos you are shown:
          a different opening brick, a different order, a different rhythm.
        - Dress it in the brand, never in a house style: background, ink and
          accents from the logo, the style examples and the site's colours you
          are given; a light background when the brand is light. Fonts: the
          site's own when it is installed, else the closest in feel; with
          nothing to go on, a display font whose character fits the product
          (a serif for something crafted or premium, a wide display face for
          something playful), and a different pairing from the last videos.
          Sora with Inter is only for a brand that already uses them. Keep
          strong contrast between ink and background. Two projects must never
          look alike, and this project's videos may vary their palette within
          the brand.
        - Write in the site language. No dash punctuation in any text.
        - Plain JavaScript only: no imports, no network, no DOM of your own,
          no timers. Only `v` and the bricks.

        {$this->voiceSection()}

        Return the whole script every time, never a diff, and a one or two
        sentence summary for the user, without dash punctuation either: what
        the video shows, or, when revising, what you changed.
        PROMPT;
    }

    /**
     * The voice over and the music, only when the instance can make them.
     */
    private function voiceSection(): string
    {
        if (! $this->voiced) {
            return 'This instance has no voice and no music: do not call s.voice(), v.narrator() or v.music().';
        }

        $narrators = collect(VideoAudio::NARRATORS)
            ->map(fn (array $narrator, string $name): string => "  '{$name}': {$narrator['sound']}")
            ->implode("\n");

        return <<<PROMPT
        VOICE AND MUSIC (available here)

        v.narrator(name)   who reads the voice lines, one of:
        {$narrators}
          Pick the one that fits this brand and this video's tone, and not the
          one the project's last video used.

        s.voice(text, { at })   a narrator reads this line aloud, `at` seconds into
          the scene. A scene is lengthened to fit its lines, so keep each line
          short (eight to twelve words) or the video drags. Most people watch without sound, so
          the screen must still tell the story on its own: the voice adds, it
          never carries.
        v.music(description)   background music for the whole video, described
          in a few words of mood, genre and instruments ("warm lo-fi, soft keys,
          steady beat"). Played quietly under everything.

        Use both on most videos: a voice line per scene or every other scene,
        short and conversational, in the site language, never reading the
        on-screen text word for word.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'composition' => $schema->string()
                ->description('The whole script: the body of a function given `v`.')
                ->required(),
            'summary' => $schema->string()
                ->description('One or two sentences for the user: what the video shows, or what changed.')
                ->required(),
        ];
    }

    public function direct(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt(), $this->images);

        return $response;
    }

    private function buildPrompt(): string
    {
        $sections = [
            "## What this product is\n\n{$this->productPortrait()}",
            "## Product URL\n\n{$this->project->url}",
            "## Site language\n\n".($this->project->default_language ?? 'en'),
            "## The {$this->platform->label()} post this video goes out with\n\n{$this->caption}",
            "## What the video is for\n\n{$this->brief}",
        ];

        $sections[] = $this->screenshots === []
            ? "## Screenshots\n\nNone: tell it with type."
            : "## Screenshots, attached in this order\n\n".collect($this->screenshots)
                ->map(fn (array $shot, string $name): string => "- '{$name}' ({$shot['width']} by {$shot['height']} px): {$shot['description']}")
                ->implode("\n");

        $sections[] = "## Logo\n\n".($this->hasLogo ? "Available as the asset 'logo', shown by s.outro(), and attached after the screenshots." : 'None: the outro shows the tagline and call to action only.');

        $sections[] = "## What the brand looks like\n\n".implode("\n", array_filter([
            $this->exampleCount > 0 ? "- {$this->exampleCount} of its own published visuals are attached after the logo: follow their palette and type." : null,
            ($this->brand['logo_colors'] ?? []) !== [] ? '- Logo colours, most present first: '.implode(', ', $this->brand['logo_colors']) : null,
            ($this->brand['site_colors'] ?? []) !== [] ? '- Colours its homepage uses, most used first: '.implode(', ', $this->brand['site_colors']) : null,
            ($this->brand['site_fonts'] ?? []) !== [] ? '- Fonts its homepage uses: '.implode(', ', $this->brand['site_fonts']) : null,
        ]) ?: ['Nothing to go on: pick a palette and fonts that suit what the product is and who it is for, never the default.']);

        if ($this->recentCompositions !== []) {
            $sections[] = "## This project's last videos, do not repeat their structure\n\n".implode("\n\n---\n\n", $this->recentCompositions);
        }

        if ($this->currentComposition !== null) {
            $sections[] = "## The current script\n\n{$this->currentComposition}";
        }

        if ($this->revisions !== []) {
            $sections[] = "## Earlier rounds with the user\n\n".collect($this->revisions)
                ->map(fn (array $round): string => '- '.($round['feedback'] ? "User: {$round['feedback']}".($round['at'] !== null ? " (at {$round['at']} s)" : '').' / ' : '')."You: {$round['summary']}")
                ->implode("\n");
        }

        if ($this->feedback !== null) {
            $where = $this->feedbackAt !== null ? " They paused at {$this->feedbackAt} s." : '';
            $stills = $this->stillTimes !== [] ? ' The last images attached are stills of the current video at '.implode(', ', array_map(fn (float $t): string => "{$t} s", $this->stillTimes)).'.' : '';
            $sections[] = "## What the user wants changed\n\n{$this->feedback}\n\n{$where}{$stills} Change what they asked, keep the rest.";
        }

        if ($this->buildError !== null) {
            $sections[] = "## Your last script failed to build\n\n{$this->buildError}\n\nFix it. Keep the same video otherwise.";
        }

        return implode("\n\n", $sections);
    }
}
