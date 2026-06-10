<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminInvite extends Command
{
    protected $signature = 'admin:invite {email : Email address of the user to invite} {--role=admin : Role to assign (admin or cfo)}';

    protected $description = 'Generate a one-time setup invite link for a new user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $role  = (string) $this->option('role');

        $validator = Validator::make(
            ['email' => $email, 'role' => $role],
            ['email' => ['required', 'email:rfc'], 'role' => ['required', 'in:admin,cfo']]
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            return self::INVALID;
        }

        $token = Str::random(64);
        $now   = Carbon::now();

        DB::table('admin_invites')->insert([
            'email'      => $email,
            'role'       => $role,
            'token'      => $token,
            'expires_at' => $now->copy()->addHours(24),
            'used'       => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $base = rtrim((string) config('app.url'), '/');
        $this->line('Setup URL: ' . $base . '/setup?token=' . $token);
        $this->line('Role: ' . $role);
        $this->line('Valid for 24 hours.');

        return self::SUCCESS;
    }
}
