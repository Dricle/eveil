@props(['title', 'description'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <meta property="og:image" content="{{ asset('og.png') }}">

        <link rel="icon" href="/icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite('resources/css/app.css')

        <style>
            body.marketing { margin: 0; background: #0b0e14; color: #e8ecf2; font-size: 15.5px; line-height: 1.7; -webkit-font-smoothing: antialiased; }
            .marketing *, .marketing *::before, .marketing *::after { box-sizing: border-box; }
            .marketing a { color: #6fd3ec; text-decoration: none; text-underline-offset: 3px; }
            .marketing a:hover { color: #a8e6f6; }
            .marketing ::selection { background: rgba(111, 211, 236, .28); }
            .marketing :focus { outline: none; }
            .marketing :focus-visible { outline: 2px solid #6fd3ec; outline-offset: 3px; }
            .marketing summary { list-style: none; cursor: pointer; }
            .marketing summary::-webkit-details-marker { display: none; }
            .marketing summary:hover { color: #6fd3ec; }

            .marketing .cta-btn { background: #6fd3ec; color: #06222c; }
            .marketing .cta-btn:hover { background: #a8e6f6; color: #06222c; }
            .marketing .ghost-btn { color: #e8ecf2; }
            .marketing .ghost-btn:hover { background: rgba(232, 236, 242, .06); color: #e8ecf2; }
            .marketing .field { border: 1px solid rgba(232, 236, 242, .16); background: #0b0e14; color: #e8ecf2; border-radius: 9px; padding: 12px 14px; font-size: 15px; }
            .marketing .field:focus { border-color: #6fd3ec; }

            .marketing .legal-h2 { font-family: 'Sora', sans-serif; font-weight: 600; font-size: 24px; letter-spacing: -.025em; margin: 0 0 12px; }
            .marketing .legal-p { margin: 0 0 32px; color: rgba(232, 236, 242, .68); }

            .marketing .hero-glow { background: radial-gradient(60% 50% at 50% 0%, rgba(111, 211, 236, .16), transparent 70%), radial-gradient(40% 40% at 85% 30%, rgba(111, 140, 236, .08), transparent 70%); }
            .marketing .hero-grid { background-image: radial-gradient(rgba(232, 236, 242, .09) 1px, transparent 1px); background-size: 26px 26px; mask-image: radial-gradient(70% 60% at 50% 30%, #000 30%, transparent 75%); }
            .marketing .lift { transition: transform .25s ease, border-color .25s ease, background-color .25s ease; }
            .marketing .lift:hover { transform: translateY(-3px); border-color: rgba(111, 211, 236, .35); }
            .marketing .mock-line { display: block; height: 7px; border-radius: 4px; background: rgba(232, 236, 242, .12); }

            .js .reveal { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s cubic-bezier(.2, .7, .2, 1); transition-delay: var(--delay, 0s); }
            .js .reveal.is-visible { opacity: 1; transform: none; }

            @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
            @keyframes blink { 50% { opacity: 0; } }
            @keyframes typing { 0%, 10% { width: 0; } 45%, 85% { width: 16ch; } 100% { width: 0; } }
            @keyframes flow { to { stroke-dashoffset: -20; } }
            @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(111, 211, 236, .45); } 100% { box-shadow: 0 0 0 10px rgba(111, 211, 236, 0); } }
            @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
            @keyframes fill { 0% { transform: scaleX(0); } 40%, 100% { transform: scaleX(1); } }
            @keyframes pop { 0%, 30% { opacity: 0; transform: scale(.6); } 45%, 100% { opacity: 1; transform: scale(1); } }

            .marketing .anim-float { animation: float 5s ease-in-out infinite; animation-delay: var(--delay, 0s); }
            .marketing .anim-blink { animation: blink 1s steps(1) infinite; }
            .marketing .anim-typing { overflow: hidden; white-space: nowrap; animation: typing 6s steps(16) infinite; }
            .marketing .anim-flow { stroke-dasharray: 4 6; animation: flow 1.2s linear infinite; }
            .marketing .anim-pulse { animation: pulse-ring 1.8s ease-out infinite; }
            .marketing .anim-shimmer { background: linear-gradient(90deg, rgba(232, 236, 242, .06) 25%, rgba(232, 236, 242, .16) 50%, rgba(232, 236, 242, .06) 75%); background-size: 200% 100%; animation: shimmer 2.4s linear infinite; }
            .marketing .anim-fill { transform-origin: left; animation: fill 4s ease-out infinite; animation-delay: var(--delay, 0s); }
            .marketing .anim-pop { animation: pop 4s ease-out infinite; animation-delay: var(--delay, 0s); }

            @media (prefers-reduced-motion: reduce) {
                .marketing *, .marketing *::before, .marketing *::after { animation: none !important; transition: none !important; }
                .js .reveal { opacity: 1; transform: none; }
                .marketing .anim-typing { width: 16ch; }
            }
        </style>
        <script>document.documentElement.classList.add('js')</script>

        <x-analytics />
    </head>
    <body class="marketing font-sans antialiased">
        @include('marketing.partials.header')

        {{ $slot }}

        @include('marketing.partials.footer')

        <script>
            (() => {
                const elements = document.querySelectorAll('.reveal');
                if (!('IntersectionObserver' in window)) {
                    elements.forEach(element => element.classList.add('is-visible'));
                    return;
                }
                const observer = new IntersectionObserver(entries => entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                }), { rootMargin: '0px 0px -8% 0px' });
                elements.forEach(element => observer.observe(element));
            })();
        </script>
    </body>
</html>
