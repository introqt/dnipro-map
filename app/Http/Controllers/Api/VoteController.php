<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Point;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    use ApiResponse;

    public function store(Request $request, Point $point)
    {
        $request->validate([
            'type' => ['required', 'in:like,dislike'],
        ]);

        $existingVote = $point->votes()->where('user_id', $request->user()->id)->first();

        if ($existingVote) {
            if ($existingVote->type->value === $request->type) {
                $existingVote->delete();
            } else {
                $existingVote->update(['type' => $request->type]);
            }
        } else {
            $point->votes()->create([
                'user_id' => $request->user()->id,
                'type' => $request->type,
            ]);
        }

        $point->load('votes');

        return $this->successResponse([
            'likes' => $point->votes->where('type.value', 'like')->count(),
            'dislikes' => $point->votes->where('type.value', 'dislike')->count(),
            'user_vote' => $point->votes->where('user_id', $request->user()->id)->first()?->type->value,
        ]);
    }
}
