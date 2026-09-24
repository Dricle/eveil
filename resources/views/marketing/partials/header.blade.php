<header class="sticky top-0 z-20 bg-[rgba(11,14,20,.82)] backdrop-blur-[10px] border-b border-[rgba(232,236,242,.08)]">
    <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-4 flex items-center gap-6 lg:gap-[34px]">
        <a href="{{ route('home') }}" class="flex items-center gap-[10px] text-[#e8ecf2]">
            <svg viewBox="0 0 64 64" class="w-[26px] h-[26px] block" aria-hidden="true">
                <rect width="64" height="64" rx="14" fill="#6fd3ec"></rect>
                <g fill="#06222c">
                    <path d="M20 40 A12 12 0 0 1 44 40 Z"></path>
                    <rect x="9" y="39.5" width="46" height="5" rx="2.5"></rect>
                    <rect x="29.5" y="14" width="5" height="9" rx="2.5"></rect>
                    <rect x="19" y="15" width="5" height="9" rx="2.5" transform="rotate(-30 21.5 19.5)"></rect>
                    <rect x="40" y="15" width="5" height="9" rx="2.5" transform="rotate(30 42.5 19.5)"></rect>
                </g>
            </svg>
            <span class="font-[Sora,sans-serif] font-semibold text-[18px] tracking-[-.01em]">eveil<span class="text-[rgba(232,236,242,.4)]">.cloud</span></span>
        </a>
        <nav class="hidden lg:flex gap-6 text-[14.5px] ml-auto">
            <a href="{{ route('home') }}#how" class="text-[rgba(232,236,242,.72)]">How it works</a>
            <a href="{{ route('home') }}#agents" class="text-[rgba(232,236,242,.72)]">Agents</a>
            <a href="{{ route('home') }}#editions" class="text-[rgba(232,236,242,.72)]">Self-hosted</a>
            <a href="{{ route('home') }}#pricing" class="text-[rgba(232,236,242,.72)]">Pricing</a>
            <a href="{{ route('blog.index') }}" class="text-[rgba(232,236,242,.72)]">Blog</a>
            <a href="https://github.com/Dricle/eveil" class="text-[rgba(232,236,242,.72)]">GitHub</a>
        </nav>
        <a href="{{ route('login') }}" class="ghost-btn ml-auto lg:ml-0 border border-[rgba(232,236,242,.18)] px-[14px] sm:px-[18px] py-[9px] rounded-lg font-[Sora,sans-serif] font-semibold text-[14px]">Log in</a>
    </div>
</header>
