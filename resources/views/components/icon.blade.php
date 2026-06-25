@props([
    'name' => 'circle',
    'class' => 'h-4 w-4',
    'strokeWidth' => 2,
])

@php
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"></path><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"></path>',
        'outbox' => '<path d="M12 10V3"></path><path d="m8 7 4-4 4 4"></path><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H16"></path><path d="M8 4h-.76a2 2 0 0 0-1.79 1.11"></path><path d="M22 12h-6l-2 3h-4l-2-3H2"></path>',
        'task' => '<path d="M9 11 12 14 22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
        'gavel' => '<path d="m14 13-7.5 7.5a2.12 2.12 0 0 1-3-3L11 10"></path><path d="m16 16 6-6"></path><path d="m8 8 6-6 4 4-6 6Z"></path><path d="m12 12 4 4"></path>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z"></path><circle cx="12" cy="12" r="3"></circle>',
        'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path>',
        'route' => '<circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7H6.5a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 3v6h6"></path><path d="M12 7v5l3 2"></path>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
        'user' => '<path d="M19 21a7 7 0 0 0-14 0"></path><circle cx="12" cy="7" r="4"></circle>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'send' => '<path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path>',
        'scanner' => '<path d="M7 3H5a2 2 0 0 0-2 2v2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><path d="M7 8h10"></path><path d="M7 12h10"></path><path d="M7 16h6"></path>',
        'paperclip' => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path>',
        'pdf' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M8 13h1.5a1.5 1.5 0 0 1 0 3H8v-3Z"></path><path d="M13 16v-3h1a1.5 1.5 0 0 1 0 3h-1Z"></path><path d="M18 13h-2v3"></path><path d="M16 15h1.5"></path>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M17 8l-5-5-5 5"></path><path d="M12 3v12"></path>',
        'plus' => '<path d="M5 12h14"></path><path d="M12 5v14"></path>',
        'edit' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>',
        'trash' => '<path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>',
        'tag' => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"></path><path d="M7 7h.01"></path>',
        'pin' => '<path d="M12 17v5"></path><path d="M9 10.76 4.28 6.05a2 2 0 0 1 0-2.83l.94-.94a2 2 0 0 1 2.83 0L12.76 7"></path><path d="m14 12 7.54-7.54"></path><path d="m8 16 8-8"></path><path d="m3 21 6-6"></path>',
        'calendar' => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path>',
        'list-numbered' => '<path d="M10 6h11"></path><path d="M10 12h11"></path><path d="M10 18h11"></path><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path>',
        'check' => '<path d="M20 6 9 17l-5-5"></path>',
        'sync' => '<path d="M21 12a9 9 0 0 0-15-6.7L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 15 6.7L21 16"></path><path d="M16 16h5v5"></path>',
        'cancel' => '<circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>',
        'bookmark' => '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2Z"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
        'circle' => '<circle cx="12" cy="12" r="10"></circle>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $paths[$name] ?? $paths['circle'] !!}
</svg>
