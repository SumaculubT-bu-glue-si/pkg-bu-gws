<?php

use Illuminate\Support\Facades\Route;
use Bu\Server\Http\Controllers\API\LocationController;
use Bu\Server\Http\Controllers\API\AssetController;
use Bu\Server\Http\Controllers\API\AuditController;
use Bu\Server\Http\Controllers\API\CorrectiveActionController;
use Bu\Server\Http\Controllers\API\EmployeeController;
use Bu\Server\Http\Controllers\API\ProjectController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Location routes without authentication
Route::get('locations', [LocationController::class, 'index']);
Route::get('locations/{id}', [LocationController::class, 'show']);
Route::post('locations', [LocationController::class, 'store']);
Route::put('locations/{id}', [LocationController::class, 'update']);
Route::delete('locations/{id}', [LocationController::class, 'destroy']);

// Asset routes without authentication
Route::get('assets', [AssetController::class, 'index']);
Route::get('assets/{id}', [AssetController::class, 'show']);
Route::post('assets', [AssetController::class, 'store']);
Route::put('assets/{id}', [AssetController::class, 'update']);
Route::delete('assets/{id}', [AssetController::class, 'destroy']);

// Employee routes without authentication
Route::get('employees', [EmployeeController::class, 'index']);
Route::get('employees/{id}', [EmployeeController::class, 'show']);
Route::post('employees', [EmployeeController::class, 'store']);
Route::put('employees/{id}', [EmployeeController::class, 'update']);
Route::delete('employees/{id}', [EmployeeController::class, 'destroy']);

// Audit routes without authentication
Route::get('audits', [AuditController::class, 'index']);
Route::get('audits/{id}', [AuditController::class, 'show']);
Route::post('audits', [AuditController::class, 'store']);
Route::put('audits/{id}', [AuditController::class, 'update']);
Route::delete('audits/{id}', [AuditController::class, 'destroy']);

// Corrective Action routes without authentication
Route::get('corrective-actions', [CorrectiveActionController::class, 'index']);
Route::get('corrective-actions/{id}', [CorrectiveActionController::class, 'show']);
Route::post('corrective-actions', [CorrectiveActionController::class, 'store']);
Route::put('corrective-actions/{id}', [CorrectiveActionController::class, 'update']);
Route::delete('corrective-actions/{id}', [CorrectiveActionController::class, 'destroy']);
Route::post('corrective-actions/send-reminders', [CorrectiveActionController::class, 'sendReminders']);

// Employee Audits routes
Route::prefix('employee-audits')->group(function () {
    Route::get('/available-plans', [AuditController::class, 'getAvailablePlans']);
    Route::post('/request-access', [AuditController::class, 'requestAccess']);
    Route::get('/access/{token}', [AuditController::class, 'validateAccessToken']);
    Route::get('/plan/{token}/{planId}', [AuditController::class, 'getPlan']);
    Route::get('/history/{token}', [AuditController::class, 'getAuditHistory']);
    Route::put('/update-asset/{token}', [AuditController::class, 'handleAssetUpdate']);
    Route::get('/corrective-actions', [CorrectiveActionController::class, 'getEmployeeActions']);
    Route::put('/update-action-status', [CorrectiveActionController::class, 'updateActionStatus']);
    Route::get('/audit-asset/{auditAssetId}', [AuditController::class, 'getAuditAsset']);
});

// Google Workspace webhook endpoint
Route::post('/gws/webhook', [Bu\Gws\Http\Controllers\GoogleWorkspaceWebhookController::class, 'handle']);
