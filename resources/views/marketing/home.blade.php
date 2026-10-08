@php
    use Illuminate\Support\Facades\Route;

    $channels = [
        ['icon' => 'mail', 'name' => 'Cold email'],
        ['icon' => 'linkedin', 'name' => 'LinkedIn'],
        ['icon' => 'x', 'name' => 'X'],
        ['icon' => 'bluesky', 'name' => 'Bluesky'],
        ['icon' => 'arrow-big-up', 'name' => 'Reddit'],
        ['icon' => 'file-text', 'name' => 'SEO articles'],
    ];

    $steps = [
        ['n' => '01', 'title' => 'Paste your URL', 'text' => 'That is the whole setup. Add a GitHub repo if you want a deeper technical read.'],
        ['n' => '02', 'title' => 'Eveil learns your product', 'text' => 'It reads your site, writes a product portrait and works out who buys it and why. You correct anything it got wrong.'],
        ['n' => '03', 'title' => 'Agents work every channel', 'text' => 'Emails, posts, articles and replies, all written from that same understanding. You approve as much or as little as you want.'],
    ];

    $emailStages = [
        ['icon' => 'scan-search', 'title' => 'Finds the companies', 'text' => 'Real companies that fit, read live and scored with the sentence that justifies it. Never a purchased list.'],
        ['icon' => 'user-round-search', 'title' => 'Finds the people', 'text' => 'The ones who would actually answer, found on the company\'s own pages, with addresses verified for free.'],
        ['icon' => 'send', 'title' => 'Writes and sends', 'text' => 'A sequence in the prospect\'s language, sent from your own mailbox at a human pace.'],
        ['icon' => 'reply', 'title' => 'Handles the replies', 'text' => 'Replies read back into one inbox. It pauses, reschedules, asks for the right contact, or suppresses.'],
    ];

    $contentChannels = [
        [
            'key' => 'social',
            'label' => 'LinkedIn, X & Bluesky',
            'title' => 'Posts that sound like you',
            'text' => 'A fact about your product, a client you just won, your latest article or relevant industry news, drafted for each network and queued for approval. LinkedIn and Bluesky publish through their official APIs; X you copy and post yourself.',
        ],
        [
            'key' => 'seo',
            'label' => 'SEO',
            'title' => 'Articles your buyers search for',
            'text' => 'Finds what your buyers search for and your site does not answer yet: a missing feature page, a competitor comparison, a question asked on Reddit. Written in your site\'s language, ready to publish.',
        ],
        [
            'key' => 'reddit',
            'label' => 'Reddit',
            'title' => 'Replies in the right threads',
            'text' => 'Finds live subreddit threads and the "best X" discussions already ranking on Google, then drafts a genuinely useful reply in the thread\'s own tone for you to post.',
        ],
    ];

    $autonomyLevels = [
        ['icon' => 'eye', 'name' => 'Supervised', 'text' => 'You approve every lead, email and post before it goes out.', 'default' => false],
        ['icon' => 'sliders-horizontal', 'name' => 'Semi-auto', 'text' => 'Research and writing run on their own. Sending waits for you.', 'default' => true],
        ['icon' => 'zap', 'name' => 'Autonomous', 'text' => 'End to end without you, breakers armed.', 'default' => false],
    ];

    $breakers = ['Bounce rate', 'Spam complaints', 'Negative replies'];

    $principles = [
        ['icon' => 'ban', 'title' => 'No purchased database', 'text' => 'Every lead was found and read live.'],
        ['icon' => 'eye-off', 'title' => 'No tracking pixels', 'text' => 'No open rates, no rewritten links.'],
        ['icon' => 'bot', 'title' => 'No warm-up bot network', 'text' => 'Your real mailbox, at a human pace.'],
        ['icon' => 'hand', 'title' => 'No LinkedIn automation', 'text' => 'Posts on your profile, never fake connection requests.'],
    ];

    $faqs = [
        ['q' => 'Is Eveil only for cold email?', 'a' => 'No. Cold email is where it started and still the deepest channel, but the same agents now post on LinkedIn, X and Bluesky, write SEO articles for your blog and find Reddit threads worth answering. Paid ads are next.'],
        ['q' => 'Whose mailbox does it send from?', 'a' => 'Yours. Plain SMTP, no relay and no shared sending domain, so replies land where you already read mail. Presets cover Infomaniak, OVH, Gandi, Zoho, Gmail and Microsoft 365.'],
        ['q' => 'Where do the leads come from?', 'a' => 'A bundled search engine and the companies\' own pages, read live at qualification time. No purchased database, and no paid data API needed to start.'],
        ['q' => 'Can I see and change what the AI decided?', 'a' => 'At every step. The product portrait comes back for correction before anything is written, target profiles are editable, every draft can be rewritten, and your edits are never overwritten.'],
        ['q' => 'Will I get a surprise AI bill?', 'a' => 'There is no bill, only a balance. Credits are prepaid at one flat rate, never expire, and auto top-up only fires at the threshold you set.'],
        ['q' => 'Can I move to self-hosted later?', 'a' => 'It is the same AGPL-3.0 codebase: Docker, five minutes, your own AI key. Nothing is missing.'],
    ];

    $signupUrl = Route::has('register') ? route('register') : route('home');
@endphp
<x-marketing-layout
    :title="config('app.name') . ' - your AI marketing team, from one URL'"
    description="Paste your product URL. Eveil finds clients by cold email, posts on LinkedIn, X and Bluesky, writes SEO articles and joins the right Reddit threads. Open source, self-hostable."
>
    <div class="border-b border-[rgba(232,236,242,.08)] px-4 py-2.5 flex flex-wrap justify-center items-center gap-3 text-[13px] text-[rgba(232,236,242,.72)] text-center">
        <span class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] border border-[rgba(111,211,236,.35)] rounded-full px-[9px] py-[2px]">Free trial</span>
        <span>5,000 credits at signup. No card.</span>
    </div>

    {{-- Hero --}}
    <section id="top" class="relative overflow-hidden border-b border-[rgba(232,236,242,.08)]">
        <div class="hero-glow absolute inset-0 pointer-events-none" aria-hidden="true"></div>
        <div class="hero-grid absolute inset-0 pointer-events-none" aria-hidden="true"></div>

        <div class="relative max-w-[1180px] mx-auto px-6 pt-16 pb-12 sm:pt-20 sm:pb-16 lg:pt-24 lg:pb-20 text-center">
            <div class="reveal inline-flex items-center gap-[9px] border border-[rgba(232,236,242,.14)] bg-[rgba(232,236,242,.03)] rounded-full px-[14px] py-[6px] font-[Geist_Mono,monospace] text-[11.5px] tracking-[.06em] uppercase text-[rgba(232,236,242,.7)] mb-6">
                <span class="anim-pulse w-[6px] h-[6px] rounded-full bg-[#6fd3ec] block"></span>
                <span>AI marketing, open source</span>
            </div>
            <h1 class="reveal font-[Sora,sans-serif] font-semibold text-[38px] sm:text-[50px] lg:text-[68px] leading-[1.04] tracking-[-.035em] mx-auto mb-[22px] max-w-[18ch] [text-wrap:balance]" style="--delay: .08s">
                Your marketing team, from a <span class="bg-[linear-gradient(90deg,#6fd3ec,#a8b8ff)] bg-clip-text text-transparent">single URL.</span></h1>
            <p class="reveal text-[17px] sm:text-[19px] leading-[1.6] max-w-[56ch] mx-auto mb-8 sm:mb-10 text-[rgba(232,236,242,.66)] [text-wrap:pretty]" style="--delay: .16s">
                Paste your product URL. Eveil learns what you sell and who buys it, then finds you clients by
                email, posts for you on social media, writes your SEO articles and joins the right Reddit threads.</p>

            <form action="{{ $signupUrl }}" method="get" style="--delay: .24s"
                  class="reveal max-w-[600px] mx-auto bg-[linear-gradient(180deg,rgba(232,236,242,.055),rgba(232,236,242,.02))] border border-[rgba(232,236,242,.12)] rounded-2xl p-5 text-left shadow-[0_20px_60px_-20px_rgba(111,211,236,.25)]">
                <label for="url"
                       class="block font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.5)] mb-[9px]">Your
                    product URL</label>
                <div class="flex gap-[9px]">
                    <input id="url" name="url" type="url" required placeholder="https://yourproduct.com" class="field flex-1 min-w-0 font-[Geist_Mono,monospace]">
                    <button type="submit" class="cta-btn group inline-flex items-center gap-2 border-0 rounded-lg font-[Sora,sans-serif] font-semibold text-[15px] px-[22px] py-3 cursor-pointer whitespace-nowrap">
                        Start free
                        <x-marketing.icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-[3px]" />
                    </button>
                </div>
                <p class="mt-[13px] text-[13.5px] text-[rgba(232,236,242,.5)]">No card, no setup wizard. You see
                    what it understood before it writes a word.</p>
            </form>

            <div class="mt-10">
                <div class="reveal font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.4)] mb-4" style="--delay: .3s">
                    One knowledge base, every channel
                </div>
                <ul class="flex flex-wrap justify-center gap-[10px]">
                    @foreach ($channels as $channel)
                        <li class="reveal" style="--delay: {{ .34 + $loop->index * .06 }}s">
                            <span class="anim-float inline-flex items-center gap-2 border border-[rgba(232,236,242,.12)] bg-[#101520] rounded-full pl-3 pr-4 py-[7px] text-[14px] text-[rgba(232,236,242,.82)]" style="--delay: {{ $loop->index * .4 }}s">
                                <x-marketing.icon :name="$channel['icon']" class="w-4 h-4 text-[#6fd3ec]" />
                                {{ $channel['name'] }}
                            </span>
                        </li>
                    @endforeach
                    <li class="reveal" style="--delay: .7s">
                        <span class="inline-flex items-center gap-2 border border-dashed border-[rgba(232,236,242,.16)] rounded-full px-4 py-[7px] text-[14px] text-[rgba(232,236,242,.42)]">
                            <x-marketing.icon name="megaphone" class="w-4 h-4" />
                            Ads <span class="font-[Geist_Mono,monospace] text-[10px] tracking-[.08em] uppercase">soon</span>
                        </span>
                    </li>
                </ul>
            </div>

            <figure class="reveal mt-14 border border-[rgba(232,236,242,.12)] rounded-2xl overflow-hidden bg-[#101520] shadow-[0_40px_120px_-40px_rgba(111,211,236,.35)]" style="--delay: .2s">
                <div class="flex items-center gap-[7px] px-[14px] py-[11px] border-b border-[rgba(232,236,242,.08)]">
                    <span class="w-[9px] h-[9px] rounded-full bg-[rgba(232,236,242,.18)] block"></span>
                    <span class="w-[9px] h-[9px] rounded-full bg-[rgba(232,236,242,.18)] block"></span>
                    <span class="w-[9px] h-[9px] rounded-full bg-[rgba(232,236,242,.18)] block"></span>
                    <span class="font-[Geist_Mono,monospace] text-[11px] text-[rgba(232,236,242,.4)] ml-[10px]">project dashboard</span>
                </div>
                <img src="{{ asset('screenshot.png') }}" alt="Eveil project dashboard" class="block w-full h-auto">
            </figure>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="reveal font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                How it works
            </div>
            <h2 class="reveal font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em] mb-8 lg:mb-11 max-w-[24ch]">
                One input. Everything else is drafted for you.</h2>
            <ol class="grid grid-cols-1 md:grid-cols-3 gap-[14px]">
                @foreach ($steps as $step)
                    <li class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl overflow-hidden" style="--delay: {{ $loop->index * .12 }}s">
                        <div class="h-[168px] border-b border-[rgba(232,236,242,.07)] bg-[radial-gradient(80%_90%_at_50%_100%,rgba(111,211,236,.08),transparent)] flex items-center justify-center px-6" aria-hidden="true">
                            @if ($loop->index === 0)
                                {{-- A URL being typed --}}
                                <div class="w-full max-w-[260px]">
                                    <div class="flex items-center gap-2 border border-[rgba(232,236,242,.16)] bg-[#0b0e14] rounded-lg px-3 py-[10px]">
                                        <x-marketing.icon name="globe" class="w-4 h-4 shrink-0 text-[rgba(232,236,242,.45)]" />
                                        <span class="anim-typing font-[Geist_Mono,monospace] text-[13px] text-[#e8ecf2]">yourproduct.com</span>
                                        <span class="anim-blink w-[2px] h-4 bg-[#6fd3ec] -ml-1"></span>
                                    </div>
                                    <div class="mt-3 ml-auto w-fit inline-flex items-center gap-1 rounded-md bg-[#6fd3ec] text-[#06222c] font-[Sora,sans-serif] font-semibold text-[12px] px-3 py-[6px] float-right">
                                        Start <x-marketing.icon name="arrow-right" class="w-3 h-3" />
                                    </div>
                                </div>
                            @elseif ($loop->index === 1)
                                {{-- The product portrait filling in --}}
                                <div class="w-full max-w-[260px] border border-[rgba(232,236,242,.12)] bg-[#0b0e14] rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-3 text-[12px] font-[Geist_Mono,monospace] uppercase tracking-[.06em] text-[rgba(232,236,242,.55)]">
                                        <x-marketing.icon name="sparkles" class="w-4 h-4 text-[#6fd3ec]" /> Product portrait
                                    </div>
                                    @foreach (['Sells', 'Buyers', 'Why they switch'] as $row)
                                        <div class="flex items-center gap-2 mb-[9px] last:mb-0">
                                            <span class="w-[86px] shrink-0 text-[11.5px] text-[rgba(232,236,242,.5)]">{{ $row }}</span>
                                            <span class="mock-line anim-fill flex-1 !bg-[rgba(111,211,236,.35)]" style="--delay: {{ $loop->index * .35 }}s"></span>
                                            <x-marketing.icon name="check" class="anim-pop w-[14px] h-[14px] text-[#6fd3ec]" style="--delay: {{ $loop->index * .35 }}s" />
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- One brain fanning out to every channel --}}
                                <div class="relative w-full max-w-[260px] h-[136px]">
                                    <svg viewBox="0 0 260 136" class="absolute inset-0 w-full h-full" fill="none">
                                        @foreach ([0, 23, 46, 69, 92, 114] as $top)
                                            <path d="M58 68 C 130 68, 130 {{ $top + 11 }}, 224 {{ $top + 11 }}" stroke="rgba(111,211,236,.4)" stroke-width="1.2" class="anim-flow" />
                                        @endforeach
                                    </svg>
                                    <div class="anim-pulse absolute left-[14px] top-[46px] w-11 h-11 rounded-xl bg-[#6fd3ec] text-[#06222c] flex items-center justify-center">
                                        <x-marketing.icon name="brain" class="w-6 h-6" />
                                    </div>
                                    @foreach ($channels as $channel)
                                        <div class="absolute right-[14px] w-[22px] h-[22px] rounded-md border border-[rgba(232,236,242,.16)] bg-[#101520] flex items-center justify-center text-[#6fd3ec]" style="top: {{ $loop->index * 23 - ($loop->last ? 1 : 0) }}px">
                                            <x-marketing.icon :name="$channel['icon']" class="w-3 h-3" />
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="px-6 pt-[22px] pb-7">
                            <div class="font-[Geist_Mono,monospace] text-[12px] text-[#6fd3ec] mb-[10px]">{{ $step['n'] }}</div>
                            <h3 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">{{ $step['title'] }}</h3>
                            <p class="text-[15px] text-[rgba(232,236,242,.6)]">{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Channels --}}
    <section id="channels" class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-8 lg:mb-11">
                <div class="reveal">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        What it does
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        Outreach and content, from the same brain.</h2>
                </div>
                <p class="reveal text-[rgba(232,236,242,.62)] max-w-[48ch]" style="--delay: .1s">Every agent reads the same understanding of
                    your product and your buyers, so nothing is explained twice and no channel contradicts another.</p>
            </div>

            {{-- Cold email: the core channel gets the wide card --}}
            <div class="reveal border border-[rgba(111,211,236,.35)] bg-[linear-gradient(180deg,rgba(111,211,236,.07),rgba(111,211,236,.015))] rounded-2xl p-6 sm:p-8 lg:p-10 mb-[14px]">
                <div class="grid grid-cols-1 lg:grid-cols-[.9fr_1.1fr] gap-8 lg:gap-12">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="w-9 h-9 rounded-lg bg-[rgba(111,211,236,.14)] text-[#6fd3ec] flex items-center justify-center">
                                <x-marketing.icon name="mail" class="w-5 h-5" />
                            </span>
                            <span class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec]">Cold email</span>
                            <span class="font-[Geist_Mono,monospace] text-[10px] tracking-[.08em] uppercase text-[rgba(232,236,242,.55)] border border-[rgba(232,236,242,.18)] rounded-full px-2 py-[1px]">Core</span>
                        </div>
                        <h3 class="font-[Sora,sans-serif] font-semibold text-[26px] sm:text-[30px] leading-[1.15] tracking-[-.025em] mb-4">
                            From a URL to replies in your inbox.</h3>
                        <p class="text-[15.5px] text-[rgba(232,236,242,.64)] mb-4">Eveil does the research and
                            outreach a person would do by hand, at a scale no person has time for. From the mailbox you
                            already own, with daily caps, gradual ramp-up and circuit breakers that stop sending the
                            moment something looks wrong.</p>
                        <p class="text-[14px] text-[rgba(232,236,242,.45)] mb-6">A market that turns out to be forty
                            companies is reported as forty, never padded to fill a quota.</p>

                        {{-- A reply landing in the inbox --}}
                        <div class="anim-float max-w-[380px] border border-[rgba(232,236,242,.12)] bg-[#0b0e14] rounded-xl p-4 shadow-[0_20px_50px_-25px_rgba(0,0,0,.8)]" aria-hidden="true">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="anim-pulse w-2 h-2 rounded-full bg-[#5ee0a0] block"></span>
                                <span class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] uppercase text-[#5ee0a0]">New reply</span>
                                <span class="ml-auto font-[Geist_Mono,monospace] text-[10.5px] text-[rgba(232,236,242,.35)]">2 min ago</span>
                            </div>
                            <div class="text-[13.5px] font-semibold mb-1">Re: your product for their team</div>
                            <div class="text-[13.5px] text-[rgba(232,236,242,.6)]">"Interesting timing, we were just looking at this. Free for a call Thursday?"</div>
                        </div>
                    </div>
                    <ol class="grid grid-cols-1 sm:grid-cols-2 gap-[12px] content-start">
                        @foreach ($emailStages as $stage)
                            <li class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-5 py-5" style="--delay: {{ .1 + $loop->index * .08 }}s">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="w-9 h-9 rounded-lg bg-[rgba(111,211,236,.1)] text-[#6fd3ec] flex items-center justify-center">
                                        <x-marketing.icon :name="$stage['icon']" class="w-[18px] h-[18px]" />
                                    </span>
                                    <span class="font-[Geist_Mono,monospace] text-[11.5px] text-[rgba(232,236,242,.35)]">0{{ $loop->iteration }}</span>
                                </div>
                                <h4 class="font-[Sora,sans-serif] font-semibold text-[17px] tracking-[-.02em] mb-[6px]">{{ $stage['title'] }}</h4>
                                <p class="text-[14px] text-[rgba(232,236,242,.58)]">{{ $stage['text'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-[14px]">
                @foreach ($contentChannels as $channel)
                    <div class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl overflow-hidden" style="--delay: {{ $loop->index * .1 }}s">
                        <div class="h-[150px] border-b border-[rgba(232,236,242,.07)] bg-[radial-gradient(80%_90%_at_50%_100%,rgba(111,211,236,.07),transparent)] flex items-center justify-center px-5" aria-hidden="true">
                            @if ($channel['key'] === 'social')
                                <div class="w-full max-w-[220px] border border-[rgba(232,236,242,.12)] bg-[#0b0e14] rounded-lg p-3">
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="w-6 h-6 rounded-full bg-[linear-gradient(135deg,#6fd3ec,#a8b8ff)] block"></span>
                                        <span class="mock-line w-16"></span>
                                        <span class="ml-auto flex gap-1 text-[rgba(232,236,242,.5)]">
                                            <x-marketing.icon name="linkedin" class="w-3 h-3" />
                                            <x-marketing.icon name="x" class="w-3 h-3" />
                                            <x-marketing.icon name="bluesky" class="w-3 h-3" />
                                        </span>
                                    </div>
                                    <span class="mock-line mb-[6px]"></span>
                                    <span class="mock-line w-4/5 mb-3"></span>
                                    <div class="flex items-center justify-between">
                                        <span class="font-[Geist_Mono,monospace] text-[9.5px] tracking-[.06em] uppercase text-[rgba(232,236,242,.4)]">Awaiting approval</span>
                                        <span class="anim-pulse rounded bg-[#6fd3ec] text-[#06222c] text-[10.5px] font-semibold px-2 py-[2px]">Approve</span>
                                    </div>
                                </div>
                            @elseif ($channel['key'] === 'seo')
                                <div class="w-full max-w-[220px] border border-[rgba(232,236,242,.12)] bg-[#0b0e14] rounded-lg p-3">
                                    <div class="flex items-center gap-2 mb-2 text-[rgba(232,236,242,.45)]">
                                        <x-marketing.icon name="scan-search" class="w-3 h-3" />
                                        <span class="mock-line flex-1 !h-[5px]"></span>
                                    </div>
                                    <div class="font-[Geist_Mono,monospace] text-[9.5px] text-[#5ee0a0] mb-1">yourproduct.com/blog</div>
                                    <div class="text-[12px] font-semibold text-[#a8c4ff] leading-tight mb-2">Your product vs. the usual suspects</div>
                                    <span class="mock-line w-full mb-[5px] !h-[5px]"></span>
                                    <div class="flex items-center gap-1 text-[#5ee0a0] text-[11px] font-semibold">
                                        <x-marketing.icon name="trending-up" class="w-[14px] h-[14px]" /> Ranking
                                    </div>
                                </div>
                            @else
                                <div class="w-full max-w-[220px] border border-[rgba(232,236,242,.12)] bg-[#0b0e14] rounded-lg p-3">
                                    <div class="flex gap-2">
                                        <div class="flex flex-col items-center text-[#ff7a45]">
                                            <x-marketing.icon name="arrow-big-up" class="w-4 h-4" />
                                            <span class="text-[10.5px] font-semibold">284</span>
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-[11.5px] leading-tight mb-2">What tool do you use for this?</div>
                                            <div class="border-l-2 border-[rgba(111,211,236,.5)] pl-2">
                                                <span class="font-[Geist_Mono,monospace] text-[9px] tracking-[.06em] uppercase text-[#6fd3ec]">Your draft</span>
                                                <span class="mock-line anim-shimmer mt-1 mb-[5px] !h-[5px]"></span>
                                                <span class="mock-line anim-shimmer w-3/4 !h-[5px]"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="px-6 pt-[22px] pb-7">
                            <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] mb-3">{{ $channel['label'] }}</div>
                            <h3 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">{{ $channel['title'] }}</h3>
                            <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">{{ $channel['text'] }}</p>
                        </div>
                    </div>
                @endforeach
                <div id="roadmap" class="reveal border border-dashed border-[rgba(232,236,242,.16)] rounded-xl overflow-hidden" style="--delay: .3s">
                    <div class="h-[150px] border-b border-dashed border-[rgba(232,236,242,.1)] flex items-center justify-center px-5" aria-hidden="true">
                        <div class="w-full max-w-[220px] border border-dashed border-[rgba(232,236,242,.14)] rounded-lg p-3">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-7 h-7 rounded-md bg-[rgba(232,236,242,.06)] text-[rgba(232,236,242,.5)] flex items-center justify-center">
                                    <x-marketing.icon name="megaphone" class="w-4 h-4" />
                                </span>
                                <span class="mock-line anim-shimmer flex-1"></span>
                            </div>
                            <span class="mock-line anim-shimmer mb-[6px]"></span>
                            <span class="mock-line anim-shimmer w-2/3"></span>
                        </div>
                    </div>
                    <div class="px-6 pt-[22px] pb-7">
                        <div class="flex items-center gap-2 font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                            <span class="anim-pulse w-[6px] h-[6px] rounded-full bg-[rgba(111,211,236,.6)] block"></span>
                            In the works
                        </div>
                        <h3 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px] text-[rgba(232,236,242,.82)]">Ad management</h3>
                        <p class="text-[14.5px] text-[rgba(232,236,242,.5)]">The same knowledge base and target
                            profiles, driving your paid campaigns. Audiences and ad copy derived from what Eveil already
                            knows about your buyers.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Control --}}
    <section class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20 grid grid-cols-1 lg:grid-cols-[1fr_1.1fr] gap-8 lg:gap-16 lg:items-center">
            <div class="reveal">
                <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                    You stay in charge
                </div>
                <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em] mb-[18px]">
                    Choose how much it does on its own.</h2>
                <p class="text-[rgba(232,236,242,.62)] mb-3">Set it per channel: let email run by itself while your
                    LinkedIn posts still wait for a yes.</p>
                <p class="text-[rgba(232,236,242,.62)] mb-6">The safety checks never switch off. Bounce rate, spam
                    complaints and negative replies are watched at every level, and a tripped breaker stops the send.</p>
                <div class="inline-flex flex-wrap items-center gap-2 border border-[rgba(232,236,242,.1)] bg-[#101520] rounded-xl px-4 py-3" aria-hidden="true">
                    <x-marketing.icon name="shield-check" class="w-5 h-5 text-[#5ee0a0]" />
                    @foreach ($breakers as $breaker)
                        <span class="inline-flex items-center gap-[6px] text-[12.5px] text-[rgba(232,236,242,.7)] border border-[rgba(232,236,242,.1)] rounded-full px-[10px] py-[3px]">
                            <span class="anim-pulse w-[6px] h-[6px] rounded-full bg-[#5ee0a0] block" style="animation-delay: {{ $loop->index * .5 }}s"></span>
                            {{ $breaker }}
                        </span>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-3">
                @foreach ($autonomyLevels as $level)
                    <div @class([
                        'reveal lift rounded-xl px-[22px] py-5 flex items-start gap-4',
                        'border border-[rgba(111,211,236,.4)] bg-[linear-gradient(180deg,rgba(111,211,236,.09),rgba(111,211,236,.03))]' => $level['default'],
                        'border border-[rgba(232,236,242,.09)] bg-[#101520]' => ! $level['default'],
                    ]) style="--delay: {{ $loop->index * .1 }}s">
                        <span @class([
                            'w-10 h-10 shrink-0 rounded-lg flex items-center justify-center',
                            'bg-[#6fd3ec] text-[#06222c]' => $level['default'],
                            'bg-[rgba(232,236,242,.06)] text-[rgba(232,236,242,.7)]' => ! $level['default'],
                        ])>
                            <x-marketing.icon :name="$level['icon']" class="w-5 h-5" />
                        </span>
                        <div>
                            <div class="flex items-baseline gap-[10px] mb-[5px]">
                                <span class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em]">{{ $level['name'] }}</span>
                                @if ($level['default'])
                                    <span class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] uppercase text-[#6fd3ec]">default</span>
                                @endif
                            </div>
                            <p class="text-[14px] text-[rgba(232,236,242,.62)]">{{ $level['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Principles --}}
    <section class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="reveal font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                Marketing you won't be embarrassed by
            </div>
            <h2 class="reveal font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em] mb-8 lg:mb-10 max-w-[26ch]">
                Some things are missing on purpose.</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[14px]">
                @foreach ($principles as $principle)
                    <div class="reveal border-t border-[rgba(232,236,242,.14)] pt-5" style="--delay: {{ $loop->index * .08 }}s">
                        <span class="w-10 h-10 mb-4 rounded-lg border border-[rgba(255,122,122,.25)] bg-[rgba(255,122,122,.06)] text-[#ff9a9a] flex items-center justify-center">
                            <x-marketing.icon :name="$principle['icon']" class="w-5 h-5" />
                        </span>
                        <h3 class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] mb-[6px]">{{ $principle['title'] }}</h3>
                        <p class="text-[14.5px] text-[rgba(232,236,242,.58)]">{{ $principle['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing & editions --}}
    <section id="pricing" class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-8 lg:mb-11">
                <div class="reveal">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        Pricing
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        Credits, not subscriptions.</h2>
                </div>
                <p class="reveal text-[rgba(232,236,242,.62)] max-w-[48ch]" style="--delay: .1s">Top up what you want at one flat rate. Credits
                    never expire, and there are no plans, tiers or per-seat fees.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-[14px]">
                @foreach ([['5,000', 'credits free at signup. Enough for a full campaign through to replies.'], ['€0.10', 'per qualified lead, all in: found, read, written to and delivered.'], ['€0', 'for verification, sending and reading replies. Always.']] as [$figure, $caption])
                    <div class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7" style="--delay: {{ $loop->index * .08 }}s">
                        <div class="font-[Sora,sans-serif] font-semibold text-[40px] leading-none tracking-[-.03em] mb-3">{{ $figure }}</div>
                        <div class="text-[14px] text-[rgba(232,236,242,.58)]">{{ $caption }}</div>
                    </div>
                @endforeach
            </div>

            <div id="editions" class="mt-[14px] grid grid-cols-1 md:grid-cols-2 gap-[14px]">
                <div class="reveal lift border border-[rgba(111,211,236,.35)] bg-[linear-gradient(180deg,rgba(111,211,236,.07),rgba(111,211,236,.015))] rounded-xl px-6 py-7 flex flex-col">
                    <div class="flex items-center gap-2 font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] mb-3">
                        <x-marketing.icon name="cloud" class="w-4 h-4" /> Cloud
                    </div>
                    <h3 class="font-[Sora,sans-serif] font-semibold text-[22px] tracking-[-.02em] mb-[10px]">Nothing to run.</h3>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)] mb-6">Hosted and backed up for you, AI
                        included and metered in credits. Starts with a knowledge base that is already big and keeps
                        growing.</p>
                    <a href="#top" class="cta-btn mt-auto self-start py-[11px] px-[22px] rounded-lg font-[Sora,sans-serif] font-semibold text-[15px]">Start free</a>
                </div>
                <div class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7 flex flex-col" style="--delay: .1s">
                    <div class="flex items-center gap-2 font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        <x-marketing.icon name="server" class="w-4 h-4" /> Self-hosted
                    </div>
                    <h3 class="font-[Sora,sans-serif] font-semibold text-[22px] tracking-[-.02em] mb-[10px]">Free forever. AGPL-3.0.</h3>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)] mb-6">Docker, five minutes, your own AI key.
                        Same code with no feature gate: unlimited mailboxes and seats, and your data never leaves your
                        machine.</p>
                    <a href="https://github.com/Dricle/eveil" class="ghost-btn mt-auto self-start border border-[rgba(232,236,242,.18)] py-[11px] px-[22px] rounded-lg font-[Sora,sans-serif] font-semibold text-[15px]">View on GitHub</a>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20 grid grid-cols-1 lg:grid-cols-[.8fr_1.2fr] gap-8 lg:gap-[60px]">
            <div class="reveal">
                <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                    Questions
                </div>
                <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                    Before you paste a URL.</h2>
            </div>
            <div class="reveal border-t border-[rgba(232,236,242,.08)]" style="--delay: .1s">
                @foreach ($faqs as $faq)
                    <details class="group border-b border-[rgba(232,236,242,.08)] py-5">
                        <summary class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] flex justify-between gap-4">
                            <span>{{ $faq['q'] }}</span>
                            <x-marketing.icon name="plus" class="w-5 h-5 shrink-0 text-[#6fd3ec] transition-transform duration-300 group-open:rotate-45" />
                        </summary>
                        <p class="mt-3 text-[rgba(232,236,242,.6)] max-w-[62ch]">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative overflow-hidden">
        <div class="hero-glow absolute inset-0 pointer-events-none rotate-180" aria-hidden="true"></div>
        <div class="relative max-w-[1180px] mx-auto px-6 py-16 sm:py-20 lg:py-24 text-center">
            <h2 class="reveal font-[Sora,sans-serif] font-semibold text-[34px] sm:text-[42px] lg:text-[52px] leading-[1.06] tracking-[-.035em] mx-auto mb-5 max-w-[20ch]">
                Give it a URL. Get back to building.</h2>
            <p class="reveal mx-auto mb-8 max-w-[50ch] text-[rgba(232,236,242,.62)] text-[16px] sm:text-[17px]" style="--delay: .08s">5,000 free
                credits, no card. See what Eveil understood about your product in a few minutes.</p>
            <form action="{{ $signupUrl }}" method="get" style="--delay: .16s"
                  class="reveal flex gap-[9px] justify-center max-w-[520px] mx-auto">
                <input type="url" name="url" required aria-label="Your product URL" placeholder="https://yourproduct.com" class="field flex-1 min-w-0 font-[Geist_Mono,monospace] bg-[#101520] px-[14px] py-[13px]">
                <button type="submit" class="cta-btn group inline-flex items-center gap-2 border-0 rounded-lg font-[Sora,sans-serif] font-semibold text-[15px] px-6 py-[13px] cursor-pointer">
                    Start free
                    <x-marketing.icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-[3px]" />
                </button>
            </form>
        </div>
    </section>
</x-marketing-layout>
