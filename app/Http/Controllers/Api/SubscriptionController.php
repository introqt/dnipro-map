<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $subscription = $request->user()->subscriptions()->first();

        if (! $subscription) {
            return $this->errorResponse('No subscription found.', 404);
        }

        return $this->successResponse(new SubscriptionResource($subscription));
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $subscription = $request->user()->subscriptions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validated()
        );

        return $this->successResponse(
            new SubscriptionResource($subscription),
            'Subscription saved.',
            201
        );
    }

    public function destroy(Request $request)
    {
        $request->user()->subscriptions()->delete();

        return $this->successResponse(message: 'Subscription removed.');
    }
}
