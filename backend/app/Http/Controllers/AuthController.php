<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:60',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'gender' => 'required|in:male,female',
        ]);
        $user = User::create($d);
        return ['token' => $user->createToken('app')->plainTextToken, 'user' => $this->payload($user)];
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::validate($d)) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }
        $user = User::where('email', $d['email'])->first();
        return ['token' => $user->createToken('app')->plainTextToken, 'user' => $this->payload($user)];
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->noContent();
    }

    public function me(Request $r) { return $this->payload($r->user()); }

    public function update(Request $r)
    {
        $d = $r->validate([
            'name' => 'sometimes|string|max:60',
            'gender' => 'sometimes|in:male,female',
            'identity_override' => 'sometimes|nullable|in:male,female',
        ]);
        $r->user()->update($d);
        return $this->payload($r->user()->fresh());
    }

    private function payload(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'gender' => $u->gender,
            'identity_override' => $u->identity_override, 'household_id' => $u->householdId(),
        ];
    }
}
