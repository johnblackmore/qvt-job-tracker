<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-lg border-2 border-copper bg-white px-5 py-2.5 text-sm font-display font-semibold text-copper hover:bg-copper hover:text-white focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors']) }}>
    {{ $slot }}
</button>
