<?php

namespace App\Http\Controllers;

use App\Services\SupabaseStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProfilePhotoController extends Controller
{
    public function show(Request $request, SupabaseStorage $storage): Response
    {
        $user = $request->user();
        abort_unless($user->profile_image, 404);

        if ($user->profile_image_storage_disk === 'supabase') {
            $content = $storage->download('profiles', $user->profile_image);
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';

            return response($content)->header('Content-Type', $mimeType)->header('Cache-Control', 'private, max-age=300');
        }

        $disk = Storage::disk('public');
        abort_unless($disk->exists($user->profile_image), 404);

        return response($disk->get($user->profile_image))
            ->header('Content-Type', $disk->mimeType($user->profile_image) ?: 'application/octet-stream')
            ->header('Cache-Control', 'private, max-age=300');
    }
}
