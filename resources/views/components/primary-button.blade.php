<button {{ $attributes->merge(['type' => 'submit', 'class' => 'w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors']) }}>
    {{ $slot }}
</button>
