<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#162c46">
        <script>document.documentElement.classList.add('js'); window.esuratInitialLoaderTimeout = window.setTimeout(() => document.getElementById('page-loader')?.setAttribute('hidden', ''), 10000);</script>
        <title>{{ \App\Models\AppSetting::agency()['app_name'] }} | Monitoring Nomor Surat</title>

        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="shortcut icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="48x48" href="/esurat-48x48.png">
        <link rel="apple-touch-icon" sizes="192x192" href="/esurat-192x192.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="app-body bg-slate-100 text-slate-900 antialiased">
        <x-ui-feedback />
        <livewire:number-monitor-page />
        @livewireScripts
    </body>
</html>
