@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'ds-auth-status']) }}>
        {{ $status }}
    </div>
@endif
