<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 * Endpoints for user registration, login and logout.
 * @unauthenticated register login socialLogin
 */
class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        $this->authService->register($request->validated());
        return response_success('User registered. Please check your email for verification.', [], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required_without:phone_number|nullable|string|email',
            'phone_number' => 'required_without:email|nullable|string',
            'password' => 'required|string',
            'fcm_token' => 'nullable|string'
        ]);

        try {
            $data = $this->authService->login($request->only('email', 'phone_number', 'password', 'fcm_token'));
            return response_success('Login successful', $data);
        } catch (ValidationException $e) {
            return response_error($e->getMessage(), $e->errors(), 401);
        }
    }

    public function socialLogin(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:google,apple',
            'token' => 'required|string',
            'fcm_token' => 'nullable|string'
        ]);

        try {
            $data = $this->authService->socialLogin($request->only('provider', 'token', 'fcm_token'));
            return response_success('Social Login successful', $data);
        } catch (ValidationException $e) {
            return response_error($e->getMessage(), $e->errors(), 401);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response_success('Successfully logged out');
    }
}


