<?php

use App\Http\Controllers\DocumentExempleController;
use Illuminate\Support\Facades\Route;

Route::get('/exemples/document', [DocumentExempleController::class, 'show']);
