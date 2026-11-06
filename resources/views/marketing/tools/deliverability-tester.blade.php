@php
    $signupUrl = Route::has('register') ? route('register') : route('home');
    $statusStyles = [
        'pass' => ['OK', 'text-[#a8e6f6]'],
        'warn' => ['Warning', 'text-[#f0d48a]'],
        'fail' => ['Problem', 'text-[#f0a0a0]'],
        'unknown' => ['Not reported', 'text-[rgba(232,236,242,.42)]'],
    ];
@endphp
<x-marketing-layout
    :title="'Free email deliverability tester - ' . config('app.name')"
    description="Send one email to a test address and see, for free and without sign-up, whether it passes SPF, DKIM and DMARC and what spam filters will make of it."
    :canonical="route('tools.deliverability')"
    :noindex="isset($address)"
>
    <div class="mx-auto max-w-[860px] px-6 pt-[72px] pb-[88px]">
        <div class="mb-[14px] font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] text-[#6fd3ec] uppercase">Free tool</div>
        <h1 class="mb-4 font-[Sora,sans-serif] text-[44px] leading-[1.1] font-semibold tracking-[-.035em]">Email deliverability tester</h1>
        <p class="mb-9 max-w-[60ch] text-[rgba(232,236,242,.66)]">Get a test address, send it a real email from the mailbox you prospect with, and see how it arrived: SPF, DKIM and DMARC results, your domain's records, and what filters look for in the message itself. No sign-up.</p>

        @if (! isset($address))
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <form method="POST" action="{{ route('tools.deliverability.store') }}" class="mb-10 rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-5">
                @csrf
                <div class="cf-turnstile mb-3" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="dark"></div>
                <button type="submit" class="cta-btn cursor-pointer rounded-[9px] border-0 px-[22px] py-3 font-[Sora,sans-serif] text-[15px] font-semibold whitespace-nowrap">Get a test address</button>
                @error('turnstile') <p class="mt-2 text-[13px] text-[#f0a0a0]">{{ $message }}</p> @enderror
            </form>
        @elseif ($report === null)
            <section class="mb-10 rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-5">
                <div class="mb-[9px] font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Send one email to</div>
                <div class="mb-4 font-[Geist_Mono,monospace] text-[19px] break-all text-[#e8ecf2] select-all">{{ $address }}</div>
                <p class="text-sm text-[rgba(232,236,242,.66)]">Write it the way you would write to a prospect, from the same mailbox. This page refreshes on its own and shows the report as soon as the email arrives. The address accepts one email and expires in an hour.</p>
            </section>
        @else
            <section class="mb-5 rounded-xl border border-[rgba(232,236,242,.09)] bg-[#101520] px-[22px] py-5">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="font-[Sora,sans-serif] text-[19px] font-semibold tracking-[-.02em] break-all">{{ $report['subject'] ?? '(no subject)' }}</h2>
                        <p class="font-[Geist_Mono,monospace] text-[13px] break-all text-[rgba(232,236,242,.5)]">From {{ $report['from'] ?? 'an unreadable address' }}</p>
                    </div>
                    <span class="rounded-full bg-[rgba(111,211,236,.12)] px-3 py-1 font-[Geist_Mono,monospace] text-[15px] whitespace-nowrap text-[#a8e6f6]">{{ $report['score'] }} / 10</span>
                </div>

                <dl class="grid gap-3 text-sm">
                    @foreach ($report['checks'] as $check)
                        <div class="grid gap-1 sm:grid-cols-[180px_100px_1fr]">
                            <dt class="font-[Geist_Mono,monospace] text-[10.5px] leading-[2] tracking-[.08em] text-[rgba(232,236,242,.42)] uppercase">{{ $check['label'] }}</dt>
                            <dd class="font-[Geist_Mono,monospace] text-[11px] leading-[2] uppercase {{ $statusStyles[$check['status']][1] }}">{{ $statusStyles[$check['status']][0] }}</dd>
                            <dd class="font-[Geist_Mono,monospace] text-[13px] break-all text-[rgba(232,236,242,.8)]">{{ $check['detail'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <p class="mb-5 text-sm text-[rgba(232,236,242,.66)]">This report stays at this address for 24 hours. <a href="{{ route('tools.spf-dmarc') }}">Fix your SPF and DMARC records</a>, or <a href="{{ route('tools.deliverability') }}">run another test</a>.</p>
        @endif

        <div class="mt-12 grid gap-6 text-[rgba(232,236,242,.66)]">
            <div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em] text-[#e8ecf2]">What this checks</h2>
                <p>Whether the receiving server accepted the message as really yours (SPF, DKIM and DMARC), whether your domain publishes the records receivers look for, and what the message carries: an unsubscribe header, a plain-text version, link shorteners, the subject line and its size.</p>
            </div>
            <div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em] text-[#e8ecf2]">What happens to your email</h2>
                <p>It is read once to build the report, then dropped. Only the report is kept, for 24 hours, and only someone with the link can see it.</p>
            </div>
        </div>

        <div class="mt-12 rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-7">
            <h2 class="mb-1.5 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em]">Mail that lands, sent for you</h2>
            <p class="mb-5 text-[rgba(232,236,242,.66)]">Eveil finds your prospects and their contacts, checks every address before it sends, and writes each email from your own mailbox, one at a time, like a person would.</p>
            <a href="{{ $signupUrl }}" class="cta-btn inline-block rounded-lg px-[22px] py-[11px] font-[Sora,sans-serif] text-[15px] font-semibold">Start free</a>
        </div>
    </div>
</x-marketing-layout>
