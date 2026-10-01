<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    /** Return the authenticated student's existing database notifications. */
    public function index(Request $request): JsonResponse
    {
        return $this->success(
            $request->user()->notifications()->latest()->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ])->values()
        );
    }
}
