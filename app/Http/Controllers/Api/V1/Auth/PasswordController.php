<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 * @subgroup Password
 * Password reset & update endpoints.
 * @unauthenticated Forgot/reset (forgotPassword, resetPassword, verifyResetOtp, resetPasswordWithToken) do not require auth; updatePassword requires Bearer token.
 */
class PasswordController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        try {
            $token = $this->authService->forgotPassword($request->email);
            return response_success(
                'A password reset OTP/link has been sent to your email.',
            );
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
            'otp' => 'required|numeric',
            'token' => 'required|string',
        ]);

        try {
            $this->authService->resetPassword($request->all());
            return response_success('Password has been successfully reset.');
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $this->authService->updatePassword(
                $request->user(),
                $request->only('current_password', 'new_password')
            );
            return response_success('Password successfully updated.');
        } catch (ValidationException $e) {
            return response_error($e->getMessage(), $e->errors(), 422);
        }
    }


    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric',
        ]);

        try {
            $resetToken = $this->authService->verifyPasswordResetOtp($request->only('email', 'otp'));
            return response_success('OTP verified successfully.', ['reset_token' => $resetToken]);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }


    public function resetPasswordWithToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $this->authService->resetPasswordWithToken($request->all());
            return response_success('Password has been successfully reset.');
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }
}
