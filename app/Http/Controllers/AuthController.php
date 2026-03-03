<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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