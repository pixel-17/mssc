@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-xl border-tinta-200 dark:border-white/15 bg-white/70 dark:bg-white/10 dark:text-white backdrop-blur focus:border-tinta-500 focus:ring-tinta-500 shadow-sm placeholder:text-gray-400 dark:placeholder:text-white/40 transition']) !!}>
