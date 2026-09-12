@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-xl border-ocean-200 dark:border-white/15 bg-white/70 dark:bg-white/10 dark:text-white backdrop-blur focus:border-ocean-500 focus:ring-ocean-500 shadow-sm placeholder:text-gray-400 dark:placeholder:text-white/40 transition']) !!}>
