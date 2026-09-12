<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-ocean']) }}>
    {{ $slot }}
</button>
