@php
    $signupUrl = Route::has('register') ? route('register') : route('home');
    $rows = isset($result) ? array_filter([
        'Syntax' => [$result['valid_syntax'], $result['valid_syntax'] ? 'Well formed' : 'Not a valid email address'],
        'Disposable domain' => $result['domain'] === null ? null : [! $result['disposable'], $result['disposable'] ? 'Yes, a throwaway address provider' : 'No'],
        'Mail servers (MX)' => $result['domain'] === null || $result['disposable'] ? null : [$result['mx'] !== [], $result['mx'] === [] ? 'None: '.$result['domain'].' receives no mail' : implode(', ', $result['mx'])],
        'Mail provider' => $result['mx'] === [] ? null : [true, $result['provider'] ?? 'Other'],
    ]) : [];
@endphp
<x-marketing-layout
    :title="'Free email address checker - ' . config('app.name')"
    description="Check an email address for free, no sign-up: syntax, disposable domain, mail servers, and the domain's SPF and DMARC records."
    :canonical="route('tools.email-checker')"
>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <div class="mx-auto max-w-[860px] px-6 pt-[72px] pb-[88px]">
        <div class="mb-[14px] font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] text-[#6fd3ec] uppercase">Free tool</div>
        <h1 class="mb-4 font-[Sora,sans-serif] text-[44px] leading-[1.1] font-semibold tracking-[-.035em]">Email address checker</h1>
        <p class="mb-9 max-w-[60ch] text-[rgba(232,236,242,.66)]">Type an email address. We check its syntax, whether the domain hands out throwaway addresses, whether it receives mail at all, and how its SPF and DMARC records look. No sign-up, and nothing is sent.</p>

        <form method="POST" action="{{ route('tools.email-checker.check') }}" class="mb-10 rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-5">
            @csrf
            <label for="t-email" class="mb-[9px] block font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Email address</label>
            <div class="flex gap-[9px]">
                <input id="t-email" name="email" type="text" required placeholder="name@example.com" class="field min-w-0 flex-1 font-[Geist_Mono,monospace]" value="{{ old('email', $email ?? '') }}">
                <button type="submit" class="cta-btn cursor-pointer rounded-[9px] border-0 px-[22px] py-3 font-[Sora,sans-serif] text-[15px] font-semibold whitespace-nowrap">Check</button>
            </div>
            <div class="cf-turnstile mt-3" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="dark"></div>
            @error('email') <p class="mt-2 text-[13px] text-[#f0a0a0]">{{ $message }}</p> @enderror
        </form>

        @isset($result)
            <section class="mb-5 rounded-xl border border-[rgba(232,236,242,.09)] bg-[#101520] px-[22px] py-5">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h2 class="font-[Sora,sans-serif] text-[19px] font-semibold tracking-[-.02em] break-all">{{ $email }}</h2>
                    @if ($result['invalid'])
                        <span class="rounded-full bg-[rgba(240,160,160,.12)] px-3 py-1 font-[Geist_Mono,monospace] text-[11px] whitespace-nowrap text-[#f0a0a0] uppercase">Invalid</span>
                    @else
                        <span class="rounded-full bg-[rgba(111,211,236,.12)] px-3 py-1 font-[Geist_Mono,monospace] text-[11px] whitespace-nowrap text-[#a8e6f6] uppercase">Domain receives mail</span>
                    @endif
                </div>

                <dl class="mb-3 grid gap-2 text-sm">
                    @foreach ($rows as $label => [$passes, $detail])
                        <div class="grid gap-1 sm:grid-cols-[180px_1fr]">
                            <dt class="font-[Geist_Mono,monospace] text-[10.5px] leading-[2] tracking-[.08em] text-[rgba(232,236,242,.42)] uppercase">{{ $label }}</dt>
                            <dd class="font-[Geist_Mono,monospace] text-[13px] break-all {{ $passes ? 'text-[rgba(232,236,242,.8)]' : 'text-[#f0a0a0]' }}">{{ $detail }}</dd>
                        </div>
                    @endforeach
                </dl>

                @unless ($result['invalid'])
                    <p class="text-sm text-[rgba(232,236,242,.66)]">The domain accepts mail, but that does not prove this mailbox exists. Only the receiving server knows, and Google and Microsoft refuse to say.</p>
                @endunless
            </section>

            @if ($result['authentication'])
                <section class="mb-5 rounded-xl border border-[rgba(232,236,242,.09)] bg-[#101520] px-[22px] py-5">
                    <h2 class="mb-3 font-[Sora,sans-serif] text-[19px] font-semibold tracking-[-.02em]">Sender authentication for {{ $result['domain'] }}</h2>
                    <dl class="mb-3 grid gap-2 text-sm">
                        @foreach (['SPF' => $result['authentication']['spf'], 'DMARC' => $result['authentication']['dmarc']] as $name => $check)
                            <div class="grid gap-1 sm:grid-cols-[180px_1fr]">
                                <dt class="font-[Geist_Mono,monospace] text-[10.5px] leading-[2] tracking-[.08em] text-[rgba(232,236,242,.42)] uppercase">{{ $name }}</dt>
                                <dd class="font-[Geist_Mono,monospace] text-[13px] {{ $check['problems'] === [] ? 'text-[rgba(232,236,242,.8)]' : 'text-[#f0a0a0]' }}">
                                    {{ $check['problems'] === [] ? 'Looks right' : trans_choice(':count issue|:count issues', count($check['problems'])) }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="text-sm text-[rgba(232,236,242,.66)]">This matters if you send from this domain. <a href="{{ route('tools.spf-dmarc') }}">See the details and a record to publish</a>.</p>
                </section>
            @endif
        @endisset

        <div class="mt-12 grid gap-6 text-[rgba(232,236,242,.66)]">
            <div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em] text-[#e8ecf2]">What this checks</h2>
                <p>Whether the address is well formed, whether its domain gives out throwaway addresses, and whether the domain has mail servers at all. Any of those failing means mail to this address will not arrive.</p>
            </div>
            <div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em] text-[#e8ecf2]">Why it does not confirm the mailbox</h2>
                <p>The only way to know a mailbox exists is to ask the receiving server. Google and Microsoft, which host a large share of business mail, refuse to answer, and a server that accepts every address answers yes to everything. A checker that says "valid" for those is guessing. This one tells you what it can prove.</p>
            </div>
        </div>

        <div class="mt-12 rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-7">
            <h2 class="mb-1.5 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em]">Need the addresses in the first place?</h2>
            <p class="mb-5 text-[rgba(232,236,242,.66)]">Eveil finds your prospects and their contacts, checks every address before it sends, and writes each email from your own mailbox, one at a time, like a person would.</p>
            <a href="{{ $signupUrl }}" class="cta-btn inline-block rounded-lg px-[22px] py-[11px] font-[Sora,sans-serif] text-[15px] font-semibold">Start free</a>
        </div>
    </div>
</x-marketing-layout>
