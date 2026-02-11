<?php

namespace App\Http\Controllers;

use App\Models\ChannelMessage;
use Illuminate\Http\Request;

class AdminChannelMessageController extends Controller
{
    public function index(Request $request)
    {
        $messages = ChannelMessage::orderBy('created_at', 'desc')->paginate(25);

        return view('admin-channel-messages', compact('messages'));
    }

    public function process(Request $request, $id)
    {
        $cm = ChannelMessage::findOrFail($id);
        $cm->processed_at = now();
        $cm->save();

        return redirect()->back()->with('success', 'Message marked processed.');
    }

    public function destroy(Request $request, $id)
    {
        $cm = ChannelMessage::findOrFail($id);
        $cm->delete();

        return redirect()->back()->with('success', 'Message deleted.');
    }
}
