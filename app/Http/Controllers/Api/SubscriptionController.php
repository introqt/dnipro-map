<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;

class SubscriptionController extends Controller
{
    use ApiResponse;

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
}
