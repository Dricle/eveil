<x-marketing-layout
    :title="$article->title . ' - ' . config('app.name')"
    :description="$article->meta_description ?? $article->title"
    :social-title="$article->title"
    :canonical="$canonical"
    type="article"
>
    {{-- Assembled in the controller, not here: schema.org's keys start with an
         at-sign, and Blade compiles one of them as a directive wherever it
         appears, inside a string literal included. --}}
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>

    <main class="mx-auto max-w-[760px] px-6 pt-[72px] pb-[88px]">
        <a href="{{ route('blog.index') }}" class="mb-8 inline-block font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] uppercase">← Blog</a>

        <div class="mb-[14px] font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] text-[rgba(232,236,242,.4)] uppercase">
            {{ $article->published_at?->format('j F Y') }}
        </div>
        <h1 class="mb-10 font-[Sora,sans-serif] text-[40px] leading-[1.12] font-semibold tracking-[-.035em]">{{ $article->title }}</h1>

        {{-- Written by our own agent, but rendered with raw HTML stripped all
             the same: the body is Markdown, and Markdown is all it needs. --}}
        <article class="prose prose-invert max-w-none prose-headings:font-[Sora,sans-serif] prose-headings:tracking-[-.02em] prose-a:text-[#6fd3ec]">
            {!! Str::markdown($article->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </article>

        <div class="mt-16 rounded-xl border border-[rgba(111,211,236,.3)] bg-[rgba(111,211,236,.06)] px-6 py-5">
            <p class="mb-3 text-[15px] text-[rgba(232,236,242,.8)]">This article was drafted by Eveil's SEO agent, from something it found, then reviewed before publishing. Eveil does the same for your product.</p>
            <a href="{{ route('home') }}" class="cta-btn inline-block rounded-lg px-[18px] py-[9px] font-[Sora,sans-serif] text-[14px] font-semibold">See how it works</a>
        </div>
    </main>
</x-marketing-layout>
