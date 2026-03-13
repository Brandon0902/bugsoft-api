<?php

use App\Http\Controllers\AdminClinicController;
use App\Http\Controllers\AdminReceptionistController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DentistAppointmentController;
use App\Http\Controllers\PacientAppointmentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PublicClinicController;
use App\Http\Controllers\SuperClinicController;
use App\Http\Controllers\SuperClinicPatientController;
use App\Http\Controllers\SuperClinicReceptionistController;
use App\Http\Controllers\SuperClinicUserController;
use App\Http\Controllers\SuperClinicAppointmentController;
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
    Route::get('/clinics/{clinic}/users/{user}', [SuperClinicUserController::class, 'show']);
    Route::patch('/clinics/{clinic}/users/{user}', [SuperClinicUserController::class, 'update']);
    Route::delete('/clinics/{clinic}/users/{user}', [SuperClinicUserController::class, 'destroy']);

    Route::get('/clinics/{clinic}/patients', [SuperClinicPatientController::class, 'index']);
    Route::post('/clinics/{clinic}/patients', [SuperClinicPatientController::class, 'store']);
    Route::get('/clinics/{clinic}/patients/{id}', [SuperClinicPatientController::class, 'show']);
    Route::patch('/clinics/{clinic}/patients/{id}', [SuperClinicPatientController::class, 'update']);
    Route::delete('/clinics/{clinic}/patients/{id}', [SuperClinicPatientController::class, 'destroy']);

    Route::get('/clinics/{clinic}/receptionists', [SuperClinicReceptionistController::class, 'index']);
    Route::post('/clinics/{clinic}/receptionists', [SuperClinicReceptionistController::class, 'store']);
    Route::get('/clinics/{clinic}/receptionists/{id}', [SuperClinicReceptionistController::class, 'show']);
    Route::patch('/clinics/{clinic}/receptionists/{id}', [SuperClinicReceptionistController::class, 'update']);
    Route::delete('/clinics/{clinic}/receptionists/{id}', [SuperClinicReceptionistController::class, 'destroy']);

    Route::get('/clinics/{clinic}/appointments', [SuperClinicAppointmentController::class, 'index']);
    Route::post('/clinics/{clinic}/appointments', [SuperClinicAppointmentController::class, 'store']);
    Route::get('/clinics/{clinic}/appointments/{appointment}', [SuperClinicAppointmentController::class, 'show']);
    Route::patch('/clinics/{clinic}/appointments/{appointment}', [SuperClinicAppointmentController::class, 'update']);
    Route::patch('/clinics/{clinic}/appointments/{appointment}/status', [SuperClinicAppointmentController::class, 'updateStatus']);
});

Route::middleware(['auth:sanctum', 'role:admin,receptionist'])->prefix('admin')->group(function (): void {
    Route::get('/patients', [PatientController::class, 'index']);
    Route::post('/patients', [PatientController::class, 'store']);
    Route::get('/patients/{id}', [PatientController::class, 'show']);
    Route::patch('/patients/{id}', [PatientController::class, 'update']);
    Route::delete('/patients/{id}', [PatientController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('/clinic', [AdminClinicController::class, 'show']);
    Route::put('/clinic', [AdminClinicController::class, 'update']);
    Route::patch('/clinic', [AdminClinicController::class, 'update']);

    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::patch('/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('/receptionists', [AdminReceptionistController::class, 'index']);
    Route::post('/receptionists', [AdminReceptionistController::class, 'store']);
    Route::get('/receptionists/{id}', [AdminReceptionistController::class, 'show']);
    Route::patch('/receptionists/{id}', [AdminReceptionistController::class, 'update']);
    Route::delete('/receptionists/{id}', [AdminReceptionistController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,receptionist,dentist'])->group(function (): void {
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
});

// Legacy compatibility endpoint for dentist clients still consuming /dentist/appointments.
Route::middleware(['auth:sanctum', 'role:dentist'])->get('/dentist/appointments', [DentistAppointmentController::class, 'index']);
Route::middleware(['auth:sanctum', 'role:pacient'])->get('/pacient/appointments', [PacientAppointmentController::class, 'index']);
