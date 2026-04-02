<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterPatientRequest;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return $this->errorResponse(
                'Credenciales inválidas.',
                ['auth' => ['Email o contraseña incorrectos.']],
                401
            );
        }

        if (! $user->status) {
            return $this->errorResponse(
                'Usuario inactivo.',
                ['auth' => ['Tu cuenta está deshabilitada.']],
                403
            );
        }

        // ✅ Sanctum estándar (NO usa user_id, usa tokenable_type/tokenable_id)
        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 'Login exitoso.');
    }

    public function registerPatient(RegisterPatientRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'clinic_id' => $data['clinic_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => Arr::get($data, 'phone'),
                'role' => 'pacient',
                'status' => true,
            ]);

            PatientProfile::query()->create([
                'user_id' => $user->id,
                'clinic_id' => $data['clinic_id'],
                'birth_date' => null,
                'gender' => null,
                'address' => null,
                'allergies' => null,
                'notes' => null,
            ]);

            return $user->load('patientProfile');
        });

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 'Paciente registrado.', 201);
    }

    public function logout(): JsonResponse
    {
        $user = request()->user();
        $token = request()->bearerToken();

        if ($user && $token) {
            $user->tokens()->where('token', hash('sha256', $token))->delete();
        }

        return $this->successResponse([], 'Logout exitoso.');
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(request()->user(), 'Perfil obtenido.');
    }
}
