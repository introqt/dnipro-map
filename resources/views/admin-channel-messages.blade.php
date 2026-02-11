@extends('layouts.base')

@section('title', 'Channel Messages')

@section('content')
<div class="p-4 max-w-6xl mx-auto">
    <h1 class="text-2xl font-semibold mb-4">Channel Messages</h1>

    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    <table class="w-full table-auto border-collapse">
        <thead>
            <tr class="text-left">
                <th class="pr-4">ID</th>
                <th class="pr-4">Channel</th>
                <th class="pr-4">Message ID</th>
                <th class="pr-4">Parsed</th>
                <th class="pr-4">Keywords</th>
                <th class="pr-4">Raw</th>
                <th class="pr-4">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($messages as $m)
            <tr class="border-t">
                <td class="py-2">{{ $m->id }}</td>
                <td>{{ $m->channel_id }}</td>
                <td>{{ $m->message_id }}</td>
                <td>@if($m->parsed_lat && $m->parsed_lon) {{ $m->parsed_lat }}, {{ $m->parsed_lon }} @else - @endif</td>
                <td>{{ is_array($m->keywords) ? implode(', ', $m->keywords) : '' }}</td>
                <td style="max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $m->raw_message }}</td>
                <td>
                    <form action="{{ url('/admin/channel-messages/'.$m->id.'/process') }}" method="POST" style="display:inline">
                        @csrf
                        <button class="btn btn-sm">Mark processed</button>
                    </form>
                    <form action="{{ url('/admin/channel-messages/'.$m->id.'/destroy') }}" method="POST" style="display:inline" onsubmit="return confirm('Delete message?')">
                        @csrf
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">{{ $messages->links() }}</div>
</div>
@endsection
