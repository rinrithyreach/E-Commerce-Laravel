<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $reqeust){
        try{
            $user = User::create([
                'name' => $reqeust->name,
                'email' => $reqeust->email,
                'password' => Hash::make($reqeust->password),
            ]);

            // assign role using Spatie Role
            $roleToAssign = $reqeust->input('role','customer');
            $user->assignRole($roleToAssign);

            //Fire the registration event to send email

            event(new Registered($user));

            return response()->json([
                'status' => true,
                'message' =>  'register successfully',
               'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(), // Pulls assigned name from Spatie
                'is_verified' => false
            ]
            ],201);

        }catch(Exception $e){
            return response()->json(['status'=>false,'message' => $e->getMessage()]);
        }
    }

    public function login(LoginRequest $request){
        try{

        $user = User::where('email',$request->email)->first();

        if(!$user || !Hash::check($request->password, $user->password)){
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our system records.'],
            ]);
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
                'is_verified' => $user->hasVerifiedEmail()
            ]
        ],200);

        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()]);
        }
    }

    public function verify(Request $request , $id, $hash){
        if(!$request->hasValidSignature()){
            return response()->json(
                [
                'status' => 'error',
                'message' => 'The verification link has expired or contains a compromised signature.'
            ], 403);
        }

        $user = User::findOrFail($id);
        if(!hash_equals((string) $hash , sha1($user->getEmailForVerification()))){
            return response()->json([
                'status' => 'error',
                'message' => 'Tampering detected in security signature tokens.'
            ], 403);
        }

        if($user->hasVerifiedEmail()){
            return response()->json([
                'status' => 'success',
                'message' => 'This email address has already been verified.'
            ], 200);
        }

        if($user->markEmailAsVerified()){
            event(new Verified($user));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully! You can now log in and explore the marketplace.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_verified' => true
            ]
        ], 200);
    }

    public function logout(Request $request){
        try{
            $request->user()->currentAccessToken()->delete();
            return response()->json(['status'=>true,'message'=> 'logout successfully']);
        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()]);
        }
    }
}
