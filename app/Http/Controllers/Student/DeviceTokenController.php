<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\FcmDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
            'platform' => ['required', 'in:web,android,ios'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        FcmDeviceToken::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $data['token'])],
            [
                'user_id' => $request->user()->id,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['success' => true], 201);
        }

        return back()->with('success', 'Push notifications are enabled on this device.');
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
        ]);

        FcmDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->delete();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Push notifications are disabled on this device.');
    }
}
