<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class FirebaseMessagingServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $configuration = config('services.firebase.web');
        $requiredKeys = ['api_key', 'auth_domain', 'project_id', 'messaging_sender_id', 'app_id', 'vapid_key'];
        foreach ($requiredKeys as $key) {
            if (blank($configuration[$key] ?? null)) {
                abort(503, 'Firebase web messaging is not configured.');
            }
        }

        $firebaseConfig = [
            'apiKey' => $configuration['api_key'],
            'authDomain' => $configuration['auth_domain'],
            'projectId' => $configuration['project_id'],
            'messagingSenderId' => $configuration['messaging_sender_id'],
            'appId' => $configuration['app_id'],
        ];

        $script = implode("\n", [
            "importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');",
            "importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');",
            'firebase.initializeApp('.json_encode($firebaseConfig, JSON_THROW_ON_ERROR).');',
            'firebase.messaging();',
        ]);

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
