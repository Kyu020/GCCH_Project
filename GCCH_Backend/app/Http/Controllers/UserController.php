<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Models\Applicant;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class UserController extends Controller
{   
    public function redirectToGoogle(Request $request)
    {
        return Socialite::driver('google')->stateless()->with(['prompt' => 'consent'])->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::firstOrCreate(
            ['google_id' => $googleUser->id],
            [
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'password' => Hash::make(uniqid()),
                'email_verified_at' => now(),
            ]
        );

        // Registration logic
        if (empty($user->role)) {
            Auth::login($user, true);
            return redirect('http://localhost:5173/redirecting');
        }

        //Login Logic
        Auth::login($user, true);
        $token = $user->createToken('auth_token')->plainTextToken;
        
        $redirectUrl = match ($user->role) {
            'applicant' => '/applicantdash',
            'company' => '/companydash',
            default => null,
        };

        if ($redirectUrl) {
            return redirect('http://localhost:5173/redirecting');
        }

        return response()->json(['error' => 'Unexpected role value'], 422);
    }

    public function selectRole($userId){
        $user = User::findOrFail($userId);
        return response()->json([
            'message' => 'Select a role',
            'user' => $user,
        ]);
    }

    public function setRole(Request $request, $userId)
    {
        $request->validate([
            'role' => 'required|in:applicant,company',
        ]);

        $user = User::findOrFail($userId);

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'message' => 'Role set successfully',
            'user' => $user,
        ]);
    }


    public function completeApplicantProfile(Request $request, User $user){
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'phone_number' => ['required', 'string', 'max:15', 'regex:/^[\d\s]+$/'],
            'course' => 'required|in:BSIT,BSCS,BSEMC,BSN,BSM,BSA,BSBA-FM,BSBA-HRM,BSBA-MM,BSCA,BSHM,BSTM,BAComm,BECEd,BCAEd,BPEd,BEED,BSEd-Eng,BSEd-Math,BSEd-Fil,BSEd-SS,BSEd-Sci,Other',
        ]);

        try{
            Applicant::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'phone_number' => $request->phone_number,
                'course' => $request->course,
            ]);
    
            return response()->json([
                'message' => 'Applicant profile completed successfully',
                'user' => $user,
            ]);
        }catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()], 422);
        }
    }

    public function completeCompanyProfile(Request $request, User $user){
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_telephone' => 'required|string|max:15',
            'street_address' => 'required|string|max:255',
            'city' => 'required|string|max:30',
            'province' => 'required|string|max:30',
            'country' => 'required|string|max:30',
            'industry_type' => 'required|string|max:255',
        ]);

        try{
            Company::create([
                'user_id' => $user->id,
                'company_name' => $request->company_name,
                'company_telephone' => $request->company_telephone,
                'street_address' => $request->street_address,
                'city' => $request->city,
                'province' => $request->province,
                'country' => $request->country,
                'industry_type' => $request->industry_type,
            ]);
    
            return response()->json([
                'message' => 'Company profile completed successfully',
                'user' => $user,
            ]);
            
        }catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()], 422);
        }
    }

    public function showApplicantProfileForm(User $user) {
        return view('auth.applicant-profile', compact('user'));
    }
    
    public function showCompanyProfileForm(User $user) {
        return view('auth.company-profile', compact('user'));
    }
    
    public function logout(Request $request)
{
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json([
        'message' => 'Logged out successfully',
    ]);
}

}
