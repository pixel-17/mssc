@props(['model', 'label', 'ayuda' => null])

<div x-data="{ on: $wire.$entangle('{{ $model }}') }" class="flex items-start justify-between gap-4">
    <div class="min-w-0">
        <p class="text-sm font-medium text-tinta-950 dark:text-white">{{ $label }}</p>
        @if ($ayuda)
            <p class="text-xs text-gray-500 dark:text-tinta-50/60">{{ $ayuda }}</p>
        @endif
    </div>
    <button type="button" role="switch" :aria-checked="on ? 'true' : 'false'" @click="on = ! on"
            :class="on ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-white/20'"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-tinta-500 focus-visible:ring-offset-2">
        <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block size-5 rounded-full bg-white shadow transition-transform"></span>
    </button>
</div>
