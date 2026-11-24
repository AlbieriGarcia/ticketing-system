<?php

namespace App\Services;

use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(string $name, string $email, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $password,
        ]);
    }

    public function login(string $email, string $password): string
    {
        $user = User::where('email', strtolower($email))->first();

        // Mismo error si no existe el email o si la contraseña es incorrecta, para no revelar qué usuarios existen
        if (! $user || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        return $user->createToken('api')->plainTextToken;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
