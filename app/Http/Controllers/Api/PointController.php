<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointStatus;
use App\Events\PointCreated;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePointRequest;
use App\Http\Requests\UpdatePointRequest;
use App\Http\Resources\PointResource;
use App\Models\Point;

class PointController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $points = Point::with(['user', 'votes'])
            ->where('status', PointStatus::Active)
            ->latest()
            ->get();

        return $this->successResponse(PointResource::collection($points));
    }

    public function store(StorePointRequest $request)
    {
        if (! $request->user()->isAdmin()) {
            return $this->errorResponse('Forbidden.', 403);
        }

        $point = $request->user()->points()->create($request->validated());

        PointCreated::dispatch($point);

        return $this->successResponse(
            new PointResource($point->load('user')),
            'Point created.',
            201
        );
    }

    public function update(UpdatePointRequest $request, Point $point)
    {
        if (! $request->user()->isAdmin()) {
            return $this->errorResponse('Forbidden.', 403);
        }

        $point->update($request->validated());

        return $this->successResponse(
            new PointResource($point->fresh()->load('user')),
            'Point updated.'
        );
    }

    public function destroy(Point $point)
    {
        if (! auth()->user()->isAdmin()) {
            return $this->errorResponse('Forbidden.', 403);
        }

        $point->delete();

        return $this->successResponse(message: 'Point deleted.');
    }
}
