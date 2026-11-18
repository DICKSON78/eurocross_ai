<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\ConnectController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/features', 'features')->name('features');
Route::view('/pricing', 'pricing')->name('pricing');
Route::view('/integrations', 'integrations')->name('integrations');
Route::view('/about', 'about')->name('about');
Route::view('/faq', 'faq')->name('faq');
Route::view('/contact', 'contact')->name('contact');

Route::redirect('/starter', config('payment.gumroad.checkout_starter'))->name('checkout.starter');
Route::redirect('/growth', config('payment.gumroad.checkout_growth'))->name('checkout.growth');
Route::redirect('/scale', config('payment.gumroad.checkout_scale'))->name('checkout.scale');

Route::view('/billing/thank-you', 'billing.thank-you')->name('billing.thank-you');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth/firebase', [AuthController::class, 'firebaseLogin'])->name('auth.firebase');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/connect/start', [ConnectController::class, 'start'])->name('connect.start');

Route::middleware('auth')->group(function (): void {
    Route::get('/connect/confirm', [ConnectController::class, 'confirm'])->name('connect.confirm');
    Route::post('/connect/approve', [ConnectController::class, 'approve'])
        ->middleware('throttle:10,1')
        ->name('connect.approve');

    Route::get('/billing/status', fn () => response()->json([
        'plan' => auth()->user()->euroCrossSettings?->plan ?? 'starter',
        'status' => auth()->user()->euroCrossSettings?->subscription_status ?? 'none',
        'access' => auth()->user()->euroCrossSettings?->accessStatus() ?? ['status' => 'inactive'],
    ]))->name('billing.status');

    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/{module}', [ClientDashboardController::class, 'index'])->name('dashboard.module');
    Route::post('/dashboard/keys', [ClientDashboardController::class, 'generateKey'])->name('dashboard.keys.generate');
});

Route::view('/app', 'react-dashboard')->name('app');
