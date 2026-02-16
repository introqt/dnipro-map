<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    use ApiResponse;

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:51200'], // 50MB
        ]);

        $path = $request->file('file')->store('uploads', 'public');

        return $this->successResponse(
            ['url' => asset('storage/' . $path)],
            'File uploaded.',
            201
        );
    }
}
