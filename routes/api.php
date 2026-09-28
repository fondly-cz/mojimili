<?php

use App\Http\Controllers\Api\McpUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

// Files for MCP comment attachments, sent to a link from the create-upload-link tool.
Route::post('/mcp-uploads/{user}', McpUploadController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('mcp-uploads.store');
