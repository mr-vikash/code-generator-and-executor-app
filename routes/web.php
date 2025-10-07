<?php
// routes/web.php

use App\Http\Controllers\CodeExecutorController;
use App\Http\Controllers\CodeGeneratorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('code-executor');
});

Route::get('/code-executor', [CodeExecutorController::class, 'index'])->name('code-executor');
Route::post('/code-executor/execute', [CodeExecutorController::class, 'execute'])->name('code-executor.execute');
Route::post('/code-generator/generate', [CodeGeneratorController::class, 'generate'])->name('code-generator.generate');