@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-xl border-ocean-200 bg-white/70 backdrop-blur focus:border-ocean-500 focus:ring-ocean-500 shadow-sm placeholder:text-gray-400 transition']) !!}>
