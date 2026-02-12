<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Point;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use ApiResponse;

    public function index(Point $point)
    {
        $comments = $point->comments()->with('user')->latest()->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'text' => $c->text,
                'created_at' => $c->created_at->toIso8601String(),
                'user' => $c->user ? ['first_name' => $c->user->first_name] : null,
            ];
        });

        return $this->successResponse($comments);
    }

    public function store(Request $request, Point $point)
    {
        $request->validate(['text' => ['required', 'string', 'max:1000']]);

        $user = $request->user();

        $comment = $point->comments()->create([
            'text' => $request->input('text'),
            'user_id' => $user ? $user->id : null,
        ]);

        return $this->successResponse([
            'id' => $comment->id,
            'text' => $comment->text,
            'created_at' => $comment->created_at->toIso8601String(),
            'user' => $user ? ['first_name' => $user->first_name] : null,
        ], 'Comment added.', 201);
    }
}
