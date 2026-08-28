@props(['title', 'description'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
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
        </style>
    </head>
    <body class="marketing font-sans antialiased">
        @include('marketing.partials.header')

        {{ $slot }}

        @include('marketing.partials.footer')
    </body>
</html>
