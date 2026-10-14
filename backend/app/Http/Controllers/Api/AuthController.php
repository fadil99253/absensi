<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nip' => ['required', 'string', 'min:2', 'max:32', 'regex:/^[a-zA-Z0-9.-]+$/', 'unique:users,username', 'unique:employees,employee_id'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')],
            'department' => ['required', 'string', 'max:80'],
            'position' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'username' => strtoupper(trim($validated['nip'])),
                'email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'role' => 'karyawan',
            ]);

            Employee::query()->create([
                'employee_id' => $user->username,
                'user_id' => $user->id,
                'name' => $user->name,
                'role' => isset($validated['position']) && trim($validated['position']) !== ''
                    ? trim($validated['position'])
                    : 'Karyawan',
                'department' => trim($validated['department']),
                'email' => $user->email,
                'active' => true,
            ]);

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'Akun karyawan berhasil dibuat.',
            'data' => [
                'user' => $this->userData($user),
                'token' => $user->createToken('hadirku-frontend')->plainTextToken,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('username', strtoupper(trim($credentials['username'])))
            ->whereHas('employee', fn ($query) => $query->where('active', true))
            ->orWhere(function ($query) use ($credentials): void {
                $query->where('username', strtoupper(trim($credentials['username'])))
                    ->where('role', 'admin');
            })
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username/NIP atau kata sandi tidak sesuai.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil masuk.',
            'data' => [
                'user' => $this->userData($user),
                'token' => $user->createToken('hadirku-frontend')->plainTextToken,
            ],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:191']]);
        Password::sendResetLink(['email' => strtolower(trim($validated['email']))]);

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, tautan pemulihan akan dikirim.',
            'data' => null,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data akun.',
            'data' => ['user' => $this->userData($request->user())],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar.',
            'data' => null,
        ]);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'employee_id' => $user->employee?->employee_id,
            'face_registered' => $user->employee?->faceProfile()->exists() ?? false,
        ];
    }
}
