{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
{{-- Split so the template never contains a literal XML declaration, which a
     server with short_open_tag on would read as an opening PHP tag. The
     declaration also has to be the very first thing in the document, so
     nothing may be echoed above this line. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if ($url['lastmod'])
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
