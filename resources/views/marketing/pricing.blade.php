@php
    use Illuminate\Support\Facades\Route;

    $figures = [
        ['figure' => '5,000', 'caption' => 'credits free at signup. No card, and no clock running down.'],
        ['figure' => '$1', 'caption' => 'buys 1,000 credits. One flat rate, whatever amount you top up.'],
        ['figure' => '$0', 'caption' => 'to verify an address or send a mail. Always, on both editions.'],
    ];

    $grid = [
        ['what' => 'Reading your product site and writing its portrait', 'credits' => '200', 'when' => 'Once per project, and whenever you ask for a refresh'],
        ['what' => 'Reading your competitors\' sites for the features they sell and you do not', 'credits' => '200', 'when' => 'Per run, when you ask for one'],
        ['what' => 'Reading a linked code repository', 'credits' => '600', 'when' => 'Per repo, confirmed before it starts'],
        ['what' => 'Deriving a target profile', 'credits' => '150', 'when' => 'Per profile'],
        ['what' => 'Planning where to look for companies', 'credits' => '500', 'when' => 'Per discovery run'],
        ['what' => 'Triaging a search result', 'credits' => '2', 'when' => 'Per result considered'],
        ['what' => 'Reading a listing page', 'credits' => '3', 'when' => 'Per page'],
        ['what' => 'Finding a company\'s contact page', 'credits' => '2', 'when' => 'Per company'],
        ['what' => 'Qualifying a company', 'credits' => '3', 'when' => 'Per company read'],
        ['what' => 'Extracting contacts from a company', 'credits' => '8', 'when' => 'Per company read, whether it finds contacts or not'],
        ['what' => 'Writing a sequence', 'credits' => '100', 'when' => 'Per campaign'],
        ['what' => 'Writing another version of a step to test against', 'credits' => '40', 'when' => 'Per version'],
        ['what' => 'Personalising one message', 'credits' => '3', 'when' => 'Per message, per lead'],
        ['what' => 'Reading a reply and deciding what to do with it', 'credits' => '1', 'when' => 'Per reply received'],
        ['what' => 'Generating the image that goes with a post', 'credits' => '100', 'when' => 'Per image'],
        ['what' => 'Reading your ad accounts and deciding what to change', 'credits' => '300', 'when' => 'Per review: once a day while ads are switched on, plus any you ask for'],
        ['what' => 'Asking Evie a question', 'credits' => '1 - 3', 'when' => 'Per message, by how long the conversation is'],
    ];

    $free = [
        [
            'icon' => 'shield-check',
            'title' => 'Verifying an address',
            'text' => 'Syntax, disposable domains, MX records, catch-all detection and an SMTP probe that sends nothing. Built in, not resold, so there is no per-address fee and no verification plan to buy.',
        ],
        [
            'icon' => 'send',
            'title' => 'Sending',
            'text' => 'Through your own mailbox, over plain SMTP. No relay, no shared sending domain, no warm-up network to rent. Connect as many mailboxes as you like: we do not count them and we do not charge for them.',
        ],
        [
            'icon' => 'reply',
            'title' => 'Receiving',
            'text' => 'Replies land in the mailbox you already read. Nothing is charged for a reply arriving; only the agent that reads it and decides what happens next costs a credit, and it is one.',
        ],
        [
            'icon' => 'check',
            'title' => 'Seats',
            'text' => 'Invite your whole team. Nobody is counted, and there is no plan that unlocks them.',
        ],
    ];

    $usual = [
        'Four products: a contact database, an enrichment tool, a verification service and a sending platform.',
        'Most of them bill per seat, so the price goes up when a colleague wants to look.',
        'All of them bill every month, whether you ran a campaign or not.',
        'The credits they include reset at the end of the month, so a quiet month is money you simply lose.',
        'Mailboxes are charged one by one, and your prospect data lives on their servers.',
    ];

    $instead = [
        'One product, one balance, one rate.',
        'No seats counted, on either edition.',
        'A month where you run nothing costs nothing.',
        'Credits never reset and never expire. What you bought stays bought.',
        'As many mailboxes as you want, sending from your own SMTP.',
    ];

    $faqs = [
        ['q' => 'Do credits expire?', 'a' => 'No. They sit on your balance until you spend them. There is no monthly reset and no use-it-or-lose-it.'],
        ['q' => 'What happens when I run out?', 'a' => 'The agents stop and tell you. Nothing is deleted, nothing is cancelled, and no card is charged that you did not ask us to charge. Auto top-up is off until you turn it on, and then it only fires at the threshold and the amount you set, under a monthly ceiling you choose.'],
        ['q' => 'Is there a free trial?', 'a' => '5,000 credits at signup, no card. A trial runs one project and stops at 500 leads found. Both limits lift the first time you top up.'],
        ['q' => 'Do you charge per seat or per mailbox?', 'a' => 'Neither. Invite the whole team and connect every mailbox you have; nothing about that changes what you pay.'],
        ['q' => 'Am I going to get a surprise bill?', 'a' => 'There is no bill. There is a balance you topped up on purpose, and a grid that tells you what each action takes out of it.'],
        ['q' => 'What is the catch with self-hosting?', 'a' => 'There is not one. Same repository, same code, AGPL-3.0, no feature gate. You bring your own AI provider key and pay that provider directly.'],
        ['q' => 'Can I start on cloud and move to my own server later?', 'a' => 'Yes. It is the same codebase: Docker, five minutes, your own key. Nothing is missing.'],
    ];

    $signupUrl = Route::has('register') ? route('register') : route('home');
@endphp
<x-marketing-layout
    :title="config('app.name') . ' - Pricing'"
    description="Eveil runs on prepaid credits that never expire. No seats, no plans, no monthly subscription. Verifying an address and sending a mail cost nothing. Self-hosting is free forever under AGPL-3.0."
>
    <div class="border-b border-[rgba(232,236,242,.08)] px-4 py-2.5 flex flex-wrap justify-center items-center gap-3 text-[13px] text-[rgba(232,236,242,.72)] text-center">
        <span class="font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] border border-[rgba(111,211,236,.35)] rounded-full px-[9px] py-[2px]">Free trial</span>
        <span>5,000 credits at signup. No card.</span>
    </div>

    {{-- Hero --}}
    <section id="top" class="relative overflow-hidden border-b border-[rgba(232,236,242,.08)]">
        <div class="hero-glow absolute inset-0 pointer-events-none" aria-hidden="true"></div>

        <div class="relative max-w-[1180px] mx-auto px-6 pt-16 pb-12 sm:pt-20 sm:pb-16 lg:pt-24 lg:pb-20">
            <div class="reveal font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                Pricing
            </div>
            <h1 class="reveal font-[Sora,sans-serif] font-semibold text-[38px] sm:text-[50px] lg:text-[60px] leading-[1.06] tracking-[-.035em] mb-[22px] max-w-[18ch] [text-wrap:balance]" style="--delay: .08s">
                No seats. No plans. No subscription.</h1>
            <p class="reveal text-[17px] sm:text-[19px] leading-[1.6] max-w-[60ch] mb-10 text-[rgba(232,236,242,.66)] [text-wrap:pretty]" style="--delay: .16s">
                You buy credits once, at one flat rate, and they never expire. Every AI step has a published price and
                is charged only when it actually runs. Everything that is not AI, verifying an address and sending the
                mail included, costs nothing on every edition, forever.</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-[14px]">
                @foreach ($figures as $figure)
                    <div class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7" style="--delay: {{ .24 + $loop->index * .08 }}s">
                        <div class="font-[Sora,sans-serif] font-semibold text-[40px] leading-none tracking-[-.03em] mb-3">{{ $figure['figure'] }}</div>
                        <div class="text-[14px] text-[rgba(232,236,242,.58)]">{{ $figure['caption'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- The grid --}}
    <section id="grid" class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-8 lg:mb-11">
                <div class="reveal">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        The grid
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        What a credit actually buys.</h2>
                </div>
                <p class="reveal text-[rgba(232,236,242,.62)] max-w-[48ch]" style="--delay: .1s">Most tools bill you for
                    a tier and leave you to work out what you used. Here is what every AI step costs, and each one is
                    charged only when it runs.</p>
            </div>

            <div class="reveal overflow-x-auto border border-[rgba(232,236,242,.09)] rounded-xl bg-[#101520]">
                <table class="w-full border-collapse text-left text-[14.5px]">
                    <thead>
                        <tr class="border-b border-[rgba(232,236,242,.12)] font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)]">
                            <th class="px-6 py-4 font-normal">What runs</th>
                            <th class="px-6 py-4 font-normal whitespace-nowrap">Credits</th>
                            <th class="px-6 py-4 font-normal">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid as $row)
                            <tr class="border-b border-[rgba(232,236,242,.07)] last:border-b-0">
                                <td class="px-6 py-4 text-[rgba(232,236,242,.82)]">{{ $row['what'] }}</td>
                                <td class="px-6 py-4 font-[Geist_Mono,monospace] text-[#6fd3ec] whitespace-nowrap">{{ $row['credits'] }}</td>
                                <td class="px-6 py-4 text-[rgba(232,236,242,.55)]">{{ $row['when'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="reveal mt-5 text-[13.5px] text-[rgba(232,236,242,.45)] max-w-[70ch]">Prices can change as the
                models behind them do. A change never re-charges a run you already paid for and never takes credits off
                your balance; it applies to runs made after it, including the ones you pay for out of credits you bought
                earlier. Ad budgets are not in this table at all: that money goes straight to the ad platform, and we
                take no percentage of it.
            </p>
        </div>
    </section>

    {{-- What costs nothing --}}
    <section class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="reveal font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                Zero credits
            </div>
            <h2 class="reveal font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em] mb-8 lg:mb-10 max-w-[26ch]">
                The parts other tools meter.</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-[14px]">
                @foreach ($free as $item)
                    <div class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7" style="--delay: {{ $loop->index * .08 }}s">
                        <span class="w-10 h-10 mb-4 rounded-lg border border-[rgba(111,211,236,.25)] bg-[rgba(111,211,236,.06)] text-[#6fd3ec] flex items-center justify-center">
                            <x-marketing.icon :name="$item['icon']" class="w-5 h-5" />
                        </span>
                        <h3 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em] mb-[6px]">{{ $item['title'] }}</h3>
                        <p class="text-[14.5px] text-[rgba(232,236,242,.6)]">{{ $item['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Editions --}}
    <section id="editions" class="border-b border-[rgba(232,236,242,.08)] bg-[#0d1119]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="reveal font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                Two editions
            </div>
            <h2 class="reveal font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em] mb-8 lg:mb-10 max-w-[26ch]">
                Pay us for the hosting, or run it yourself.</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-[14px]">
                <div class="reveal lift border border-[rgba(111,211,236,.35)] bg-[linear-gradient(180deg,rgba(111,211,236,.07),rgba(111,211,236,.015))] rounded-xl px-6 py-7 flex flex-col">
                    <div class="flex items-center gap-2 font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[#6fd3ec] mb-3">
                        <x-marketing.icon name="cloud" class="w-4 h-4" /> Cloud
                    </div>
                    <h3 class="font-[Sora,sans-serif] font-semibold text-[22px] tracking-[-.02em] mb-[10px]">Nothing to run.</h3>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)] mb-6">Hosted and backed up for you, AI included
                        and metered in credits at the rate above. Starts with a knowledge base that is already big and
                        keeps growing.</p>
                    <a href="{{ $signupUrl }}" class="cta-btn mt-auto self-start py-[11px] px-[22px] rounded-lg font-[Sora,sans-serif] font-semibold text-[15px]">Start free</a>
                </div>
                <div class="reveal lift bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7 flex flex-col" style="--delay: .1s">
                    <div class="flex items-center gap-2 font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] uppercase text-[rgba(232,236,242,.45)] mb-3">
                        <x-marketing.icon name="server" class="w-4 h-4" /> Self-hosted
                    </div>
                    <h3 class="font-[Sora,sans-serif] font-semibold text-[22px] tracking-[-.02em] mb-[10px]">Free forever. AGPL-3.0.</h3>
                    <p class="text-[14.5px] text-[rgba(232,236,242,.6)] mb-6">Docker, five minutes, your own AI provider
                        key, which you pay that provider directly and nothing to us. The same code with no feature gate:
                        nothing removed, nothing metered, unlimited mailboxes and seats, and your data never leaves your
                        machine.</p>
                    <a href="https://docs.eveil.cloud/self-hosted/installation" class="ghost-btn mt-auto self-start border border-[rgba(232,236,242,.18)] py-[11px] px-[22px] rounded-lg font-[Sora,sans-serif] font-semibold text-[15px]">Read the install guide</a>
                </div>
            </div>
        </div>
    </section>

    {{-- One balance vs four subscriptions --}}
    <section class="border-b border-[rgba(232,236,242,.08)]">
        <div class="max-w-[1180px] mx-auto px-6 py-16 lg:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-[60px] md:items-end mb-8 lg:mb-11">
                <div class="reveal">
                    <div class="font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase text-[#6fd3ec] mb-[14px]">
                        One balance
                    </div>
                    <h2 class="font-[Sora,sans-serif] font-semibold text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.12] tracking-[-.03em]">
                        The usual way to do outbound is four invoices.</h2>
                </div>
                <p class="reveal text-[rgba(232,236,242,.62)] max-w-[48ch]" style="--delay: .1s">We are comparing
                    pricing models here, not naming anyone: their prices change every quarter, and the shape of the
                    deal is what actually costs you money.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-[14px]">
                <div class="reveal bg-[#101520] border border-[rgba(232,236,242,.09)] rounded-xl px-6 py-7">
                    <h3 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em] mb-5">The usual stack</h3>
                    <ul class="flex flex-col gap-[14px]">
                        @foreach ($usual as $line)
                            <li class="flex gap-3 text-[14.5px] text-[rgba(232,236,242,.6)]">
                                <x-marketing.icon name="ban" class="w-[18px] h-[18px] shrink-0 mt-[3px] text-[#ff9a9a]" />
                                <span>{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="reveal lift border border-[rgba(111,211,236,.35)] bg-[linear-gradient(180deg,rgba(111,211,236,.07),rgba(111,211,236,.015))] rounded-xl px-6 py-7" style="--delay: .1s">
                    <h3 class="font-[Sora,sans-serif] font-semibold text-[19px] tracking-[-.02em] mb-5">Eveil</h3>
                    <ul class="flex flex-col gap-[14px]">
                        @foreach ($instead as $line)
                            <li class="flex gap-3 text-[14.5px] text-[rgba(232,236,242,.72)]">
                                <x-marketing.icon name="check" class="w-[18px] h-[18px] shrink-0 mt-[3px] text-[#6fd3ec]" />
                                <span>{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
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
                    Before you top up.</h2>
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
                credits, no card. Spend nothing more until you decide to.</p>
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
