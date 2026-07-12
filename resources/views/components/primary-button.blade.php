<button {{ $attributes->merge(['type' => 'submit', 'class' => 'w-full flex items-center justify-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-display font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors']) }}>
    {{ $slot }}
</button>
