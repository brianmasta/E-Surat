@props(['label', 'icon' => 'circle', 'action', 'active' => false, 'badge' => 0])

<button type="button" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}"
        class="sidebar-link {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
    <span class="sidebar-link-label">{{ $label }}</span>
    @if ($badge > 0)<span class="sidebar-count">{{ $badge }}</span>@endif
    <span wire:loading.inline-block wire:target="{{ $action }}" class="spinner-border spinner-border-sm sidebar-spinner" aria-hidden="true"></span>
</button>
