@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper rounded-lg text-sm px-3.5 py-2.5']) }}>
