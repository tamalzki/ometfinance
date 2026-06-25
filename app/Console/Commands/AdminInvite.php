<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminInvite extends Command
{
    protected $signature = 'admin:invite
        {email : Email address of the user to invite}
        {--role=admin : Role to assign (admin, cfo, or accounting)}
        {--source= : Office to lock the invite to (mindanao or bgc) — required when --role=accounting}';

    protected $description = 'Generate a one-time setup invite link for a new user';

    public function handle(): int
    {
        $email  = (string) $this->argument('email');
        $role   = (string) $this->option('role');
        $source = $this->option('source') ? (string) $this->option('source') : null;

        $validator = Validator::make(
            ['email' => $email, 'role' => $role, 'source' => $source],
            [
                'email'  => ['required', 'email:rfc'],
                'role'   => ['required', 'in:admin,cfo,accounting'],
                'source' => [$role === 'accounting' ? 'required' : 'nullable', 'in:mindanao,bgc'],
            ],
            [
                'source.required' => 'An accounting invite needs --source=mindanao or --source=bgc — they are locked to one office.',
            ]
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
            'source'     => $role === 'accounting' ? $source : null,
            'token'      => $token,
            'expires_at' => $now->copy()->addHours(24),
            'used'       => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $base = rtrim((string) config('app.url'), '/');
        $this->line('Setup URL: ' . $base . '/setup?token=' . $token);
        $this->line('Role: ' . $role);
        if ($role === 'accounting') {
            $this->line('Office: ' . ($source === 'bgc' ? 'BGC' : 'Main'));
        }
        $this->line('Valid for 24 hours.');

        return self::SUCCESS;
    }
}
