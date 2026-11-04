@php
    $signupUrl = Route::has('register') ? route('register') : route('home');
    $checks = isset($result) ? [
        'SPF' => ['host' => $domain, 'check' => $result['spf']],
        'DMARC' => ['host' => '_dmarc.'.$domain, 'check' => $result['dmarc']],
    ] : [];
@endphp
<x-marketing-layout
    :title="'Free SPF and DMARC checker - ' . config('app.name')"
    description="Check a domain's SPF and DMARC records for free, no sign-up. See what is missing or wrong and get a record to publish."
    :canonical="route('tools.spf-dmarc')"
>
    <div class="mx-auto max-w-[860px] px-6 pt-[72px] pb-[88px]">
        <div class="mb-[14px] font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] text-[#6fd3ec] uppercase">Free tool</div>
        <h1 class="mb-4 font-[Sora,sans-serif] text-[44px] leading-[1.1] font-semibold tracking-[-.035em]">SPF and DMARC checker</h1>
        <p class="mb-9 max-w-[60ch] text-[rgba(232,236,242,.66)]">Type the domain you send email from. We read its SPF and DMARC records from DNS, tell you what is missing or wrong, and suggest a record to publish when there is none. No sign-up, and nothing is sent.</p>

        <form method="POST" action="{{ route('tools.spf-dmarc.check') }}" class="mb-10 rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-5">
            @csrf
            <label for="t-domain" class="mb-[9px] block font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Domain</label>
            <div class="flex gap-[9px]">
                <input id="t-domain" name="domain" type="text" required placeholder="example.com" class="field min-w-0 flex-1 font-[Geist_Mono,monospace]" value="{{ old('domain', $domain ?? '') }}">
                <button type="submit" class="cta-btn cursor-pointer rounded-[9px] border-0 px-[22px] py-3 font-[Sora,sans-serif] text-[15px] font-semibold whitespace-nowrap">Check</button>
            </div>
            @error('domain') <p class="mt-2 text-[13px] text-[#f0a0a0]">{{ $message }}</p> @enderror
        </form>

        @foreach ($checks as $name => $item)
            @php($check = $item['check'])
            <section class="mb-5 rounded-xl border border-[rgba(232,236,242,.09)] bg-[#101520] px-[22px] py-5">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h2 class="font-[Sora,sans-serif] text-[19px] font-semibold tracking-[-.02em]">{{ $name }}</h2>
                    @if ($check['problems'] === [])
                        <span class="rounded-full bg-[rgba(111,211,236,.12)] px-3 py-1 font-[Geist_Mono,monospace] text-[11px] text-[#a8e6f6] uppercase">Looks right</span>
                    @else
                        <span class="rounded-full bg-[rgba(240,160,160,.12)] px-3 py-1 font-[Geist_Mono,monospace] text-[11px] text-[#f0a0a0] uppercase">{{ trans_choice(':count issue|:count issues', count($check['problems'])) }}</span>
                    @endif
                </div>

                <div class="mb-1 font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.42)] uppercase">TXT record at {{ $item['host'] }}</div>
                <p class="mb-3 font-[Geist_Mono,monospace] text-[13px] break-all text-[rgba(232,236,242,.8)]">{{ $check['record'] ?? 'Not found' }}</p>

                @if ($check['problems'] !== [])
                    <ul class="mb-3 list-disc pl-5 text-sm text-[rgba(232,236,242,.66)]">
                        @foreach ($check['problems'] as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($check['suggestion'])
                    <div class="rounded-[9px] border border-[rgba(111,211,236,.35)] bg-[rgba(111,211,236,.08)] px-4 py-3">
                        <div class="mb-1 font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Suggested record: TXT at {{ $item['host'] }}</div>
                        <p class="font-[Geist_Mono,monospace] text-[13px] break-all text-[#a8e6f6]">{{ $check['suggestion'] }}</p>
                    </div>
                @endif
            </section>
        @endforeach

        <div class="mt-12 grid gap-6 text-[rgba(232,236,242,.66)]">
            <div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em] text-[#e8ecf2]">What SPF does</h2>
                <p>SPF lists the servers allowed to send mail for your domain. A receiving server checks the sender against that list. Without it, or with a record that breaks one of the rules, your mail is more likely to land in spam.</p>
            </div>
            <div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em] text-[#e8ecf2]">What DMARC does</h2>
                <p>DMARC tells receivers what to do with mail that fails SPF or DKIM: deliver it, send it to spam, or reject it. It also asks them to send you reports, so you can see who sends mail as your domain. Start with p=none, read the reports, then tighten the policy.</p>
            </div>
            <div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em] text-[#e8ecf2]">And DKIM?</h2>
                <p>DKIM signs each message. Its record sits under a selector name that only your mail provider knows, so it cannot be looked up from the domain alone. Your provider's admin panel shows the selector and the record to publish.</p>
            </div>
        </div>

        <div class="mt-12 rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-7">
            <h2 class="mb-1.5 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em]">Records in place? Start sending.</h2>
            <p class="mb-5 text-[rgba(232,236,242,.66)]">Eveil finds your prospects, writes the emails and sends them from your own mailbox, one at a time, like a person would.</p>
            <a href="{{ $signupUrl }}" class="cta-btn inline-block rounded-lg px-[22px] py-[11px] font-[Sora,sans-serif] text-[15px] font-semibold">Start free</a>
        </div>
    </div>
</x-marketing-layout>
