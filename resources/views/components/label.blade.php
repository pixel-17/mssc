@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-ocean-950/80 dark:text-ocean-50/80']) }}>
    {{ $value ?? $slot }}
</label>
