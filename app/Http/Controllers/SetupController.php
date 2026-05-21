<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function show(Request $request)
    {
        $invite = $this->resolveInvite($request->query('token'));

        if (! $invite) {
            return response()->view('setup.invalid', [], 410);
        }

        return view('setup.index', [
            'invite' => $invite,
            'token'  => $invite->token,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'token'    => ['required', 'string', 'size:64'],
            'name'     => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $invite = $this->resolveInvite($data['token']);

        if (! $invite) {
            return response()->view('setup.invalid', [], 410);
        }

        $user = DB::transaction(function () use ($invite, $data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $invite->email,
                'password' => Hash::make($data['password']),
            ]);

            DB::table('admin_invites')
                ->where('id', $invite->id)
                ->update([
                    'used'       => true,
                    'updated_at' => Carbon::now(),
                ]);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    private function resolveInvite(?string $token)
    {
        if (! $token) {
            return null;
        }

        $invite = DB::table('admin_invites')->where('token', $token)->first();

        if (! $invite || $invite->used || Carbon::parse($invite->expires_at)->isPast()) {
            return null;
        }

        return $invite;
    }
}
