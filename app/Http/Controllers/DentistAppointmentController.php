<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointment\IndexAppointmentRequest;
use Illuminate\Http\JsonResponse;

class DentistAppointmentController extends Controller
{
    public function __construct(private readonly AppointmentController $appointmentController)
    {
    }

    public function index(IndexAppointmentRequest $request): JsonResponse
    {
        // Legacy compatibility endpoint: delegates to the unified /appointments listing flow.
        return $this->appointmentController->index($request);
    }
}
