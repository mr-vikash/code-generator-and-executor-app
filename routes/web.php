<?php
// routes/web.php

use App\Http\Controllers\CodeExecutorController;
use App\Http\Controllers\CodeGeneratorController;
use App\Http\Controllers\CodeHistoryController;

// Code Executor Routes
Route::get('/code-executor', [CodeExecutorController::class, 'index'])->name('code-executor');
Route::post('/code-executor/execute', [CodeExecutorController::class, 'execute'])->name('code-executor.execute');
Route::post('/code-executor/download', [CodeExecutorController::class, 'download'])->name('code-executor.download');
Route::post('/code-executor/upload-git', [CodeExecutorController::class, 'uploadGitRepo'])->name('code-executor.upload-git');
Route::post('/code-executor/upload-project', [CodeExecutorController::class, 'uploadProject'])->name('code-executor.upload-project');

// Code Generator Routes
Route::post('/code-generator/generate', [CodeGeneratorController::class, 'generate'])->name('code-generator.generate');
Route::get('/code-generator/generate-stream', [CodeGeneratorController::class, 'generateStream'])->name('code-generator.generate-stream');
Route::post('/code-generator/save-stream', [CodeGeneratorController::class, 'saveFromStream'])->name('code-generator.save-stream');

// History Routes
Route::get('/code-history', [CodeHistoryController::class, 'index'])->name('code-history.index');
Route::get('/code-history/{id}', [CodeHistoryController::class, 'show'])->name('code-history.show');
Route::delete('/code-history/{id}', [CodeHistoryController::class, 'destroy'])->name('code-history.destroy');
Route::delete('/code-history', [CodeHistoryController::class, 'clear'])->name('code-history.clear');
Route::post('/code-executor/import-repo', [CodeExecutorController::class, 'importRepo'])->name('code-executor.import-repo');