<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'IoT Safety Box CMS')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('head')
</head>

<body class="min-h-screen bg-[radial-gradient(circle_at_top,_#f6efe7,_#f2f6f4_40%,_#eef2f8)] text-slate-900">
    <div class="min-h-screen px-6 py-8 md:px-10">
        <header class="mb-8 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-mono uppercase tracking-[0.25em] text-slate-500">IoT Safety Box</p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl">
                    Safety Box Control System
                </h1>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/70 px-4 py-3 shadow-sm">
                <p class="text-xs text-slate-500">Status sistem</p>
                <p class="text-sm font-medium text-emerald-700">Online • CMS & Device API</p>
            </div>
        </header>

        @if (session('status'))
            <div class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <main>
            @yield('content')
        </main>
    </div>

    @yield('scripts')
</body>

</html>
