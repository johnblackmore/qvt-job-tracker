@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-700 mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
