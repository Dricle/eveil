<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}, open-source outreach</title>
        <meta name="description" content="Give a URL and what you sell. {{ config('app.name') }} finds the companies that need it and writes the outreach. Open source, self-hostable.">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="font-sans antialiased">
        <main class="mx-auto max-w-3xl px-6 py-24">
            <h1 class="text-4xl font-semibold tracking-tight">{{ config('app.name') }}</h1>

            <p class="mt-4 text-lg text-neutral-600">
                Give your product URL. Get the companies that need it, the people to write to, and the
                sequence to send them.
            </p>

            <div class="mt-10 flex gap-4">
                <a href="/app" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white">
                    Open the app
                </a>
            </div>
        </main>
    </body>
</html>
