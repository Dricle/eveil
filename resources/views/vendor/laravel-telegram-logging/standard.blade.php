<b>{{ $appName }}</b> ({{ $level_name }})
Env: {{ $appEnv }}
[{{ $datetime->format('Y-m-d H:i:s') }}] {{ $appEnv }}.{{ $level_name }} {{ $formatted }}
Url: {{ request()->url() }}
IP: {{ request()->ip() }}
User Agent: {{ request()->userAgent() }}
User: {{ auth()->user()->name ?? 'Guest' }}
Payload: {{ json_encode(request()->except(['password', 'password_confirmation'])) }}
