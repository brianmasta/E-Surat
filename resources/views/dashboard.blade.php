<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ \App\Models\AppSetting::agency()['app_name'] }} | Manajemen Arsip Surat</title>

        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="shortcut icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="48x48" href="/esurat-48x48.png">
        <link rel="apple-touch-icon" sizes="192x192" href="/esurat-192x192.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-slate-100 text-slate-900 antialiased">
        <livewire:dashboard />
        @livewireScripts
    </body>
</html>
