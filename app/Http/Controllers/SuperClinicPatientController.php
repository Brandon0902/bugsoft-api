<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SuperClinicPatientController extends Controller
{
    use ApiResponse;

    public function index(Clinic $clinic): JsonResponse
    {
        $patients = User::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', 'pacient')
            ->with('patientProfile')
            ->latest()
            ->get();

        return $this->successResponse($patients, 'Pacientes de clínica listados.');
    }

    public function store(Clinic $clinic, StorePatientRequest $request): JsonResponse
    {
        $data = $request->validated();

        $patient = DB::transaction(function () use ($clinic, $data): User {
            $user = User::query()->create([
                'clinic_id' => $clinic->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'] ?? 'ChangeMe123!',
                'phone' => $data['phone'] ?? null,
                'role' => 'pacient',
                'status' => $data['status'] ?? true,
            ]);

            $user->patientProfile()->create([
                'clinic_id' => $clinic->id,
                'birth_date' => data_get($data, 'profile.birth_date'),
                'gender' => data_get($data, 'profile.gender'),
                'address' => data_get($data, 'profile.address'),
                'allergies' => data_get($data, 'profile.allergies'),
                'notes' => data_get($data, 'profile.notes'),
            ]);

            return $user->load('patientProfile');
        });

        return $this->successResponse($patient, 'Paciente creado en clínica.', 201);
    }

    public function show(Clinic $clinic, int $id): JsonResponse
    {
        $patient = $this->findClinicPatient($clinic->id, $id);

        return $this->successResponse($patient, 'Paciente obtenido.');
    }

    public function update(Clinic $clinic, UpdatePatientRequest $request, int $id): JsonResponse
    {
        $patient = $this->findClinicPatient($clinic->id, $id);
        $data = $request->validated();

        DB::transaction(function () use ($clinic, $patient, $data): void {
            $patient->fill(collect($data)->only(['name', 'email', 'phone', 'status'])->toArray());
            if (array_key_exists('password', $data) && ! empty($data['password'])) {
                $patient->password = $data['password'];
            }
            $patient->save();

            if (array_key_exists('profile', $data)) {
                $patient->patientProfile()->updateOrCreate(
                    ['user_id' => $patient->id],
                    [
                        'clinic_id' => $clinic->id,
                        'birth_date' => data_get($data, 'profile.birth_date'),
                        'gender' => data_get($data, 'profile.gender'),
                        'address' => data_get($data, 'profile.address'),
                        'allergies' => data_get($data, 'profile.allergies'),
                        'notes' => data_get($data, 'profile.notes'),
                    ]
                );
            }
        });

        return $this->successResponse($patient->fresh()->load('patientProfile'), 'Paciente actualizado.');
    }

    public function destroy(Clinic $clinic, int $id): JsonResponse
    {
        $patient = $this->findClinicPatient($clinic->id, $id);

        DB::transaction(function () use ($patient): void {
            $patient->patientProfile()?->delete();
            $patient->delete();
        });

        return $this->successResponse([], 'Paciente eliminado.');
    }

    private function findClinicPatient(int $clinicId, int $id): User
    {
        return User::query()
            ->where('id', $id)
            ->where('clinic_id', $clinicId)
            ->where('role', 'pacient')
            ->with('patientProfile')
            ->firstOrFail();
    }
}
