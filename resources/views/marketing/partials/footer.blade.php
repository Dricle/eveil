<footer class="border-t border-[rgba(232,236,242,.08)] bg-[#0d1119]">
    <div class="max-w-[1180px] mx-auto px-6 pt-12 sm:pt-[52px] pb-9 grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr] gap-8 sm:gap-10">
        <div class="col-span-2 lg:col-span-1">
            <div class="flex items-center gap-[10px] mb-3">
                <svg viewBox="0 0 64 64" class="w-[22px] h-[22px] block" aria-hidden="true">
                    <rect width="64" height="64" rx="14" fill="#6fd3ec"></rect>
                    <g fill="#06222c">
                        <path d="M20 40 A12 12 0 0 1 44 40 Z"></path>
                        <rect x="9" y="39.5" width="46" height="5" rx="2.5"></rect>
                        <rect x="29.5" y="14" width="5" height="9" rx="2.5"></rect>
                        <rect x="19" y="15" width="5" height="9" rx="2.5" transform="rotate(-30 21.5 19.5)"></rect>
                        <rect x="40" y="15" width="5" height="9" rx="2.5" transform="rotate(30 42.5 19.5)"></rect>
                    </g>
                </svg>
                <span class="font-[Sora,sans-serif] font-semibold text-[17px]">eveil.cloud</span>
            </div>
            <p class="text-[13.5px] text-[rgba(232,236,242,.5)] max-w-[36ch]">Organic, automated AI marketing. Auto cold email, auto LinkedIn content, and Reddit reply drafts today, no purchased lists, no ad spend required to start.</p>
        </div>
        <div class="flex flex-col gap-[10px] text-[14px]">
            <div class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] uppercase text-[rgba(232,236,242,.38)] mb-[2px]">Product</div>
            <a href="{{ route('home') }}#how" class="text-[rgba(232,236,242,.7)]">How it works</a>
            <a href="{{ route('home') }}#agents" class="text-[rgba(232,236,242,.7)]">Agents</a>
            <a href="{{ route('home') }}#pricing" class="text-[rgba(232,236,242,.7)]">Pricing</a>
            <a href="{{ route('home') }}#editions" class="text-[rgba(232,236,242,.7)]">Cloud vs self-hosted</a>
        </div>
        <div class="flex flex-col gap-[10px] text-[14px]">
            <div class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] uppercase text-[rgba(232,236,242,.38)] mb-[2px]">Open source</div>
            <a href="https://github.com/Dricle/eveil" class="text-[rgba(232,236,242,.7)]">GitHub</a>
            <a href="https://docs.eveil.cloud/" class="text-[rgba(232,236,242,.7)]">Docs</a>
            <a href="https://docs.eveil.cloud/self-hosted/installation" class="text-[rgba(232,236,242,.7)]">Self-host guide</a>
            <a href="https://github.com/Dricle/eveil/blob/main/LICENSE" class="text-[rgba(232,236,242,.7)]">AGPL-3.0 licence</a>
        </div>
        <div class="flex flex-col gap-[10px] text-[14px]">
            <div class="font-[Geist_Mono,monospace] text-[10.5px] tracking-[.08em] uppercase text-[rgba(232,236,242,.38)] mb-[2px]">Legal</div>
            <a href="{{ route('privacy') }}" class="text-[rgba(232,236,242,.7)]">Privacy</a>
            <a href="{{ route('terms') }}" class="text-[rgba(232,236,242,.7)]">Terms</a>
            <a href="{{ route('data-retention') }}" class="text-[rgba(232,236,242,.7)]">Data retention</a>
            <a href="{{ route('contact') }}" class="text-[rgba(232,236,242,.7)]">Contact</a>
        </div>
    </div>
    <div class="max-w-[1180px] mx-auto px-6 pt-[18px] pb-10 flex flex-col sm:flex-row sm:justify-between gap-[10px] sm:gap-5 text-[12.5px] text-[rgba(232,236,242,.4)] border-t border-[rgba(232,236,242,.07)]">
        <span>© {{ now()->year }} Eveil. Self-hostable under AGPL-3.0.</span>
        <span>No tracking pixels. No purchased lists. No unsubscribe footer.</span>
    </div>
</footer>
