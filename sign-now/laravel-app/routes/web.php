<?php

use App\Http\Controllers\ApiTemplateController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\RecipientController;
use App\Http\Controllers\SigningController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', fn () => redirect()->route('dashboard'));

// ─── Public signing routes (no auth required) ────────────────────────────────
Route::get('/signing/validate/{token}', [SigningController::class, 'validation'])
    ->name('signing.validation');
Route::get('/signing/unauthorized', fn () => view('signing.unauthorized'))
    ->name('signing.unauthorized');
Route::get('/signing/{uuid}', [SigningController::class, 'signing'])
    ->name('signing.sign');
Route::get('/signing/{uuid}/done', [SigningController::class, 'done'])
    ->name('signing.done');
Route::get('/signing/{uuid}/fields', [FieldController::class, 'getFields'])
    ->name('signing.fields');
Route::get('/signing/{uuid}/recipient-values', [PdfController::class, 'recipientValues'])
    ->name('signing.recipientValues');
Route::post('/signing/{uuid}/generate', [PdfController::class, 'generate'])
    ->name('signing.generate');
Route::get('/signing/{uuid}/serve/{which}', [DocumentController::class, 'serve'])
    ->name('signing.serve');

// Public API trigger (external systems call this)
Route::post('/api/templates/trigger', [ApiTemplateController::class, 'trigger'])
    ->name('api.templates.trigger');

// ─── Authenticated admin routes ───────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

    // Documents
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::post('/documents/{uuid}/rename', [DocumentController::class, 'rename'])->name('documents.rename');
    Route::post('/documents/{uuid}/delete', [DocumentController::class, 'delete'])->name('documents.delete');
    Route::get('/documents/{uuid}/serve/{which}', [DocumentController::class, 'serve'])->name('documents.serve');
    Route::get('/documents/{uuid}/download/{which}', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{uuid}/view', [DocumentController::class, 'view'])->name('documents.view');
    Route::get('/documents/{uuid}/audit', [DocumentController::class, 'audit'])->name('documents.audit');

    // Field Builder
    Route::get('/documents/{uuid}/edit', [FieldController::class, 'edit'])->name('documents.edit');
    Route::get('/documents/{uuid}/fields', [FieldController::class, 'getFields'])->name('documents.fields');
    Route::post('/documents/fields', [FieldController::class, 'saveFields'])->name('documents.saveFields');

    // Recipients
    Route::get('/documents/{uuid}/recipients', [RecipientController::class, 'getRecipients'])->name('documents.recipients');
    Route::post('/documents/{uuid}/recipients', [RecipientController::class, 'saveRecipients'])->name('documents.saveRecipients');
    Route::post('/documents/{uuid}/invite/{recipientId}', [RecipientController::class, 'sendInvite'])->name('documents.sendInvite');
    Route::post('/documents/{uuid}/send-all', [RecipientController::class, 'sendAll'])->name('documents.sendAll');
    Route::post('/documents/{uuid}/resend-all', [RecipientController::class, 'resendAll'])->name('documents.resendAll');
    Route::post('/documents/{uuid}/send-complete', [RecipientController::class, 'sendComplete'])->name('documents.sendComplete');
    Route::post('/documents/add-customer/{uuid}', [RecipientController::class, 'addCustomerRecipient'])->name('documents.addCustomer');

    // PDF Fill & Finalize
    Route::get('/documents/{uuid}/fill', [PdfController::class, 'fill'])->name('documents.fill');
    Route::post('/documents/{uuid}/generate', [PdfController::class, 'generate'])->name('documents.generate');
    Route::get('/documents/{uuid}/recipient-values', [PdfController::class, 'recipientValues'])->name('documents.recipientValues');

    // ─── Admin-only: customers, templates, API templates, finalize ───
    Route::middleware('admin')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/data', [CustomerController::class, 'data'])->name('customers.data');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::post('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::post('/customers/{id}/update', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{id}/delete', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::get('/documents/{uuid}/finalize', [PdfController::class, 'finalize'])->name('documents.finalize');
        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/data', [TemplateController::class, 'data'])->name('templates.data');
        Route::post('/templates/{uuid}/mark', [TemplateController::class, 'markTemplate'])->name('templates.mark');
        Route::post('/templates/{uuid}/unmark', [TemplateController::class, 'unmarkTemplate'])->name('templates.unmark');
        Route::post('/templates/{uuid}/use', [TemplateController::class, 'useTemplate'])->name('templates.use');
        Route::post('/api/templates/{uuid}/toggle', [ApiTemplateController::class, 'toggle'])->name('api.templates.toggle');
        Route::get('/api/templates/{uuid}/info', [ApiTemplateController::class, 'info'])->name('api.templates.info');
        Route::post('/api/templates/{uuid}/set-main-nda', [ApiTemplateController::class, 'setMainNda'])->name('api.templates.setMainNda');
        Route::post('/api/templates/{uuid}/webhook', [ApiTemplateController::class, 'setWebhook'])->name('api.templates.setWebhook');
        Route::get('/api/templates/list', [ApiTemplateController::class, 'templateList'])->name('api.templates.list');
    });
});

// ─── Breeze auth & profile routes ────────────────────────────────────────────
use App\Http\Controllers\ProfileController;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/signing', [ProfileController::class, 'updateSigning'])->name('profile.signing');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
