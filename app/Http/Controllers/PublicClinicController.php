<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;

class PublicClinicController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $clinics = Clinic::query()->where('status', true)->get();

        return $this->successResponse($clinics, 'Clínicas públicas listadas.');
    }
}
