<?php

namespace App\Http\Controllers\Teacher;

use App\Exceptions\SupabaseStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\SupabaseStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Teacher/Profile/Edit', [
            'user' => $request->user()->only([
                'id', 'first_name', 'middle_name', 'last_name', 'email',
                'username', 'employee_number', 'gender', 'profile_image', 'profile_image_file_size',
            ]) + [
                'profile_image_url' => $request->user()->profile_image ? route('profile.photo') : null,
            ],
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated.');
    }

    public function updateAvatar(Request $request, SupabaseStorage $storage): RedirectResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        try {
            $uploaded = $storage->upload('profiles', $request->file('avatar'));
        } catch (SupabaseStorageException $exception) {
            return back()->withErrors(['avatar' => $exception->getMessage()]);
        }

        $oldPath = $user->profile_image;
        $oldDisk = $user->profile_image_storage_disk;
        $oldSize = $user->profile_image_file_size;
        try {
            $user->update([
                'profile_image' => $uploaded['path'],
                'profile_image_storage_disk' => 'supabase',
                'profile_image_file_size' => $uploaded['size'],
            ]);
        } catch (\Throwable $exception) {
            try {
                $storage->delete('profiles', $uploaded['path']);
            } catch (SupabaseStorageException $cleanupException) {
                Log::error('Unable to clean up a profile image after a database failure.', [
                    'user_id' => $user->id,
                    'exception' => $cleanupException,
                ]);
            }

            throw $exception;
        }

        try {
            $this->deleteProfileImage($user, $storage, $oldPath, $oldDisk);
        } catch (SupabaseStorageException $exception) {
            $user->update([
                'profile_image' => $oldPath,
                'profile_image_storage_disk' => $oldDisk,
                'profile_image_file_size' => $oldSize,
            ]);

            try {
                $storage->delete('profiles', $uploaded['path']);
            } catch (SupabaseStorageException $cleanupException) {
                Log::error('Unable to clean up a failed profile image replacement.', [
                    'user_id' => $user->id,
                    'exception' => $cleanupException,
                ]);
            }

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Profile photo updated.');
    }

    public function deleteAvatar(Request $request, SupabaseStorage $storage): RedirectResponse
    {
        $user = $request->user();
        $oldPath = $user->profile_image;
        $oldDisk = $user->profile_image_storage_disk;
        $oldSize = $user->profile_image_file_size;
        abort_unless($oldPath, 404);
        $user->update([
            'profile_image' => null,
            'profile_image_storage_disk' => null,
            'profile_image_file_size' => null,
        ]);

        try {
            $this->deleteProfileImage($user, $storage, $oldPath, $oldDisk);
        } catch (SupabaseStorageException $exception) {
            $user->update([
                'profile_image' => $oldPath,
                'profile_image_storage_disk' => $oldDisk,
                'profile_image_file_size' => $oldSize,
            ]);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Profile photo deleted.');
    }

    private function deleteProfileImage(
        User $user,
        SupabaseStorage $storage,
        ?string $path,
        ?string $disk,
    ): void {
        if (! $path) {
            return;
        }

        if ($disk === 'supabase') {
            $storage->delete('profiles', $path);
        } else {
            Storage::disk('public')->delete($path);
        }
    }
}
