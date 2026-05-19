<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\SendOtpMail;
use App\Models\User;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {

                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);

                $roleToAssign = $request->input('role', 'customer');
                $user->assignRole($roleToAssign);


                $otpCode = (string) rand(100000, 999999);

                //  Store token parameters in our tracking table
                DB::table('otps')->insert([
                    'email' => $user->email,
                    'otp' => Hash::make($otpCode), // Hashed for security
                    'expires_at' => Carbon::now()->addMinutes(10),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                //  Dispatch verification message dispatch
                Mail::to($user->email)->send(new SendOtpMail($otpCode));

                return $user;
            });

            return response()->json([
                'status' => true,
                'message' => 'Registration successful! Please verify the 6-digit OTP code sent to your email.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first(),
                    'is_verified' => false
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getTraceAsString()], 422);
        }
    }


    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|string|size:6',
        ]);

        try {
            // Find the latest active token record for this email
            $otpRecord = DB::table('otps')
                ->where('email', $request->email)
                ->where('expires_at', '>', Carbon::now())
                ->latest()
                ->first();

            if (!$otpRecord || !Hash::check($request->otp, $otpRecord->otp)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or expired OTP verification token code.'
                ], 422);
            }

            $user = User::where('email', $request->email)->firstOrFail();

            // Guard check to prevent redundant executions
            if ($user->hasVerifiedEmail()) {
                return response()->json(['status' => true, 'message' => 'Account is already verified.'], 200);
            }

            // Mark user status timestamp as validated inside the DB
            $user->markEmailAsVerified();

            // Clear used tokens out of the system cache
            DB::table('otps')->where('email', $request->email)->delete();


            return response()->json([
                'status' => true,
                'message' => 'Email verified successfully! Welcome to the marketplace.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first(),
                    'is_verified' => true
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials do not match our system records.'],
                ]);
            }

            //  Stop user from entering dashboards if account verification is incomplete
            if (!$user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account email address is unverified. Please enter your OTP token to proceed.'
                ], 403);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'login successfully',
                'token' => $token,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first(),
                    'is_verified' => true
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }


    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $otpCode = (string) rand(100000, 999999);

            // Clear any old active recovery tokens for this email
            DB::table('otps')->where('email', $request->email)->delete();

            // Save the new recovery code
            DB::table('otps')->insert([
                'email' => $request->email,
                'otp' => Hash::make($otpCode), // Hashed for DB security
                'expires_at' => \Carbon\Carbon::now()->addMinutes(10),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ]);

            Mail::to($request->email)->send(new SendOtpMail($otpCode));

            return response()->json([
                'status' => true,
                'message' => 'Password reset OTP has been sent to your email.'
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }


    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|string|size:6',
        ]);

        try {
            $otpRecord = DB::table('otps')
                ->where('email', $request->email)
                ->where('expires_at', '>', \Carbon\Carbon::now())
                ->latest()
                ->first();

            if (!$otpRecord || !Hash::check($request->otp, $otpRecord->otp)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or expired OTP code.'
                ], 422);
            }


            $resetToken = Str::random(60);

            // Clear used tokens out of the system cache
            DB::table('otps')->where('email', $request->email)->update([
                'otp' => Hash::make($resetToken),
                'expires_at' => \Carbon\Carbon::now()->addMinutes(5),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'OTP verified successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {

            $otpRecord = DB::table('otps')
                ->where('email', $request->email)
                ->where('expires_at', '>', \Carbon\Carbon::now())
                ->first();

            if (!$otpRecord) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized or expired password reset token session.'
                ], 403);
            }

            // Update user details
            $user = User::where('email', $request->email)->firstOrFail();
            $user->update([
                'password' => Hash::make($request->password)
            ]);


            DB::table('otps')->where('email', $request->email)->delete();



            return response()->json([
                'status' => true,
                'message' => 'Your password has been successfully updated!'
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['status' => true, 'message' => 'logout successfully']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }
}
