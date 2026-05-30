<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth
 *
 * Registration, login, and session endpoints. Tokens are issued via Laravel
 * Sanctum and sent as `Authorization: Bearer {token}`.
 */
class AuthController extends Controller
{
    /**
     * Register
     *
     * Create a customer account and return an API token.
     *
     * @bodyParam name string required Example: Jane Doe
     * @bodyParam email string required Example: jane@example.com
     * @bodyParam password string required Min 8 chars. Example: secret123
     * @unauthenticated
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data);
        $user->assignRole('customer');

        return response()->json([
            'user' => $user->load('roles'),
            'token' => $user->createToken('register')->plainTextToken,
        ], 201);
    }

    /**
     * Login
     *
     * Exchange credentials for a Sanctum bearer token.
     *
     * @bodyParam email string required Example: customer@shop.test
     * @bodyParam password string required Example: password
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        return response()->json([
            'user' => $user->load('roles'),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    /**
     * Logout
     *
     * Revoke the current access token.
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Current user
     *
     * Return the authenticated user with their roles.
     *
     * @authenticated
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('roles')]);
    }
}
