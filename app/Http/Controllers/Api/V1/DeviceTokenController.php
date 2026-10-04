<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Student\DeviceTokenController as StudentDeviceTokenController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends ApiController
{
    public function store(Request $request, StudentDeviceTokenController $tokens): JsonResponse
    {
        $response = $tokens->store($request);

        return $this->success(null, 'Device token registered', $response->getStatusCode());
    }

    public function destroy(Request $request, StudentDeviceTokenController $tokens): JsonResponse
    {
        $tokens->destroy($request);

        return $this->success(null, 'Device token removed');
    }
}
