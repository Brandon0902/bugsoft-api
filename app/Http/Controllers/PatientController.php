<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $authUser = request()->user();

        $patients = User::query()
            ->where('clinic_id', $authUser->clinic_id)
            ->where('role', 'pacient')
            ->with('patientProfile')
            ->latest()
            ->get();

        return $this->successResponse($patients, 'Pacientes listados.');
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $authUser = request()->user();
        $data = $request->validated();

        $patient = DB::transaction(function () use ($authUser, $data) {
            $user = User::query()->create([
                'clinic_id' => $authUser->clinic_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'] ?? 'ChangeMe123!',
                'phone' => $data['phone'] ?? null,
                'role' => 'pacient',
                'status' => $data['status'] ?? true,
            ]);

            PatientProfile::query()->create([
                'user_id' => $user->id,
                'clinic_id' => $authUser->clinic_id,
                'birth_date' => data_get($data, 'profile.birth_date'),
                'gender' => data_get($data, 'profile.gender'),
                'address' => data_get($data, 'profile.address'),
                'allergies' => data_get($data, 'profile.allergies'),
                'notes' => data_get($data, 'profile.notes'),
            ]);

            return $user->load('patientProfile');
        });

        return $this->successResponse($patient, 'Paciente creado.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $patient = $this->findClinicPatient($id);

        return $this->successResponse($patient, 'Paciente obtenido.');
    }

    public function update(UpdatePatientRequest $request, int $id): JsonResponse
    {
        $patient = $this->findClinicPatient($id);
        $data = $request->validated();

        DB::transaction(function () use ($patient, $data) {
            $patient->fill(collect($data)->only(['name', 'email', 'phone', 'status'])->toArray());
            if (array_key_exists('password', $data) && ! empty($data['password'])) {
                $patient->password = $data['password'];
            }
            $patient->save();

            if (array_key_exists('profile', $data)) {
                $patient->patientProfile()->updateOrCreate(
                    ['user_id' => $patient->id],
                    [
                        'clinic_id' => $patient->clinic_id,
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

    protected function findClinicPatient(int $id): User
    {
        $authUser = request()->user();

        return User::query()
            ->where('id', $id)
            ->where('clinic_id', $authUser->clinic_id)
            ->where('role', 'pacient')
            ->with('patientProfile')
            ->firstOrFail();
    }
}
