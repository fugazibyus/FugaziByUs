<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\AiWorkflowController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\Webhooks\AnthropicWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('version', VersionController::class)->name('version');

// Signed by Anthropic, so no CSRF token (the controller checks the signature instead).
Route::post('webhooks/anthropic', AnthropicWebhookController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.anthropic');

Route::get('ai-workflow', AiWorkflowController::class)->name('ai-workflow');

Route::get('contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'show'])->name('show');
    Route::patch('profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::patch('email', [AccountController::class, 'updateEmail'])
        ->middleware('throttle:6,1')
        ->name('email.update');
    Route::put('password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});
