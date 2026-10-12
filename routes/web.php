<?php

use App\Http\Controllers\AuthorizedMcpClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoCredentialsController;
use App\Http\Controllers\DemoWorkspaceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('/about', 'about')->name('about');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');

Route::post('/demo', [DemoWorkspaceController::class, 'store'])
    ->middleware(['guest', 'throttle:demo-workspaces'])
    ->name('demo.store');

Route::middleware('auth')->group(function () {
    Route::prefix('demo/credentials')->group(function () {
        Route::get('/', [DemoCredentialsController::class, 'show'])->name('demo.credentials');
        Route::get('/download', [DemoCredentialsController::class, 'download'])->name('demo.credentials.download');
        Route::post('/continue', [DemoCredentialsController::class, 'destroy'])->name('demo.credentials.continue');
    });

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/settings', [WorkspaceController::class, 'show'])->name('settings');
    Route::delete('/settings/mcp-clients/{client}', [AuthorizedMcpClientController::class, 'destroy'])->name('settings.clients.destroy');
    Route::delete('/settings/workspace', [WorkspaceController::class, 'destroy'])->name('workspace.destroy');

    Route::resource('projects', ProjectController::class)->except('index');
    Route::resource('projects.tasks', TaskController::class)->scoped()->except(['index', 'show']);
});
