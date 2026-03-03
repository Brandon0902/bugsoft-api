<?php

use App\Http\Controllers\AdminClinicController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientAppointmentController;
use App\Http\Controllers\DentistAppointmentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PublicClinicController;
use App\Http\Controllers\SuperClinicController;
use App\Http\Controllers\SuperClinicUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::get('/public/clinics', [PublicClinicController::class, 'index']);

Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('super')->group(function (): void {
    Route::post('/clinics', [SuperClinicController::class, 'store']);
    Route::get('/clinics', [SuperClinicController::class, 'index']);
    Route::get('/clinics/{clinic}', [SuperClinicController::class, 'show']);
    Route::put('/clinics/{clinic}', [SuperClinicController::class, 'update']);
    Route::patch('/clinics/{clinic}', [SuperClinicController::class, 'update']);
    Route::delete('/clinics/{clinic}', [SuperClinicController::class, 'destroy']);

    Route::post('/clinics/{clinic}/users', [SuperClinicUserController::class, 'store']);
    Route::get('/clinics/{clinic}/users', [SuperClinicUserController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('/clinic', [AdminClinicController::class, 'show']);
    Route::put('/clinic', [AdminClinicController::class, 'update']);
    Route::patch('/clinic', [AdminClinicController::class, 'update']);

    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users', [AdminUserController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role:admin,receptionist'])->group(function (): void {
    Route::apiResource('patients', PatientController::class)->only(['index', 'store', 'show', 'update']);

    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
});

Route::middleware(['auth:sanctum', 'role:dentist'])->get('/dentist/appointments', [DentistAppointmentController::class, 'index']);
Route::middleware(['auth:sanctum', 'role:client'])->get('/client/appointments', [ClientAppointmentController::class, 'index']);
