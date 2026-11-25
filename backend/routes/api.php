<?php

use App\Http\Controllers\{ExportController, RecurringController, WhatsAppController, AccountController, AuthController, BudgetController, CategoryController, HouseholdController, ReportController, SavingController, TransactionController};
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Unduhan ekspor: tautan sementara bertanda tangan (dibuat lewat POST /export/link)
Route::get('/export/download', [ExportController::class, 'download'])->name('export.download')->middleware(['signed', 'throttle:20,1']);

// Webhook Fonnte (tanpa login; diamankan rahasia di URL)
Route::post('/whatsapp/fonnte/{secret}', [WhatsAppController::class, 'webhook'])->middleware('throttle:60,1');
Route::get('/whatsapp/fonnte/{secret}', [WhatsAppController::class, 'ping']); // Fonnte bisa mengetes URL dengan GET saat menyimpan

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/whatsapp', [WhatsAppController::class, 'status']);
    Route::post('/whatsapp/link-code', [WhatsAppController::class, 'linkCode'])->middleware('throttle:10,1');
    Route::delete('/whatsapp', [WhatsAppController::class, 'unlink']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'update']);

    Route::get('/household', [HouseholdController::class, 'showMine']);
    Route::post('/household', [HouseholdController::class, 'store']);
    Route::post('/household/join', [HouseholdController::class, 'join'])->middleware('throttle:10,1');

    Route::middleware('household')->group(function () {
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/reports/monthly', [ReportController::class, 'monthly']);
        Route::get('/pulse', [ReportController::class, 'pulse']);
        Route::post('/export/link', [ExportController::class, 'link'])->middleware('throttle:20,1');

        Route::apiResource('transactions', TransactionController::class)->parameters(['transactions' => 'id']);
        Route::post('/transactions/{id}/restore', [TransactionController::class, 'restore']);

        Route::get('/recurring', [RecurringController::class, 'index']);
        Route::post('/recurring', [RecurringController::class, 'store']);
        Route::patch('/recurring/{id}', [RecurringController::class, 'update']);
        Route::delete('/recurring/{id}', [RecurringController::class, 'destroy']);

        Route::get('/accounts', [AccountController::class, 'index']);
        Route::post('/accounts', [AccountController::class, 'store']);
        Route::patch('/accounts/{id}', [AccountController::class, 'update']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);

        Route::get('/saving-goals', [SavingController::class, 'index']);
        Route::post('/saving-goals', [SavingController::class, 'store']);
        Route::get('/saving-goals/{id}', [SavingController::class, 'show']);
        Route::delete('/saving-goals/{id}', [SavingController::class, 'destroy']);
        Route::post('/saving-goals/{id}/entries', [SavingController::class, 'addEntry']);
        Route::post('/saving-goals/{id}/convert', [SavingController::class, 'convert']);
        Route::delete('/saving-goals/{id}/conversions/{entry}', [SavingController::class, 'undoConversion']);

        Route::get('/budgets', [BudgetController::class, 'index']);
        Route::put('/budgets', [BudgetController::class, 'upsert']);
        Route::post('/budgets/copy-previous', [BudgetController::class, 'copyPrevious']);
    });
});
