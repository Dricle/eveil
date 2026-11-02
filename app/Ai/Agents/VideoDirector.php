<?php

namespace App\Ai\Agents;

use App\Enums\ImageFormat;
use App\Enums\SocialPlatform;
use App\Models\Project;
use App\Services\Video\VideoAudio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
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
     * @param  array<int, string>  $recentConcepts  this project's last videos' concepts, newest first
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
        private array $recentConcepts = [],
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

    /**
     * One drawn at random for each new video, so two projects with the same
     * kind of brand, or the same project twice, do not land on the same look.
     *
     * @var array<int, string>
     */
    private const DIRECTIONS = [
        'Swiss poster: hard cuts and pushes, a different flat colour field every scene, huge tight type filling the width, bands and squares as structure. Display Archivo or Space Grotesk.',
        'Editorial: a serif display (Fraunces or Playfair Display), calm wipes, generous space, a warm light background alternating with deep ink scenes, a single ring or circle as ornament.',
        'Playful pop: bouncy, blobs and circles drifting behind the words, iris and rise transitions, saturated fields in turns. Display Unbounded or Nunito.',
        'Bold type wall: outlined marquees scrolling behind every scene, slams, cuts on the beat, two colours alternating. Display Syne or Bricolage Grotesque.',
        'Product hero: the screenshots carry it, each scene on a solid field framing the product, rise transitions, a counter or a field typing between them. Display Manrope or Outfit.',
        'Minimal premium: few words very large, slow iris and blur transitions, deep dark fields with one accent shape, lots of empty space. Display Playfair Display or Sora.',
    ];

    /**
     * A starting point for each new video, one option drawn per axis, for
     * the director to invent a concept from. Left to itself a model writes
     * the same video every time (slam, sentences, list, outro): the chance
     * has to come from here, and axes rather than whole stories give it
     * hundreds of combinations instead of a handful.
     *
     * @var array<string, array<int, string>>
     */
    private const AXES = [
        'It opens on' => [
            'a question the viewer is asking themselves',
            'a bold claim, as big as the frame allows',
            'the problem, shown or said in the buyer\'s own words',
            'the product already working, no introduction',
            'a single real number from the product portrait, or a striking fact',
            'one surprising word, alone on a colour field',
        ],
        'It is carried by' => [
            'the screenshots, almost every scene a screen with captions',
            'big type alone, statements one per scene',
            'a field typing what the user would type, and what comes back',
            'contrast pairs: the old way, then the way with the product',
            'numbered steps, each a scene with a huge number',
            'shapes and colour fields as the metaphor, few words',
        ],
        'Its rhythm is' => [
            'fast: six to eight short scenes, mostly cuts',
            'slow: three or four long scenes, soft transitions',
            'a build: slow at first, faster and faster to the end',
            'a steady beat, colours alternating scene for scene',
        ],
        'It leaves out' => [
            's.list and s.cards',
            's.slam',
            's.text paragraphs',
            's.outro: it ends on a scene of its own',
        ],
        'It ends on' => [
            'the product\'s name, huge',
            'the URL typed in a field',
            'a question back to the viewer',
            'the result on screen, with the URL as a caption',
            'the outro card',
        ],
    ];

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You direct short videos that go out with a social post for a product.
        {$this->frame()} You write the video as JavaScript for a small
        animation library. Your script is the body of a function given `v`, the
        video. It runs in a browser with no network: only the calls below exist.

        THE LIBRARY

        v.theme({ background, surface, ink, muted, accent, accent2, display, body, emphasis, glow, grid })
          ALWAYS call it first, with every value: the default is a plain grey
          that belongs to no brand. Colours as CSS strings. display is the
          headline font, body the rest, from the fonts below. emphasis is how
          **words** stand out: 'color' (in the accent), 'gradient' (accent to
          accent2), 'highlight' (a marker block behind them), 'underline' or
          'outline'; pick one that suits the brand, not always the same. glow:
          true adds a soft coloured light behind everything, grid: true a faint
          moving grid; both are off unless the look calls for them.

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
          One scene. Scenes play back to back. Bricks inside a scene stack top
          to bottom, centred. Every `at` below is seconds from the start of THAT
          scene. The long form takes options:
          v.scene({ duration, background, ink, transition, align: 'left', justify: 'flex-start', label }, fn)
          background: fills the whole scene with a flat colour, a theme name
            ('accent', 'accent2', 'ink', 'surface', 'background') or any CSS
            colour. The bricks recolour themselves to stay readable on it; ink
            forces the text colour.
          transition: how the scene arrives. 'blur' (default, a soft zoom),
            'cut' (instant, punchy), 'push' (slides in from the right, pushing
            the last scene out), 'rise' (slides up from the bottom), 'wipe'
            (revealed left to right), 'iris' (a circle opening from the centre).
            The four moving ones slide a colour field in: give them a background.
          label: a small uppercase line at the top.

        In any text, **double stars** paint those words with the accent gradient.

        s.slam(['Word.', 'Word.', '**Word.**'], { at, every })
          Huge words one at a time, trailer style, each held `every` seconds
          (default 0.38). The last one stays. Two to four words, at most once.
        s.title(text, { at, size })   headline, words arrive one by one. size
          defaults to 104 (px at 1080 on the short side). Keep it under about twelve words.
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
        s.shape(kind, { x, y, size, color, at, spin, drift, opacity })
          A flat shape behind the bricks: 'circle', 'ring', 'square', 'blob', or
          'band' (a stripe across the full width). x and y are its centre as
          fractions of the frame (they may go past the edges, to crop it), size
          in px. color is a theme name or a CSS colour. spin (degrees) and drift
          (px upwards) keep it moving through the scene.
        s.marquee(text, { y, size, color, speed, reverse, outline, opacity, front })
          A line of huge type scrolling across the frame at height y (a
          fraction), repeated, behind the bricks unless front. outline: true
          draws only its contour. Great as texture with a few words.
        s.draw(markup, { front, style })   YOUR OWN VISUALS. Any HTML or inline
          SVG, laid over the whole frame: `<svg viewBox="0 0 {$this->size()}">`
          covers it exactly, in the frame's own px. Behind the bricks with
          front: false. Returns the layer. Use it for what the bricks cannot
          do: an illustration of the subject (a dice, an inbox, a calendar, a
          phone, a chart, a character), icons, arrows, a layout of your own
          (split screen, a grid of tiles, a fake notification, a chat bubble,
          a mock interface), type set by hand letter by letter. Style it
          inline; fonts as above. Give elements classes to animate them. No
          CSS animation or transition: they would not follow the timeline.
          Keep everything inside the frame with a margin: type that runs past
          it is shrunk to fit, which can leave it smaller than you meant.
        s.animate(target, keyframes, { at, duration, easing, stagger, iterations, direction })
          Moves anything in the scene: a node, or every element matching a
          selector, `stagger` seconds apart. Keyframes as in the Web
          Animations API: transform, opacity, clipPath, filter,
          strokeDashoffset, d: 'path(...)', offsetDistance, colours. Timed
          from `at` seconds into the scene. iterations: Infinity keeps it
          looping. This is where the motion design happens:
            an SVG drawing itself (pathLength="1" stroke-dasharray="1", then
              strokeDashoffset 1 to 0),
            letters or words flying in one by one (each in a span with
              display: inline-block, staggered; a space as its own span with
              white-space: pre, or words run together),
            a mask or clip-path wiping text or an image in,
            a 3D flip (perspective and rotateY), a zoom through a word into
            the next scene, an object bouncing, spinning, orbiting, a shape
            morphing, a counter of tiles filling up, an arrow drawing a path.
        s.outro({ tagline, cta })   the closing card: the logo (when the project
          has one), a tagline, and a button-like call to action.
        v.sound(name, atSecondsFromVideoStart, volume)
          Extra sounds: 'pop', 'click', 'typing', 'chime', 'reveal'. Bricks
          already make their own (slam, list and cards pop, field types and
          clicks, screen clicks, outro reveals): add one only where it helps.

        HOW TO MAKE IT GOOD

        - Invent this video's concept from the starting point you are given
          below, not from habit: it decides how the video opens, what carries
          it, its rhythm, what it leaves out and how it ends. Within that, the
          idea is yours. Make it clearly different from the project's last
          videos.
        - 12 to 25 seconds in all. A hook in the first two seconds. Every scene
          long enough to READ: count about 0.35 s per word on screen, plus a
          second, and never under 2.5 s except the opening slam. A screen
          scene needs about 2.2 s per shot. The outro needs 3 s.
        - {$this->framing()}
        - Show the product working, not adjectives. The brief says what the
          video is for; the screenshots are what you can show. Without
          screenshots, tell it with type, shapes and colour.
        - Few words, big. One idea per scene. Captions are four to eight words.
        - End on the product's name or URL as the call to action, with the
          outro or a closing scene of your own.
        - It must not look like slides of text. Draw: every video has at least
          one illustration of its own about the subject (s.draw, in SVG,
          animated with s.animate), and more when there are no screenshots.
          Something keeps moving in every scene for its whole length, not
          only on entry. Type is big: a short line at 140 to 200, the key word
          even bigger, and type itself animated (per letter, masked, flipped,
          scaled through), not only popped in.
        - Design it like a motion designer, never as slides of text on one
          background. Most scenes get a flat colour field, and consecutive
          scenes change colour (background, accent, ink, accent2, in turns that
          suit the brand). Use at least three different transitions, shapes or
          a marquee for texture in several scenes, and big type that fills the
          frame. A list or cards at most once per video, and never two text
          scenes built the same way in a row. Shapes and marquees are
          seasoning, not the dish: no drifting blobs and rings by habit.
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

        Return the whole script every time, never a diff, a one or two
        sentence summary for the user, without dash punctuation either: what
        the video shows, or, when revising, what you changed, and the concept
        in one line.
        PROMPT;
    }

    /**
     * The frame as an SVG viewBox's width and height.
     */
    private function size(): string
    {
        return implode(' ', $this->platform->videoFormat()->videoSize());
    }

    private function frame(): string
    {
        [$width, $height] = $this->platform->videoFormat()->videoSize();

        return "This one is {$width} by {$height} px, ".$this->platform->videoFormat()->describe().", for {$this->platform->label()}. Every size below is in px at 1080 on the short side.";
    }

    /**
     * How a screenshot reads in this shape.
     */
    private function framing(): string
    {
        return match ($this->platform->videoFormat()) {
            ImageFormat::Story => 'The screen is a phone held upright. A screenshot of a wide desktop page is unreadable whole: frame a region about 0.3 to 0.45 of the image wide, never the full width, and move between regions. A mobile screenshot can be framed whole.',
            ImageFormat::Wide, ImageFormat::Landscape => 'The frame is wide, watched on a desktop as much as a phone: a desktop screenshot can be framed whole for context, then move in on a region about half its width. A mobile screenshot sits small in it: frame it whole and let shapes or type fill the sides.',
            default => 'The frame is a little taller than square, watched in a phone\'s feed: a desktop screenshot is unreadable whole, frame a region about 0.4 to 0.6 of its width and move between regions. A mobile screenshot can be framed whole.',
        };
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
            'concept' => $schema->string()
                ->description('One line naming this video\'s idea and structure, as in "a question answered by a live demo in four fast scenes, ending on the URL typed". Shown to the project\'s next videos so they differ.')
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

        if ($this->recentConcepts !== []) {
            $sections[] = "## This project's last videos, by their concept\n\n".collect($this->recentConcepts)->map(fn (string $concept): string => "- {$concept}")->implode("\n");
        }

        if ($this->currentComposition === null) {
            $sections[] = "## Starting point for this one\n\n".collect(self::AXES)->map(fn (array $options, string $axis): string => "- {$axis} ".Arr::random($options).'.')->implode("\n").' Follow it unless the brief clearly cannot be told this way.';
            $sections[] = "## Art direction for this one\n\n".Arr::random(self::DIRECTIONS).' Apply it within the brand: its colours always win, and its own fonts when it has them.';
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
