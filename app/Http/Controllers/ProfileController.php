<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Show profile page
     */
    public function show()
    {
        $user = Auth::user();
        
        // Log current profile image status for debugging
        Log::info('Profile Show', [
            'user_id' => $user->id,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $user->profile_image ? Storage::disk('public')->url($user->profile_image) : 'No image',
            'image_exists' => $user->profile_image ? Storage::disk('public')->exists($user->profile_image) : false,
        ]);
        
        return view('profile.show', compact('user'));
    }

    /**
     * Update profile
     */
    public function update(Request $request)
    {
        try {
            $user = Auth::user();
            
            Log::info('Profile Update Started', [
                'user_id' => $user->id,
                'has_file' => $request->hasFile('profile_image'),
                'file_size' => $request->hasFile('profile_image') ? $request->file('profile_image')->getSize() : null,
            ]);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'phone' => ['nullable', 'string', 'max:20'],
                'bio' => ['nullable', 'string', 'max:1000'],
                'address' => ['nullable', 'string', 'max:500'],
                'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            ]);

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                try {
                    $image = $request->file('profile_image');
                    
                    Log::info('Image Upload Details', [
                        'original_name' => $image->getClientOriginalName(),
                        'mime_type' => $image->getMimeType(),
                        'size' => $image->getSize(),
                        'extension' => $image->getClientOriginalExtension(),
                    ]);

                    // Validate file
                    if (!$image->isValid()) {
                        throw new \Exception('Invalid image file uploaded');
                    }

                    // Ensure profiles directory exists
                    $profilesDir = storage_path('app/public/profiles');
                    if (!file_exists($profilesDir)) {
                        mkdir($profilesDir, 0755, true);
                        Log::info('Created profiles directory', ['path' => $profilesDir]);
                    }
                    
                    // Check directory is writable
                    if (!is_writable($profilesDir)) {
                        throw new \Exception('Profiles directory is not writable. Path: ' . $profilesDir);
                    }

                    // Delete old image if exists
                    if ($user->profile_image) {
                        $oldImagePath = $user->profile_image;
                        if (Storage::disk('public')->exists($oldImagePath)) {
                            Storage::disk('public')->delete($oldImagePath);
                            Log::info('Deleted old image', ['path' => $oldImagePath]);
                        } else {
                            Log::warning('Old image not found for deletion', ['path' => $oldImagePath]);
                        }
                    }

                    // Store new image using Storage facade - more reliable
                    $fileName = Str::uuid() . '.' . $image->getClientOriginalExtension();
                    $imageName = 'profiles/' . $fileName;
                    
                    // Use Storage facade putFileAs - returns path relative to disk root
                    // putFileAs('profiles', $image, $fileName) stores at 'profiles/filename.ext'
                    $storedPath = Storage::disk('public')->putFileAs('profiles', $image, $fileName);
                    
                    Log::info('Image Storage Attempt', [
                        'file_name' => $fileName,
                        'image_name' => $imageName,
                        'stored_path' => $storedPath,
                        'stored_path_exists' => Storage::disk('public')->exists($storedPath),
                    ]);
                    
                    // putFileAs returns 'profiles/filename.ext' - verify it exists
                    if (!Storage::disk('public')->exists($storedPath)) {
                        // Check direct file system as fallback
                        $directPath = storage_path('app/public/profiles/' . $fileName);
                        
                        if (file_exists($directPath)) {
                            // File exists but Storage can't see it - use the path we know works
                            $storedPath = $imageName; // 'profiles/filename.ext'
                            Log::info('Found file via direct check, using known path', [
                                'direct_path' => $directPath,
                                'using_path' => $storedPath,
                            ]);
                        } else {
                            Log::error('Image file not found after storage', [
                                'stored_path' => $storedPath,
                                'image_name' => $imageName,
                                'direct_path' => $directPath,
                                'stored_exists' => Storage::disk('public')->exists($storedPath),
                                'direct_exists' => file_exists($directPath),
                                'profiles_dir' => $profilesDir,
                                'profiles_dir_exists' => is_dir($profilesDir),
                                'profiles_dir_writable' => is_writable($profilesDir),
                            ]);
                            
                            throw new \Exception('Image file was not saved successfully. Directory writable: ' . (is_writable($profilesDir) ? 'Yes' : 'No') . ' | Check logs for details.');
                        }
                    }

                    // Verify file is readable
                    $fullPath = Storage::disk('public')->path($storedPath);
                    if (!file_exists($fullPath)) {
                        // Try direct path
                        $fullPath = storage_path('app/public/profiles/' . $fileName);
                        $storedPath = $imageName;
                    }
                    
                    if (!is_readable($fullPath)) {
                        throw new \Exception('Image file is not readable. Check permissions. Path: ' . $fullPath);
                    }
                    
                    Log::info('Image upload successful', [
                        'stored_path' => $storedPath,
                        'full_path' => $fullPath,
                        'file_size' => filesize($fullPath),
                        'url' => Storage::disk('public')->url($storedPath),
                    ]);

                    $validated['profile_image'] = $storedPath;
                    
                } catch (\Exception $e) {
                    Log::error('Image Upload Error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    return redirect()->route('profile.show')
                        ->with('error', 'Failed to upload image: ' . $e->getMessage())
                        ->withInput();
                }
            } else {
                // Don't update profile_image if no new image is uploaded
                unset($validated['profile_image']);
                Log::info('No new image uploaded, keeping existing');
            }

            // Update user
            $user->update($validated);
            
            Log::info('User Updated', [
                'user_id' => $user->id,
                'profile_image' => $user->profile_image,
            ]);

            // Refresh the user model to get the latest data
            $user->refresh();
            
            // Reload the authenticated user in the session so layouts show updated image
            Auth::setUser($user);
            
            Log::info('Profile update completed successfully', [
                'user_id' => $user->id,
                'final_profile_image' => $user->profile_image,
                'final_image_url' => $user->profile_image ? Storage::disk('public')->url($user->profile_image) : 'No image',
            ]);

            return redirect()->route('profile.show')
                ->with('success', 'Profile updated successfully!');
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error', [
                'errors' => $e->errors(),
            ]);
            return redirect()->route('profile.show')
                ->withErrors($e->errors())
                ->withInput();
                
        } catch (\Exception $e) {
            Log::error('Profile Update Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('profile.show')
                ->with('error', 'An error occurred: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Delete profile image
     */
    public function deleteImage(Request $request)
    {
        try {
            $user = Auth::user();
            
            Log::info('Delete Image Started', [
                'user_id' => $user->id,
                'current_image' => $user->profile_image,
            ]);

            if ($user->profile_image) {
                $imagePath = $user->profile_image;
                
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                    Log::info('Image file deleted', ['path' => $imagePath]);
                } else {
                    Log::warning('Image file not found for deletion', ['path' => $imagePath]);
                }
            }

            $user->update(['profile_image' => null]);
            
            // Reload the authenticated user in the session
            $user->refresh();
            Auth::setUser($user);
            
            Log::info('Image deleted successfully', ['user_id' => $user->id]);

            return redirect()->route('profile.show')
                ->with('success', 'Profile image deleted successfully!');
                
        } catch (\Exception $e) {
            Log::error('Delete Image Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('profile.show')
                ->with('error', 'Failed to delete image: ' . $e->getMessage());
        }
    }
}
