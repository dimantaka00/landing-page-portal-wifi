@props(['name' => 'bell'])
<svg {{ $attributes->merge(['class' => 'icon']) }} width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('maintenance')<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z"/><path d="m5 17 2 2"/>@break
@case('wifi')<path d="M3 8a15 15 0 0 1 18 0M6 12a10 10 0 0 1 12 0M9 16a5 5 0 0 1 6 0"/><circle cx="12" cy="20" r=".8"/>@break
@case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>@break
@case('pin')<path d="m16 3 5 5-5 1-3 5v3l-3-3-5-1 5-3 1-5zM8 16l-5 5"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('edit')<path d="m16 3 5 5-12 12-6 1 1-6zM14 5l5 5"/>@break
@case('trash')<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>@break
@case('image')<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><path d="m3 16 5-5 4 4 3-3 6 6"/>@break
@case('shield')<path d="M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7zM8 12l3 3 5-6"/>@break
@case('logout')<path d="M9 4H4v16h5M9 12h12M17 8l4 4-4 4"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@case('file')<path d="M14 3H5v18h14V8zM14 3v5h5M8 12h8M8 16h6"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('building')<path d="M3 21h18M5 21V7h14v14M3 7l9-4 9 4M9 11v6M15 11v6"/>@break
@case('lock')<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>@break
@case('eye')<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12"/><circle cx="12" cy="12" r="3"/>@break
@default<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
@endswitch
</svg>
