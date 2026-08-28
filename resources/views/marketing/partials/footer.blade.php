<footer style="border-top:1px solid rgba(232,236,242,.08);background:#0d1119">
    <div style="max-width:1180px;margin:0 auto;padding:52px 24px 36px;display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:40px">
        <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                <svg viewBox="0 0 64 64" style="width:22px;height:22px;display:block" aria-hidden="true">
                    <rect width="64" height="64" rx="14" fill="#6fd3ec"></rect>
                    <g fill="#06222c">
                        <path d="M20 40 A12 12 0 0 1 44 40 Z"></path>
                        <rect x="9" y="39.5" width="46" height="5" rx="2.5"></rect>
                        <rect x="29.5" y="14" width="5" height="9" rx="2.5"></rect>
                        <rect x="19" y="15" width="5" height="9" rx="2.5" transform="rotate(-30 21.5 19.5)"></rect>
                        <rect x="40" y="15" width="5" height="9" rx="2.5" transform="rotate(30 42.5 19.5)"></rect>
                    </g>
                </svg>
                <span style="font-family:'Sora',sans-serif;font-weight:600;font-size:17px">eveil.cloud</span>
            </div>
            <p style="margin:0;font-size:13.5px;color:rgba(232,236,242,.5);max-width:36ch">The open-source alternative to lemlist. Multichannel sequencing, AI personalisation, deliverability built in, one unified inbox.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:14px">
            <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.38);margin-bottom:2px">Product</div>
            <a href="{{ route('home') }}#how" style="color:rgba(232,236,242,.7)">How it works</a>
            <a href="{{ route('home') }}#agents" style="color:rgba(232,236,242,.7)">Agents</a>
            <a href="{{ route('home') }}#pricing" style="color:rgba(232,236,242,.7)">Pricing</a>
            <a href="{{ route('home') }}#editions" style="color:rgba(232,236,242,.7)">Cloud vs self-hosted</a>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:14px">
            <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.38);margin-bottom:2px">Open source</div>
            <a href="https://github.com/Dricle/eveil" style="color:rgba(232,236,242,.7)">GitHub</a>
            <a href="https://github.com/Dricle/eveil" style="color:rgba(232,236,242,.7)">Docs</a>
            <a href="https://github.com/Dricle/eveil" style="color:rgba(232,236,242,.7)">Self-host guide</a>
            <a href="https://github.com/Dricle/eveil/blob/main/LICENSE" style="color:rgba(232,236,242,.7)">AGPL-3.0 licence</a>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:14px">
            <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.38);margin-bottom:2px">Legal</div>
            <a href="{{ route('privacy') }}" style="color:rgba(232,236,242,.7)">Privacy</a>
            <a href="{{ route('terms') }}" style="color:rgba(232,236,242,.7)">Terms</a>
            <a href="{{ route('data-retention') }}" style="color:rgba(232,236,242,.7)">Data retention</a>
            <a href="{{ route('contact') }}" style="color:rgba(232,236,242,.7)">Contact</a>
        </div>
    </div>
    <div style="max-width:1180px;margin:0 auto;padding:18px 24px 40px;display:flex;justify-content:space-between;gap:20px;font-size:12.5px;color:rgba(232,236,242,.4);border-top:1px solid rgba(232,236,242,.07)">
        <span>© {{ now()->year }} Eveil. Self-hostable under AGPL-3.0.</span>
        <span>No tracking pixels. No purchased lists. No unsubscribe footer.</span>
    </div>
</footer>
