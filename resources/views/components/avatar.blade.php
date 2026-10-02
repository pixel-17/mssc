@props(['user', 'size' => 'size-6'])

{{-- Foto de perfil o, si no tiene, sus iniciales. Requiere Features::profilePhotos(). --}}
@if ($user->profile_photo_path)
    <img src="{{ $user->profile_photo_url }}" alt="{{ $user->nombre_completo }}" loading="lazy"
         {{ $attributes->class([$size, 'shrink-0 rounded-full object-cover']) }}>
@else
    <span {{ $attributes->class([$size, 'inline-flex shrink-0 items-center justify-center rounded-full bg-tinta-600 text-[10px] font-semibold text-white']) }}
          aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $user->name, 0, 1).mb_substr((string) $user->apellido, 0, 1)) }}</span>
@endif
