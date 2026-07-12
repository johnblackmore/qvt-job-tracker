@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-teal-dark']) }}>
        {{ $status }}
    </div>
@endif
