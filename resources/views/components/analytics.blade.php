{{--
    One tag, both front ends: the Blade marketing site and the Inertia
    application shell. It used to live inline in the marketing layout only,
    which is the whole reason nothing past the landing page was ever measured -
    the application had no tag at all, so a visitor who signed up vanished from
    the funnel at exactly the moment they became interesting.

    Cloud only. A self-hosted instance renders nothing: no script, no request,
    no beacon. See `config/eveil.php`'s analytics block for why that gate is
    the edition and not an env flag.
--}}
@if (config('eveil.edition') === 'cloud' && filled(config('eveil.analytics.site_id')))
    <script
        src="{{ rtrim(config('eveil.analytics.host'), '/') }}/api/script.js"
        data-site-id="{{ config('eveil.analytics.site_id') }}"
        defer
    ></script>
@endif
