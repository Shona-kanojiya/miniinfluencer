<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/watchlist');
    }
    return redirect('/login');
});
Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');


Route::post('/login', function (Request $request) {

    // Validate input
    $validator = Validator::make($request->all(), [
        'email' => ['required', 'email'],
        'password' => ['required', 'min:8'],
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    // Attempt login
    if (Auth::attempt($request->only('email', 'password'))) {
        $request->session()->regenerate();
        return redirect('/watchlist');
    }

    // Auth failed
    return back()->withErrors([
        'auth' => 'Invalid credentials',
    ])->withInput();
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
});

Route::middleware('auth')->group(function () {
    Route::get('/watchlist',              [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::get('/watchlist/create',       [WatchlistController::class, 'create'])->name('watchlist.create');
    Route::post('/watchlist',             [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::get('/watchlist/{profile}',    [WatchlistController::class, 'show'])->name('watchlist.show');
    Route::post('/watchlist/{profile}/refetch', [WatchlistController::class, 'refetch'])->name('watchlist.refetch');
    Route::post('/webhooks/{provider}',   [WebhookController::class, 'handle']);
    Route::get('/healthz',                [HealthController::class, 'check']);
});