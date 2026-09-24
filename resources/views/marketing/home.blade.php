@php use Illuminate\Support\Facades\Route; @endphp
<x-marketing-layout
    :title="config('app.name') . ' - open-source outreach'"
    description="Give a URL and what you sell. Eveil finds the companies that need it, writes the outreach, drafts your LinkedIn posts, and finds Reddit threads worth replying to. Open source, self-hostable."
>
    <div class="border-b border-[rgba(232,236,242,.08)] px-4 py-2.5 flex flex-wrap justify-center items-center gap-3 text-[13px] text-[rgba(232,236,242,.72)] text-center">
        <span class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] border border-[rgba(111,211,236,.35)] rounded-full px-[9px] py-[2px]">Free trial</span>
        <span>5,000 credits at signup. Enough for one full campaign, through to replies. No card.</span>
    </div>

    <section id="top" class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 pt-16 pb-12 sm:pt-20 sm:pb-16 lg:pt-24 lg:pb-20 text-center">
            <div class="inline-flex items-center gap-[9px] border border-[rgba(232,236,242,.14)] bg-[rgba(232,236,242,.03)] rounded-full px-[14px] py-[6px] font-[Geist_Mono,monospace] text-[11.5px] tracking-[.06em] uppercase text-[rgba(232,236,242,.7)] mb-6">
                <span class="w-[6px] h-[6px] rounded-full bg-[#6fd3ec] block"></span>
                <span>Open source, AGPL-3.0</span>
            </div>
            <h1 class="font-[Sora,sans-serif] font-semibold text-[38px] sm:text-[50px] lg:text-[68px] leading-[1.04] tracking-[-.035em] mx-auto mb-[22px] max-w-[20ch] [text-wrap:balance]">
                You don't have time to run marketing.<br>Now you don't need to.</h1>
            <p class="text-[16px] sm:text-[18.5px] leading-[1.6] max-w-[64ch] mx-auto mb-[18px] text-[rgba(232,236,242,.66)] [text-wrap:pretty]">
                Paste your product URL. Eveil reads the site, works out who buys it, finds those companies and the
                people at them, writes and sends the outreach from your own mailbox, reads the replies, drafts
                LinkedIn posts about what you're building, and finds Reddit threads worth replying to. You approve
                as much or as little as you want.</p>

            <p class="text-[14px] sm:text-[15px] leading-[1.6] max-w-[58ch] mx-auto mb-8 sm:mb-10 text-[rgba(232,236,242,.48)] [text-wrap:pretty]">
                Not a purchased contact list, and not a pool of pre-warmed inboxes sending on your behalf. It
                automates the same research and outreach a person would do by hand, from the mailbox you already
                own.</p>

            <form action="{{ Route::has('register') ? route('register') : route('home') }}" method="get"
                  class="max-w-[600px] mx-auto bg-[linear-gradient(180deg,rgba(232,236,242,.055),rgba(232,236,242,.02))] border border-[rgba(232,236,242,.12)] rounded-2xl p-5 text-left">
                <label for="url"
                       class="block font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.5)] mb-[9px]">Your
                    product URL</label>
                <div class="flex gap-[9px]">
                    <input id="url" name="url" type="url" required placeholder="https://yourproduct.com" class="field flex-1 min-w-0 font-[Geist_Mono,monospace]">
                    <button type="submit" class="cta-btn border-0 rounded-lg font-[Sora,sans-serif] font-semibold text-[15px] px-[22px] py-3 cursor-pointer whitespace-nowrap">
                        Start free
                    </button>
                </div>
                <p class="mt-[13px] text-[13.5px] text-[rgba(232,236,242,.5)]">5,000 credits, no card, no setup
                    wizard. It shows you a product portrait to correct before it writes a word.</p>
            </form>

            <div class="flex gap-5 sm:gap-7 justify-center mt-8 font-[Geist_Mono,monospace] text-[11.5px] tracking-[.05em] uppercase text-[rgba(232,236,242,.42)] flex-wrap">
                <span>Self-hostable</span>
                <span>Your own mailbox</span>
                <span>No tracking pixels</span>
            </div>

            <figure class="mt-14 border border-[rgba(232,236,242,.12)] rounded-2xl overflow-hidden bg-[#101520]">
                <div class="flex items-center gap-[7px] px-[14px] py-[11px] border-b border-[rgba(232,236,242,.08)]">
                    <span class="w-[9px] h-[9px] rounded-full bg-[rgba(232,236,242,.18)] block"></span>
                    <span class="w-[9px] h-[9px] rounded-full bg-[rgba(232,236,242,.18)] block"></span>
                    <span class="w-[9px] h-[9px] rounded-full bg-[rgba(232,236,242,.18)] block"></span>
                    <span class="font-[Geist_Mono,monospace] text-[11px] text-[rgba(232,236,242,.4)] ml-[10px]">discovery run / live</span>
                </div>
                <img src="{{ asset('screenshot.png') }}" alt="Eveil dashboard" class="block w-full h-auto">
            </figure>
            <figcaption class="mt-3 text-[13px] text-[rgba(232,236,242,.42)]">Project Dashboard
            </figcaption>
        </div>
    </section>

    <section id="how" class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-8 lg:mb-11">
                <div>
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        How it works
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        Five stages between a URL and a qualified lead.</h2>
                </div>
                <p class="text-[rgba(232,236,242,.62)] max-w-[48ch]">Every company gets a fit score and the
                    sentence that justifies it. The pipeline runs over a bundled, self-hosted search engine, so no paid
                    data API is required to start and no purchased list appears anywhere in it.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-[14px]">
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-5 pt-[22px] pb-[26px]">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] text-[#6fd3ec] mb-[14px]">
                        01
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[17.5px] tracking-[-.02em] mb-2">
                        Search planning</h4>
                    <p class="text-[14px] text-[rgba(232,236,242,.58)]">Target profiles become the actual
                        queries that will find those companies.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-5 pt-[22px] pb-[26px]">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] text-[#6fd3ec] mb-[14px]">
                        02
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[17.5px] tracking-[-.02em] mb-2">
                        Qualification</h4>
                    <p class="text-[14px] text-[rgba(232,236,242,.58)]">Each company is read and scored for
                        fit, with the reasoning attached.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-5 pt-[22px] pb-[26px]">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] text-[#6fd3ec] mb-[14px]">
                        03
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[17.5px] tracking-[-.02em] mb-2">
                        Contact discovery</h4>
                    <p class="text-[14px] text-[rgba(232,236,242,.58)]">The people who would actually answer,
                        found on the company's own pages.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-5 pt-[22px] pb-[26px]">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] text-[#6fd3ec] mb-[14px]">
                        04
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[17.5px] tracking-[-.02em] mb-2">
                        Pattern inference</h4>
                    <p class="text-[14px] text-[rgba(232,236,242,.58)]">The company's email convention,
                        inferred rather than guessed one address at a time.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-5 pt-[22px] pb-[26px]">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] text-[#6fd3ec] mb-[14px]">
                        05
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[17.5px] tracking-[-.02em] mb-2">
                        Verification</h4>
                    <p class="text-[14px] text-[rgba(232,236,242,.58)]">Addresses checked before a single
                        mail is queued. Free, always.</p>
                </div>
            </div>
            <p class="mt-5 text-[13.5px] text-[rgba(232,236,242,.42)] max-w-[74ch]">A market that turns
                out to be forty companies is reported as a result, not padded with noise to fill a quota.</p>
        </div>
    </section>

    <section id="agents" class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-8 lg:mb-11">
                <div>
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        The team
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        One team of agents, one knowledge base.</h2>
                </div>
                <p class="text-[rgba(232,236,242,.62)] max-w-[48ch]">Every stage from discovery to sending,
                    and every channel you publish to, reads the same understanding of your product, so nothing is
                    re-explained and nothing contradicts itself.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-[14px]">
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Research
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Knowledge base</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Site analysis, plus an optional
                        linked GitHub repo for a deeper technical read. Ask it questions. Set one writing style for the
                        whole project: tone, language, banned words.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Strategy
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Target profiles</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Who buys the product and why they
                        switch: derived, always editable. Customer profiles and partner profiles, for reach through
                        whoever already touches the customer.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Prospecting
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Companies &amp; contacts</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Approval workflow, status per
                        contact, search across everything discovered, and import when you already have a list.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Copy
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Campaigns &amp; sequences</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Generated sequences with reorderable
                        steps and variants. Regenerate one missing step on its own, without rewriting the sequence
                        around it.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Delivery
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Sending &amp; deliverability</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Plain SMTP from your own mailbox,
                        with no relay and no shared domain. Daily caps, ramp-up, pacing spread across the day, never
                        bursty.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Conversation
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Unified inbox</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Replies read back over IMAP and
                        threaded on the mail's own Message-ID. The reply handler pauses, reschedules, asks for the right
                        contact, or suppresses.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Infrastructure
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Mailbox management</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">SMTP and IMAP with presets for
                        Infomaniak, OVH, Gandi, Zoho, Gmail and Microsoft 365. A connection test that names the exact
                        cause of a refusal.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Channels
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        LinkedIn posting</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Official API, personal profile.
                        A knowledge-base fact, a client you just won, or relevant industry news, drafted and queued
                        for your approval. No automation of connection requests or messages.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Channels
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Reddit replies</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Finds live subreddit threads and evergreen
                        "best X" discussions already ranking on Google, drafts a genuinely useful reply in the
                        thread's own tone, and queues it for you to copy and post yourself.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Team
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Organizations &amp; roles</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Three separate permission scopes,
                        instance, organization and project, never merged into one role column. Invitations and
                        multi-project grants in both editions.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 pt-[26px] pb-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Compliance
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Retention &amp; erasure</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Automatic purge on CNIL-referenced
                        defaults. Erasure keeps a one-way hash only, enough to refuse re-contacting someone forever.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20 grid grid-cols-1 lg:grid-cols-[1fr_1.1fr] gap-8 lg:gap-16 lg:items-center">
            <div>
                <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                    Autonomy
                </div>
                <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em] mb-[18px]">
                    Three notches. The safety valves never move.</h2>
                <p class="mb-3 text-[rgba(232,236,242,.62)]">Autonomy removes approval checkpoints, not
                    guardrails. Circuit breakers apply at every notch, autonomous included: bounce rate, spam
                    complaints, negative-reply rate, auth failures.</p>
                <p class="text-[rgba(232,236,242,.62)]">Plus a bounce breaker scoped per mailbox and a
                    three-layer suppression list: project, mailbox, instance.</p>
                <p class="mt-3 text-[rgba(232,236,242,.62)]">Set per channel: let email run on its own while
                    LinkedIn posts still wait for your approval.</p>
            </div>
            <div class="grid gap-3">
                <div class="border border-[rgba(232,236,242,.09)] bg-[#101520] rounded-xl px-[22px] py-5 grid grid-cols-[auto_1fr] gap-4 items-baseline">
                    <span class="font-[Geist_Mono,monospace] text-[11px] text-[rgba(232,236,242,.35)]">01</span>
                    <div>
                        <div class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em] mb-[5px]">
                            Supervised
                        </div>
                        <p class="text-[14px] text-[rgba(232,236,242,.58)]">You approve every company,
                            contact and mail before it moves.</p>
                    </div>
                </div>
                <div class="border border-[rgba(111,211,236,.4)] bg-[linear-gradient(180deg,rgba(111,211,236,.09),rgba(111,211,236,.03))] rounded-xl px-[22px] py-5 grid grid-cols-[auto_1fr] gap-4 items-baseline">
                    <span class="font-[Geist_Mono,monospace] text-[11px] text-[#6fd3ec]">02</span>
                    <div>
                        <div class="flex items-baseline gap-[10px] mb-[5px]">
                            <span class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em]">Semi-auto</span>
                            <span class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] uppercase text-[#6fd3ec]">default</span>
                        </div>
                        <p class="text-[14px] text-[rgba(232,236,242,.66)]">Research and writing run on their
                            own. Sending is the one thing that waits for you.</p>
                    </div>
                </div>
                <div class="border border-[rgba(232,236,242,.09)] bg-[#101520] rounded-xl px-[22px] py-5 grid grid-cols-[auto_1fr] gap-4 items-baseline">
                    <span class="font-[Geist_Mono,monospace] text-[11px] text-[rgba(232,236,242,.35)]">03</span>
                    <div>
                        <div class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em] mb-[5px]">
                            Autonomous
                        </div>
                        <p class="text-[14px] text-[rgba(232,236,242,.58)]">End to end without you. Breakers
                            stay armed and a tripped one stops the send.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="editions" class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-7 lg:mb-10">
                <div>
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        Editions
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        Same code. No feature gate.</h2>
                </div>
                <p class="text-[rgba(232,236,242,.62)] max-w-[48ch]">Nothing is withheld from self-hosted to
                    make cloud look better. Cloud sells convenience and a head start.</p>
            </div>
            <div class="border border-[rgba(232,236,242,.1)] rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] border-collapse text-[14.5px] text-left">
                        <thead>
                        <tr class="bg-[rgba(232,236,242,.04)]">
                            <th class="px-[18px] py-[14px] font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] font-medium w-1/5">
                                What matters
                            </th>
                            <th class="px-[18px] py-[14px] font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] font-medium">
                                Self-hosted
                            </th>
                            <th class="px-[18px] py-[14px] font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] font-medium">
                                Cloud
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] font-medium">Cost
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.62)]">
                                Free, forever. AGPL-3.0.
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.75)] bg-[rgba(111,211,236,.045)]">
                                Pay-as-you-go credits, no subscription.
                            </td>
                        </tr>
                        <tr>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] font-medium">AI
                                provider
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.62)]">
                                Bring your own key: Anthropic, OpenAI, whichever you already pay for.
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.75)] bg-[rgba(111,211,236,.045)]">
                                Included, metered in credits.
                            </td>
                        </tr>
                        <tr>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] font-medium">Your
                                data
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.62)]">
                                Never leaves your machine.
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.75)] bg-[rgba(111,211,236,.045)]">
                                Hosted, managed, backed up for you.
                            </td>
                        </tr>
                        <tr>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] font-medium">
                                Mailboxes, seats, multi-user
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.62)]">
                                Unlimited. Organizations, roles and invitations included.
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.75)] bg-[rgba(111,211,236,.045)]">
                                Identical. Same code, same limits.
                            </td>
                        </tr>
                        <tr>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] font-medium">Cold
                                start
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.62)]">
                                Learns on its own, in isolation. An instance gets smarter over time as data comes in — what kind of mail works best, which new websites are worth discovering, and so on.
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.75)] bg-[rgba(111,211,236,.045)]">
                                Already smart. You benefit from a knowledge base that's already big and keeps growing.
                            </td>
                        </tr>
                        <tr>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] font-medium">Setup
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.62)]">
                                Docker, five minutes, your own reverse proxy for TLS.
                            </td>
                            <td class="px-[18px] py-[15px] border-t border-[rgba(232,236,242,.08)] text-[rgba(232,236,242,.75)] bg-[rgba(111,211,236,.045)]">
                                An account. Nothing to run.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex gap-3 mt-7 flex-wrap">
                <a href="#top" class="cta-btn py-[11px] px-[22px] rounded-lg font-[Sora,sans-serif] font-semibold text-[15px]">Start
                    on cloud</a>
                <a href="https://github.com/Dricle/eveil" class="ghost-btn border border-[rgba(232,236,242,.18)] py-[11px] px-[22px] rounded-lg font-[Sora,sans-serif] font-semibold text-[15px]">Self-host
                    it</a>
            </div>
        </div>
    </section>

    <section id="pricing" class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-8 lg:mb-11">
                <div>
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        Pricing
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        Credits. No plans, no tiers, no recurring invoice.</h2>
                </div>
                <p class="text-[rgba(232,236,242,.62)] max-w-[48ch]">Top up whatever you choose at one flat
                    published rate. Auto top-up on a threshold, Stripe's own hosted portal for invoices and cards.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[14px]">
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <div class="font-[Sora,sans-serif] font-semibold text-[40px] leading-none tracking-[-.03em] mb-3">
                        €0.10
                    </div>
                    <div class="text-[13.5px] text-[rgba(232,236,242,.58)]">per qualified lead, all in: found, read,
                        written to and delivered.
                    </div>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <div class="font-[Sora,sans-serif] font-semibold text-[40px] leading-none tracking-[-.03em] mb-3">
                        3,500
                    </div>
                    <div class="text-[13.5px] text-[rgba(232,236,242,.58)]">credits for a full 100-lead campaign, end
                        to end. Roughly one exported contact on Apollo.
                    </div>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <div class="font-[Sora,sans-serif] font-semibold text-[40px] leading-none tracking-[-.03em] mb-3">
                        5,000
                    </div>
                    <div class="text-[13.5px] text-[rgba(232,236,242,.58)]">credits free at signup, enough for one
                        full campaign through to replies.
                    </div>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <div class="font-[Sora,sans-serif] font-semibold text-[40px] leading-none tracking-[-.03em] mb-3">
                        €0
                    </div>
                    <div class="text-[13.5px] text-[rgba(232,236,242,.58)]">for verification, SMTP sends and IMAP
                        reads. Always. Credits never expire.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-7 lg:mb-10">
                <div>
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        What we don't do
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        Five things missing on purpose.</h2>
                </div>
                <p class="text-[rgba(232,236,242,.62)] max-w-[48ch]">These are product decisions, not gaps.
                    The only signal worth tracking is a positive reply.</p>
            </div>
            <div class="grid border-t border-[rgba(232,236,242,.08)]">
                <div class="py-5 border-b border-[rgba(232,236,242,.08)] grid grid-cols-1 md:grid-cols-[270px_1fr] gap-2 md:gap-6 md:items-baseline">
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em]">
                        No open or click tracking</h4>
                    <p class="text-[rgba(232,236,242,.58)] text-[14.5px]">No pixel, no link rewriting, no
                        open-rate metric anywhere in the product.</p>
                </div>
                <div class="py-5 border-b border-[rgba(232,236,242,.08)] grid grid-cols-1 md:grid-cols-[270px_1fr] gap-2 md:gap-6 md:items-baseline">
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em]">
                        No mailbox warm-up</h4>
                    <p class="text-[rgba(232,236,242,.58)] text-[14.5px]">A real, already-warm mailbox
                        sending at a human pace, not a fresh domain ramped by a bot network.</p>
                </div>
                <div class="py-5 border-b border-[rgba(232,236,242,.08)] grid grid-cols-1 md:grid-cols-[270px_1fr] gap-2 md:gap-6 md:items-baseline">
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em]">
                        No OAuth</h4>
                    <p class="text-[rgba(232,236,242,.58)] text-[14.5px]">SMTP and IMAP credentials only, in
                        both editions.</p>
                </div>
                <div class="py-5 border-b border-[rgba(232,236,242,.08)] grid grid-cols-1 md:grid-cols-[270px_1fr] gap-2 md:gap-6 md:items-baseline">
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em]">
                        No unsubscribe link</h4>
                    <p class="text-[rgba(232,236,242,.58)] text-[14.5px]">Opt-out is a sentence a person
                        could have typed: reply STOP. Never a compliance footer.</p>
                </div>
                <div class="py-5 border-b border-[rgba(232,236,242,.08)] grid grid-cols-1 md:grid-cols-[270px_1fr] gap-2 md:gap-6 md:items-baseline">
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em]">
                        No purchased database</h4>
                    <p class="text-[rgba(232,236,242,.58)] text-[14.5px]">Every lead shown was found and read
                        live, not pulled from a stale list.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                Who it's for
            </div>
            <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em] mb-8 lg:mb-10">
                Three people, one codebase.</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-[14px]">
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Self-hosted
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Technical solo founder</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Operational in fifteen minutes, with
                        no third-party API key to sign up for beyond the AI provider you already pay for.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] mb-3">
                        Cloud
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Small growth team</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Multi-user and managed hosting, with
                        billing you can predict: a balance you top up, not an invoice that surprises you.</p>
                </div>
                <div class="bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <div class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        Self-hosted
                    </div>
                    <h4 class="font-[Sora,sans-serif] font-semibold text-[20px] tracking-[-.02em] mb-[10px]">
                        Instance superadmin</h4>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">Configure the AI provider, choose a
                        model per agent and close registration from a screen, not by editing files.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20 grid grid-cols-1 lg:grid-cols-[.8fr_1.2fr] gap-8 lg:gap-[60px]">
            <div>
                <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                    Questions
                </div>
                <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                    Before you paste a URL.</h2>
            </div>
            <div class="border-t border-[rgba(232,236,242,.08)]">
                <details class="border-b border-[rgba(232,236,242,.08)] py-5">
                    <summary class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] flex justify-between gap-4">
                        <span>Whose mailbox does it send from?</span><span class="text-[#6fd3ec]">+</span></summary>
                    <p class="mt-3 text-[rgba(232,236,242,.6)] max-w-[62ch]">Yours. Plain SMTP, no relay and
                        no shared sending domain, so your replies land where you already read mail. Presets cover
                        Infomaniak, OVH, Gandi, Zoho, Gmail and Microsoft 365.</p>
                </details>
                <details class="border-b border-[rgba(232,236,242,.08)] py-5">
                    <summary class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] flex justify-between gap-4">
                        <span>Where do the leads come from?</span><span class="text-[#6fd3ec]">+</span></summary>
                    <p class="mt-3 text-[rgba(232,236,242,.6)] max-w-[62ch]">A bundled, self-hosted search
                        engine and the companies' own pages, read live at qualification time. No purchased database, and
                        no paid data API required to start.</p>
                </details>
                <details class="border-b border-[rgba(232,236,242,.08)] py-5">
                    <summary class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] flex justify-between gap-4">
                        <span>Can I see and change what the AI decided?</span><span class="text-[#6fd3ec]">+</span>
                    </summary>
                    <p class="mt-3 text-[rgba(232,236,242,.6)] max-w-[62ch]">At every step. The product
                        portrait comes back for correction before anything is written, target profiles are editable,
                        sequences are reorderable, and hand-edits are never overwritten by a re-derivation.</p>
                </details>
                <details class="border-b border-[rgba(232,236,242,.08)] py-5">
                    <summary class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] flex justify-between gap-4">
                        <span>What language does it write in?</span><span class="text-[#6fd3ec]">+</span></summary>
                    <p class="mt-3 text-[rgba(232,236,242,.6)] max-w-[62ch]">The prospect's. Language is
                        detected per lead's own market rather than fixed per project, so a mail follows the person
                        receiving it.</p>
                </details>
                <details class="border-b border-[rgba(232,236,242,.08)] py-5">
                    <summary class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] flex justify-between gap-4">
                        <span>Will I get a surprise AI bill?</span><span class="text-[#6fd3ec]">+</span></summary>
                    <p class="mt-3 text-[rgba(232,236,242,.6)] max-w-[62ch]">There is no bill, only a
                        balance. Credits are prepaid at one flat rate, never expire, and auto top-up only fires at the
                        threshold you set. Invoices and cards live in Stripe's hosted portal.</p>
                </details>
                <details class="border-b border-[rgba(232,236,242,.08)] py-5">
                    <summary class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.02em] flex justify-between gap-4">
                        <span>Can I move to self-hosted later?</span><span class="text-[#6fd3ec]">+</span></summary>
                    <p class="mt-3 text-[rgba(232,236,242,.6)] max-w-[62ch]">It's the same AGPL-3.0
                        codebase: Docker, five minutes, your own reverse proxy for TLS. Bring your own AI key and
                        nothing is missing.</p>
                </details>
            </div>
        </div>
    </section>

    <section>
        <div class="max-w-[1180px] mx-auto px-6 py-16 sm:py-20 lg:py-24 text-center">
            <h2 class="font-[Sora,sans-serif] font-semibold text-[34px] sm:text-[42px] lg:text-[52px] leading-[1.06] tracking-[-.035em] mx-auto mb-5 max-w-[22ch]">
                Give it a URL. Read the replies.</h2>
            <p class="mx-auto mb-8 max-w-[54ch] text-[rgba(232,236,242,.62)] text-[16px] sm:text-[17px]">5,000 credits at
                signup, capped to one project and to leads actually discovered. Enough for a full campaign through to
                replies.</p>
            <form action="{{ Route::has('register') ? route('register') : route('home') }}" method="get"
                  class="flex gap-[9px] justify-center max-w-[520px] mx-auto">
                <input type="url" name="url" required placeholder="https://yourproduct.com" class="field flex-1 min-w-0 font-[Geist_Mono,monospace] bg-[#101520] px-[14px] py-[13px]">
                <button type="submit" class="cta-btn border-0 rounded-lg font-[Sora,sans-serif] font-semibold text-[15px] px-6 py-[13px] cursor-pointer">
                    Start free
                </button>
            </form>
        </div>
    </section>
</x-marketing-layout>
