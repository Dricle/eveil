<x-marketing-layout
    :title="config('app.name') . ' - Blog'"
    description="Notes on organic, automated marketing for founders and small teams: cold email, LinkedIn, Reddit and SEO, from the team building Eveil."
>
    <main class="mx-auto max-w-[820px] px-6 pt-[72px] pb-[88px]">
        <div class="mb-[14px] font-[Geist_Mono,monospace] text-[11.5px] tracking-[.1em] text-[#6fd3ec] uppercase">Blog</div>
        <h1 class="mb-4 font-[Sora,sans-serif] text-[44px] leading-[1.1] font-semibold tracking-[-.035em]">The Eveil blog</h1>
        <p class="mb-12 text-[rgba(232,236,242,.66)]">We write about finding clients without a growth team: cold email that gets answered, LinkedIn and Reddit done without spam, SEO for small products, and what we learn building Eveil in the open.</p>

        @forelse ($articles as $article)
            <a href="{{ route('blog.show', [$article->id, Str::slug($article->title)]) }}" class="block border-t border-[rgba(232,236,242,.08)] py-7">
                <div class="mb-2 font-[Geist_Mono,monospace] text-[11px] tracking-[.08em] text-[rgba(232,236,242,.4)] uppercase">
                    {{ $article->published_at?->format('j F Y') }}
                </div>
                <h2 class="mb-2 font-[Sora,sans-serif] text-[22px] leading-[1.25] font-semibold tracking-[-.02em] text-[#e8ecf2]">{{ $article->title }}</h2>
                @if ($article->meta_description)
                    <p class="text-[15px] text-[rgba(232,236,242,.6)]">{{ $article->meta_description }}</p>
                @endif
            </a>
        @empty
            <p class="text-[rgba(232,236,242,.55)]">Nothing published yet.</p>
        @endforelse
    </main>
</x-marketing-layout>
