@php
    use Illuminate\Support\Js;

    $config = $getMapData();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        x-data="leafletMapField(
            $wire,
            {{ Js::from($config) }},
        )"
        x-init="$nextTick(() => {
            const rawState = config.state ? $wire.get(config.state.statePath) : null;
            if (!rawState && pickMarker) {
                Alpine.raw(pickMarker).removeFrom(Alpine.raw(mapCore.map));
                pickMarker = null;
            }
        })"
        style="height: {{ $config['mapHeight'] }}px; width: 100%"
    >
        <div id="{{ $config['mapId'] }}"></div>

        @push('styles')
            <style>
                {!! $config['customStyles'] !!}
            </style>
        @endpush

        @push('scripts')
            <script>
                {!! $config['customScripts'] !!}
            </script>
        @endpush
    </div>
</x-dynamic-component>
