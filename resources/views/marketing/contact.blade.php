<x-marketing-layout
    :title="config('app.name') . ' - Contact'"
    description="Reach the Eveil team for support, privacy and erasure requests, or self-hosted bugs and issues."
>
    <div class="mx-auto grid max-w-[1180px] grid-cols-2 items-start gap-16 px-6 pt-[72px] pb-[88px]">
        <div>
            <div class="mb-[14px] font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] text-[#6fd3ec] uppercase">Contact</div>
            <h1 class="mb-4 font-[Sora,sans-serif] text-[44px] leading-[1.1] font-semibold tracking-[-.035em]">Talk to a person, not a bot.</h1>
            <p class="mb-9 max-w-[52ch] text-[rgba(232,236,242,.66)]">A real person on the Eveil team reads and answers every message, in English. Technical questions about the self-hosted edition belong on GitHub, where the answer helps the next person too.</p>

            <div class="mb-9 grid gap-3">
                <div class="rounded-xl border border-[rgba(232,236,242,.09)] bg-[#101520] px-[22px] py-5">
                    <div class="mb-2 font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.42)] uppercase">Support</div>
                    <a href="mailto:support@eveil.cloud" class="font-[Sora,sans-serif] text-[19px] font-semibold tracking-[-.02em]">support@eveil.cloud</a>
                    <p class="mt-1.5 text-sm text-[rgba(232,236,242,.55)]">We reply within 24 hours.</p>
                </div>
                <div class="rounded-xl border border-[rgba(232,236,242,.09)] bg-[#101520] px-[22px] py-5">
                    <div class="mb-2 font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.42)] uppercase">Bugs and self-hosting</div>
                    <a href="https://github.com/Dricle/eveil" class="font-[Sora,sans-serif] text-[19px] font-semibold tracking-[-.02em]">github.com/Dricle/eveil</a>
                    <p class="mt-1.5 text-sm text-[rgba(232,236,242,.55)]">Issues and discussions, in the open.</p>
                </div>
            </div>

            <div class="border-t border-[rgba(232,236,242,.08)] pt-[22px] text-sm text-[rgba(232,236,242,.5)]">
                <div class="mb-2 font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.38)] uppercase">Registered entity</div>
                <p>DRICLE LLP<br>5 Brayford Square<br>London, E1 0SG<br>United Kingdom<br>Registration OC453390</p>
            </div>
        </div>

        <div class="rounded-[14px] border border-[rgba(232,236,242,.12)] bg-gradient-to-b from-[rgba(232,236,242,.055)] to-[rgba(232,236,242,.02)] p-7">
            <h2 class="mb-1.5 font-[Sora,sans-serif] text-2xl font-semibold tracking-[-.025em]">Send a message</h2>
            <p class="mb-6 text-sm text-[rgba(232,236,242,.55)]">Goes straight to support@eveil.cloud. We reply to the email address you give below.</p>

            @if (session('status'))
                <div class="mb-4 rounded-[9px] border border-[rgba(111,211,236,.35)] bg-[rgba(111,211,236,.12)] px-4 py-[13px] text-sm text-[#a8e6f6]">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" class="grid gap-4">
                @csrf
                <div class="grid gap-[7px]">
                    <label for="c-name" class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Name</label>
                    <input id="c-name" name="name" type="text" placeholder="Your name" class="field" value="{{ old('name') }}" required>
                    @error('name') <p class="text-[13px] text-[#f0a0a0]">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-[7px]">
                    <label for="c-email" class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Email</label>
                    <input id="c-email" name="email" type="email" placeholder="you@company.com" class="field font-[Geist_Mono,monospace]" value="{{ old('email') }}" required>
                    @error('email') <p class="text-[13px] text-[#f0a0a0]">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-[7px]">
                    <label for="c-topic" class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Topic</label>
                    <select id="c-topic" name="topic" class="field">
                        @foreach (\App\Http\Requests\ContactMessageRequest::TOPICS as $topic)
                            <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-[7px]">
                    <label for="c-msg" class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] text-[rgba(232,236,242,.5)] uppercase">Message</label>
                    <textarea id="c-msg" name="message" rows="6" placeholder="What do you need?" class="field resize-y" required>{{ old('message') }}</textarea>
                    @error('message') <p class="text-[13px] text-[#f0a0a0]">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="cta-btn cursor-pointer rounded-[9px] border-0 px-[22px] py-[13px] font-[Sora,sans-serif] text-[15px] font-semibold">Send message</button>
                <p class="text-[13px] text-[rgba(232,236,242,.45)]">Used only to answer this message.</p>
            </form>
        </div>
    </div>
</x-marketing-layout>
