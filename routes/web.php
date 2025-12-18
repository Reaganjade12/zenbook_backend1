<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OTPVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CleanerController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ProfileController;

// Test route to verify storage routing works
Route::get('/storage-test', function () {
    $testFile = 'profiles/test.txt';
    $testPath = storage_path('app/public/' . $testFile);
    
    // Create a test file if it doesn't exist
    if (!file_exists($testPath)) {
        \Storage::disk('public')->put($testFile, 'Test file for storage route verification');
    }
    
    return response()->json([
        'message' => 'Storage route is accessible',
        'storage_path' => storage_path('app/public'),
        'storage_exists' => file_exists(storage_path('app/public')),
        'profiles_dir_exists' => file_exists(storage_path('app/public/profiles')),
        'test_file_url' => url('/storage/' . $testFile),
        'server_info' => [
            'request_uri' => request()->server('REQUEST_URI'),
            'document_root' => request()->server('DOCUMENT_ROOT'),
            'script_name' => request()->server('SCRIPT_NAME'),
        ],
    ]);
});

// Serve storage files with CORS headers (MUST be before other routes, works without symlink)
Route::options('/storage/{path}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization')
        ->header('Access-Control-Max-Age', '3600');
})->where('path', '.*');

Route::get('/storage/{path}', function ($path) {
    // Log that route was hit
    \Log::info('Storage route accessed', [
        'requested_path' => $path,
        'full_url' => request()->fullUrl(),
        'method' => request()->method(),
        'server_request_uri' => request()->server('REQUEST_URI'),
    ]);
    
    // Security: prevent directory traversal
    $path = str_replace('..', '', $path);
    $path = ltrim($path, '/');
    
    // Try storage/app/public first (where Laravel stores files)
    $filePath = storage_path('app/public/' . $path);
    
    // Fallback: also check public/storage (in case of symlink or direct copy)
    $publicStoragePath = public_path('storage/' . $path);
    
    \Log::info('Storage route processing', [
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
        $storagePath = realpath(storage_path('app/public'));
    } elseif (file_exists($publicStoragePath) && is_file($publicStoragePath)) {
        $actualFilePath = $publicStoragePath;
        $storagePath = realpath(public_path('storage'));
    } else {
        \Log::warning('Storage file not found in either location', [
            'requested_path' => $path,
            'storage_path' => $filePath,
            'public_storage_path' => $publicStoragePath,
            'storage_exists' => file_exists($filePath),
            'public_exists' => file_exists($publicStoragePath),
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
    
    if (!$isAllowed) {
        \Log::warning('Storage file access denied - path traversal attempt or invalid path', [
            'requested_path' => $path,
            'file_path' => $actualFilePath,
            'real_path' => $realPath,
            'allowed_storage_path' => $allowedStoragePath,
            'allowed_public_path' => $allowedPublicPath,
        ]);
        abort(404, 'File not found');
    }
    
    // Check if file is readable
    if (!is_readable($actualFilePath)) {
        $perms = file_exists($actualFilePath) ? substr(sprintf('%o', fileperms($actualFilePath)), -4) : 'unknown';
        \Log::error('Storage file not readable', [
            'requested_path' => $path,
            'file_path' => $actualFilePath,
            'permissions' => $perms,
            'owner' => file_exists($actualFilePath) ? fileowner($actualFilePath) : null,
        ]);
        abort(403, 'File access denied - check file permissions');
    }
    
    try {
        $file = file_get_contents($actualFilePath);
        $mimeType = mime_content_type($actualFilePath) ?: 'application/octet-stream';
        
        \Log::info('Storage file served successfully', [
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
            ->header('X-Served-By', 'Laravel-Route'); // Debug header to confirm route was hit
    } catch (\Exception $e) {
        \Log::error('Error serving storage file', [
            'requested_path' => $path,
            'file_path' => $actualFilePath,
            'error' => $e->getMessage(),
        ]);
        abort(500, 'Error serving file');
    }
})->where('path', '.*');

// Home redirect
Route::get('/', function () {
	return view('welcome');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// OTP Email Verification Routes
Route::get('/verify-otp', [OTPVerificationController::class, 'show'])->name('otp.verify')->middleware('guest');
Route::post('/verify-otp', [OTPVerificationController::class, 'verify'])->middleware('guest');
Route::post('/resend-otp', [OTPVerificationController::class, 'resend'])->name('otp.resend')->middleware('guest');

// Password Reset Routes
Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPassword'])->name('password.request')->middleware('guest');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('guest');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset')->middleware('guest');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('guest');

// Profile Routes (All authenticated users)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/image', [ProfileController::class, 'deleteImage'])->name('profile.image.delete');
});

// Customer Routes
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [CustomerController::class, 'dashboard'])->name('dashboard');
    Route::get('/booking/create', [CustomerController::class, 'showBookingForm'])->name('booking.create');
    Route::post('/booking/create', [CustomerController::class, 'createBooking'])->name('booking.store');
    Route::get('/history', [CustomerController::class, 'bookingHistory'])->name('history');
});

// Therapist Routes
Route::middleware(['auth', 'role:cleaner'])->prefix('therapist')->name('therapist.')->group(function () {
    Route::get('/dashboard', [CleanerController::class, 'dashboard'])->name('dashboard');
    Route::get('/bookings', [CleanerController::class, 'bookings'])->name('bookings');
    Route::get('/customers', [CleanerController::class, 'customers'])->name('customers');
    Route::post('/booking/{id}/accept', [CleanerController::class, 'acceptBooking'])->name('booking.accept');
    Route::post('/booking/{id}/decline', [CleanerController::class, 'declineBooking'])->name('booking.decline');
    Route::post('/booking/{id}/update-status', [CleanerController::class, 'updateStatus'])->name('booking.update-status');
    Route::post('/toggle-availability', [CleanerController::class, 'toggleAvailability'])->name('toggle-availability');
});

// Staff Routes
Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [StaffController::class, 'dashboard'])->name('dashboard');
    Route::delete('/user/{id}', [StaffController::class, 'deleteUser'])->name('user.delete');
    Route::delete('/cleaner/{id}', [StaffController::class, 'deleteCleaner'])->name('cleaner.delete');
    Route::delete('/booking/{id}', [StaffController::class, 'deleteBooking'])->name('booking.delete');
    
    // Therapist Management Routes
    Route::get('/therapists', [StaffController::class, 'indexTherapists'])->name('therapists.index');
    Route::get('/therapists/create', [StaffController::class, 'createTherapist'])->name('therapists.create');
    Route::post('/therapists', [StaffController::class, 'storeTherapist'])->name('therapists.store');
    Route::get('/therapists/{id}/edit', [StaffController::class, 'editTherapist'])->name('therapists.edit');
    Route::put('/therapists/{id}', [StaffController::class, 'updateTherapist'])->name('therapists.update');
    Route::delete('/therapists/{id}', [StaffController::class, 'deleteTherapist'])->name('therapists.delete');
});
