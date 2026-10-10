<?php

use App\Http\Controllers\DemoCredentialsController;
use App\Http\Controllers\DemoWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/demo', [DemoWorkspaceController::class, 'store'])
    ->middleware(['guest', 'throttle:demo-workspaces'])
    ->name('demo.store');

Route::middleware('auth')->prefix('demo/credentials')->group(function () {
    Route::get('/', [DemoCredentialsController::class, 'show'])->name('demo.credentials');
    Route::get('/download', [DemoCredentialsController::class, 'download'])->name('demo.credentials.download');
    Route::post('/continue', [DemoCredentialsController::class, 'destroy'])->name('demo.credentials.continue');
});
