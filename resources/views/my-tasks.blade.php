<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ \App\Models\AppSetting::agency()['app_name'] }} | Tugas Saya</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-slate-100 text-slate-900 antialiased">
        <livewire:my-tasks-page />
        @livewireScripts
    </body>
</html>
