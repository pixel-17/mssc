@props(['user', 'size' => 'size-6'])

{{-- Foto de perfil o, si no tiene, sus iniciales. Requiere Features::profilePhotos().
     El tamaño va también inline (size-N = N*4 px) para que una foto grande nunca
     se salga de su caja, aunque el CSS compilado no esté actualizado. --}}
@php
    $px = max(16, ((int) preg_replace('/\D/', '', $size)) * 4);
@endphp
@if ($user->profile_photo_path)
    <img src="{{ $user->profile_photo_url }}" alt="{{ $user->nombre_completo }}" loading="lazy"
         width="{{ $px }}" height="{{ $px }}"
         style="width: {{ $px }}px; height: {{ $px }}px; max-width: none;"
         {{ $attributes->class([$size, 'shrink-0 rounded-full object-cover']) }}>
@else
    <span style="width: {{ $px }}px; height: {{ $px }}px;"
          {{ $attributes->class([$size, 'inline-flex shrink-0 items-center justify-center rounded-full bg-tinta-600 text-[10px] font-semibold text-white']) }}
          aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $user->name, 0, 1).mb_substr((string) $user->apellido, 0, 1)) }}</span>
@endif
