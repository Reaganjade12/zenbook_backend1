<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'profile_image' => $user->profile_image,
                'profile_image_url' => $user->profile_image_url,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:500'],
            'profile_image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('profile_image')) {
            try {
                $image = $request->file('profile_image');
                
                \Log::info('[API ProfileController] Image upload started', [
                    'user_id' => $user->id,
                    'file_name' => $image->getClientOriginalName(),
                    'file_size' => $image->getSize(),
                    'mime_type' => $image->getMimeType(),
                ]);

                // Delete old image if exists
                if ($user->profile_image) {
                    $oldPath = $user->profile_image;
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                        \Log::info('[API ProfileController] Deleted old image', ['path' => $oldPath]);
                    }
                }

                // Store new image in profiles directory
                $storedPath = $request->file('profile_image')->store('profiles', 'public');
                
                \Log::info('[API ProfileController] Image stored', [
                    'stored_path' => $storedPath,
                    'full_path' => Storage::disk('public')->path($storedPath),
                    'file_exists' => Storage::disk('public')->exists($storedPath),
                    'file_size' => Storage::disk('public')->exists($storedPath) ? Storage::disk('public')->size($storedPath) : 0,
                ]);

                // Verify file was actually saved
                if (!Storage::disk('public')->exists($storedPath)) {
                    \Log::error('[API ProfileController] File does not exist after storage!', [
                        'stored_path' => $storedPath,
                        'full_path' => Storage::disk('public')->path($storedPath),
                    ]);
                    throw new \Exception('Image file was not saved successfully');
                }

                $validated['profile_image'] = $storedPath;
                
                // Get the URL that will be generated
                $imageUrl = Storage::disk('public')->url($storedPath);
                \Log::info('[API ProfileController] Image URL generated', [
                    'url' => $imageUrl,
                    'stored_path' => $storedPath,
                ]);
                
            } catch (\Exception $e) {
                \Log::error('[API ProfileController] Image upload failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'message' => 'Failed to upload image: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        $user->update($validated);
        
        // Refresh user model to get updated profile_image_url
        $user->refresh();

        // Log the final state
        \Log::info('[API ProfileController] Profile updated', [
            'user_id' => $user->id,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $user->profile_image_url,
            'file_exists' => $user->profile_image ? Storage::disk('public')->exists($user->profile_image) : false,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'profile_image' => $user->profile_image,
                'profile_image_url' => $user->profile_image_url,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ],
            'debug' => [
                'file_exists' => $user->profile_image ? Storage::disk('public')->exists($user->profile_image) : false,
                'storage_path' => $user->profile_image ? Storage::disk('public')->path($user->profile_image) : null,
                'public_url' => $user->profile_image ? Storage::disk('public')->url($user->profile_image) : null,
            ],
        ]);
    }

    /**
     * Check storage configuration
     */
    public function checkStorage(Request $request)
    {
        $user = $request->user();
        $storagePath = storage_path('app/public');
        $profilesPath = storage_path('app/public/profiles');
        $publicStoragePath = public_path('storage');
        
        $checks = [
            'storage_path_exists' => file_exists($storagePath),
            'storage_path' => $storagePath,
            'profiles_dir_exists' => file_exists($profilesPath),
            'profiles_dir_path' => $profilesPath,
            'public_storage_link_exists' => file_exists($publicStoragePath),
            'public_storage_is_link' => is_link($publicStoragePath),
            'public_storage_path' => $publicStoragePath,
            'storage_writable' => is_writable($storagePath),
            'profiles_dir_writable' => is_writable($profilesPath),
        ];

        if ($user && $user->profile_image) {
            $imagePath = Storage::disk('public')->path($user->profile_image);
            $checks['user_image_exists'] = Storage::disk('public')->exists($user->profile_image);
            $checks['user_image_path'] = $imagePath;
            $checks['user_image_url'] = Storage::disk('public')->url($user->profile_image);
            $checks['user_image_file_exists'] = file_exists($imagePath);
            $checks['user_image_readable'] = file_exists($imagePath) ? is_readable($imagePath) : false;
            $checks['user_image_permissions'] = file_exists($imagePath) ? substr(sprintf('%o', fileperms($imagePath)), -4) : null;
            $checks['user_image_size'] = file_exists($imagePath) ? filesize($imagePath) : null;
        }

        // Check if we can list files in profiles directory
        if (is_dir($profilesPath) && is_readable($profilesPath)) {
            $files = scandir($profilesPath);
            $checks['profiles_dir_file_count'] = count(array_filter($files, function($file) use ($profilesPath) {
                return $file !== '.' && $file !== '..' && is_file($profilesPath . '/' . $file);
            }));
        }

        return response()->json([
            'checks' => $checks,
            'message' => 'Storage configuration check',
            'note' => 'Storage files are served via Laravel route /storage/{path} - symlink not required',
        ]);
    }

    /**
     * Delete profile image
     */
    public function deleteImage(Request $request)
    {
        $user = $request->user();

        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
            $user->update(['profile_image' => null]);
        }
        
        // Refresh user model to get updated profile_image_url
        $user->refresh();

        return response()->json([
            'message' => 'Profile image deleted successfully!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'profile_image' => $user->profile_image,
                'profile_image_url' => $user->profile_image_url,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }
}

