<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Middleware\JWTMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('auth.google-auth');
})->name('login');

Route::prefix('auth')->group(function () {
    Route::get('google/redirect', [UserController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('google/callback', [UserController::class, 'handleGoogleCallback'])->name('google.callback');
});

// Public route for deleting all data (if needed)
Route::get('/delete-all-data', function () {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::table('applicants')->truncate();
    DB::table('companies')->truncate();
    DB::table('users')->truncate();
    DB::table('jobs')->truncate();
    DB::table('job_applications')->truncate();
    DB::table('resumes')->truncate();
    DB::table('sessions')->truncate();
    DB::table('messages')->truncate();
    DB::table('notifications')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    return 'All data has been deleted successfully!';
});