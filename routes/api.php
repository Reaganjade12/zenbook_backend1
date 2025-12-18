<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TherapistController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
Route::post('/resend-otp', [AuthController::class, 'resendOTP']);

// Password Reset Routes (Public)
// Handle CORS preflight for password reset routes
Route::options('/forgot-password', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization')
        ->header('Access-Control-Max-Age', '3600');
});

Route::options('/reset-password', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization')
        ->header('Access-Control-Max-Age', '3600');
});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Handle CORS preflight for storage files
Route::options('/storage/{path}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization')
        ->header('Access-Control-Max-Age', '3600');
})->where('path', '.*');

// Public image serving route (bypasses Apache direct file serving)
Route::get('/storage/{path}', function ($path) {
    \Log::info('API Storage route accessed', [
        'requested_path' => $path,
        'full_url' => request()->fullUrl(),
        'method' => request()->method(),
    ]);
    
    // Security: prevent directory traversal
    $path = str_replace('..', '', $path);
    $path = ltrim($path, '/');
    
    // Try storage/app/public first (where Laravel stores files)
    $filePath = storage_path('app/public/' . $path);
    
    // Fallback: also check public/storage (in case of symlink or direct copy)
    $publicStoragePath = public_path('storage/' . $path);
    
    \Log::info('API Storage route processing', [
        'sanitized_path' => $path,
        'file_path' => $filePath,
        'public_storage_path' => $publicStoragePath,
        'file_exists_storage' => file_exists($filePath),
        'file_exists_public' => file_exists($publicStoragePath),
    ]);
    
    // Determine which file to serve
    $actualFilePath = null;
    if (file_exists($filePath) && is_file($filePath)) {
        $actualFilePath = $filePath;
    } elseif (file_exists($publicStoragePath) && is_file($publicStoragePath)) {
        $actualFilePath = $publicStoragePath;
    } else {
        \Log::warning('API Storage file not found', [
            'requested_path' => $path,
            'storage_path' => $filePath,
            'public_storage_path' => $publicStoragePath,
        ]);
        abort(404, 'File not found');
    }
    
    // Security check: ensure file is within allowed directory
    $realPath = realpath($actualFilePath);
    $allowedStoragePath = realpath(storage_path('app/public'));
    $allowedPublicPath = realpath(public_path('storage'));
    
    $isAllowed = false;
    if ($allowedStoragePath && $realPath && strpos($realPath, $allowedStoragePath) === 0) {
        $isAllowed = true;
    } elseif ($allowedPublicPath && $realPath && strpos($realPath, $allowedPublicPath) === 0) {
        $isAllowed = true;
    }
    
    if (!$isAllowed || !is_readable($actualFilePath)) {
        \Log::error('API Storage file access denied', [
            'requested_path' => $path,
            'file_path' => $actualFilePath,
            'is_allowed' => $isAllowed,
            'is_readable' => is_readable($actualFilePath),
        ]);
        abort(403, 'File access denied');
    }
    
    try {
        $file = file_get_contents($actualFilePath);
        $mimeType = mime_content_type($actualFilePath) ?: 'application/octet-stream';
        
        \Log::info('API Storage file served successfully', [
            'requested_path' => $path,
            'file_path' => $actualFilePath,
            'file_size' => strlen($file),
            'mime_type' => $mimeType,
        ]);
        
        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization')
            ->header('Cache-Control', 'public, max-age=31536000')
            ->header('Content-Length', strlen($file))
            ->header('X-Served-By', 'Laravel-API-Route');
    } catch (\Exception $e) {
        \Log::error('API Storage error serving file', [
            'requested_path' => $path,
            'file_path' => $actualFilePath,
            'error' => $e->getMessage(),
        ]);
        abort(500, 'Error serving file');
    }
})->where('path', '.*');

// Protected routes (require Bearer token)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile/image', [ProfileController::class, 'deleteImage']);
    Route::get('/profile/storage-check', [ProfileController::class, 'checkStorage']);

    // Customer routes
    Route::middleware('role:customer')->prefix('customer')->name('customer.')->group(function () {
        Route::get('/dashboard', [BookingController::class, 'dashboard']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/available-therapists', [BookingController::class, 'getAvailableTherapists']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::put('/bookings/{id}', [BookingController::class, 'update']);
        Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    });

    // Therapist routes
    Route::middleware('role:cleaner,therapist')->prefix('therapist')->name('therapist.')->group(function () {
        Route::get('/dashboard', [TherapistController::class, 'dashboard']);
        Route::get('/bookings', [TherapistController::class, 'bookings']);
        Route::get('/customers', [TherapistController::class, 'customers']);
        Route::post('/bookings/{id}/accept', [TherapistController::class, 'acceptBooking']);
        Route::post('/bookings/{id}/decline', [TherapistController::class, 'declineBooking']);
        Route::post('/bookings/{id}/update-status', [TherapistController::class, 'updateStatus']);
        Route::post('/toggle-availability', [TherapistController::class, 'toggleAvailability']);
    });

    // Staff routes (accessible by staff and super_admin)
    Route::middleware('role:staff,super_admin')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/dashboard', [StaffController::class, 'dashboard']);
        Route::get('/users', [StaffController::class, 'users']);
        Route::post('/users', [StaffController::class, 'createUser']);
        Route::put('/users/{id}', [StaffController::class, 'updateUser']);
        Route::delete('/users/{id}', [StaffController::class, 'deleteUser']);
        Route::get('/therapists', [StaffController::class, 'therapists']);
        Route::get('/therapists/{id}', [StaffController::class, 'therapist']);
        Route::post('/therapists', [StaffController::class, 'createTherapist']);
        Route::put('/therapists/{id}', [StaffController::class, 'updateTherapist']);
        Route::get('/bookings', [StaffController::class, 'bookings']);
        Route::delete('/therapists/{id}', [StaffController::class, 'deleteTherapist']);
        Route::delete('/bookings/{id}', [StaffController::class, 'deleteBooking']);
    });

    // Super Admin routes (for managing admins)
    Route::middleware('role:super_admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/admins', [StaffController::class, 'getAdmins']);
        Route::post('/admins', [StaffController::class, 'createAdmin']);
        Route::put('/admins/{id}', [StaffController::class, 'updateAdmin']);
        Route::delete('/admins/{id}', [StaffController::class, 'deleteAdmin']);
    });
});

