<header style="position:sticky;top:0;z-index:20;background:rgba(11,14,20,.82);backdrop-filter:blur(10px);border-bottom:1px solid rgba(232,236,242,.08)">
    <div style="max-width:1180px;margin:0 auto;padding:16px 24px;display:flex;align-items:center;gap:34px">
        <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:10px;color:#e8ecf2">
            <svg viewBox="0 0 64 64" style="width:26px;height:26px;display:block" aria-hidden="true">
                <rect width="64" height="64" rx="14" fill="#6fd3ec"></rect>
                <g fill="#06222c">
                    <path d="M20 40 A12 12 0 0 1 44 40 Z"></path>
                    <rect x="9" y="39.5" width="46" height="5" rx="2.5"></rect>
                    <rect x="29.5" y="14" width="5" height="9" rx="2.5"></rect>
                    <rect x="19" y="15" width="5" height="9" rx="2.5" transform="rotate(-30 21.5 19.5)"></rect>
                    <rect x="40" y="15" width="5" height="9" rx="2.5" transform="rotate(30 42.5 19.5)"></rect>
                </g>
            </svg>
            <span style="font-family:'Sora',sans-serif;font-weight:600;font-size:18px;letter-spacing:-.01em">eveil<span style="color:rgba(232,236,242,.4)">.cloud</span></span>
        </a>
        <nav style="display:flex;gap:24px;font-size:14.5px;margin-left:auto">
            <a href="{{ route('home') }}#how" style="color:rgba(232,236,242,.72)">How it works</a>
            <a href="{{ route('home') }}#agents" style="color:rgba(232,236,242,.72)">Agents</a>
            <a href="{{ route('home') }}#editions" style="color:rgba(232,236,242,.72)">Self-hosted</a>
            <a href="{{ route('home') }}#pricing" style="color:rgba(232,236,242,.72)">Pricing</a>
            <a href="https://github.com/Dricle/eveil" style="color:rgba(232,236,242,.72)">GitHub</a>
        </nav>
        <a href="{{ route('home') }}#top" class="cta-btn" style="padding:9px 18px;border-radius:8px;font-family:'Sora',sans-serif;font-weight:600;font-size:14px">Start free</a>
    </div>
</header>
