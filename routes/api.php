<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentExempleController;
use App\Http\Controllers\DocumentFileController;
use Illuminate\Support\Facades\Route;

Route::get('/exemples/document', [DocumentExempleController::class, 'show']);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->whereNumber('document');
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->whereNumber('document');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->whereNumber('document');
    Route::post('/documents/{document}/file', [DocumentFileController::class, 'store'])->whereNumber('document');
    Route::get('/documents/{document}/file', [DocumentFileController::class, 'download'])->whereNumber('document');
});
