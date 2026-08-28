@props(['heading', 'meta', 'notice'])
<div style="max-width:1180px;margin:0 auto;padding:64px 24px 88px;display:grid;grid-template-columns:220px 1fr;gap:56px">
    <aside style="position:sticky;top:32px;align-self:start;display:flex;flex-direction:column;gap:11px">
        <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.09em;text-transform:uppercase;color:rgba(232,236,242,.38);margin-bottom:4px">On this page</div>
        {{ $toc }}
    </aside>

    <main style="max-width:70ch">
        <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">Legal</div>
        <h1 style="font-family:'Sora',sans-serif;font-weight:600;font-size:44px;line-height:1.1;letter-spacing:-.035em;margin:0 0 16px">{{ $heading }}</h1>
        <p style="margin:0 0 32px;color:rgba(232,236,242,.55);font-size:14px;font-family:'Geist Mono',monospace">{{ $meta }}</p>

        <div style="border:1px solid rgba(111,211,236,.3);background:rgba(111,211,236,.06);border-radius:12px;padding:18px 20px;margin-bottom:40px">
            <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.8)">{{ $notice }}</p>
        </div>

        {{ $slot }}

        <div style="border-top:1px solid rgba(232,236,242,.08);padding-top:24px;display:flex;gap:20px;flex-wrap:wrap;font-size:14.5px">
            {{ $links }}
        </div>
    </main>
</div>
