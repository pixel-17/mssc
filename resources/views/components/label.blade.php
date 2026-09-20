@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-tinta-950/80 dark:text-tinta-50/80']) }}>
    {{ $value ?? $slot }}
</label>
