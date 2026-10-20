@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'ds-help ds-help--error']) }} style="list-style:none;padding:0;margin-top:6px;">
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
