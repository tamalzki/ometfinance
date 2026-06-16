<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        /**
         * Gate: only admin-level users may add/edit/delete financial records
         * (account entries, transfers, project inflows/outflows) and view
         * account balances.
         *
         * Today every authenticated user is an admin, so the gate just
         * confirms authentication. When a real `role` column is added to the
         * users table, change the body to:
         *
         *     return $user->role === 'admin';
         */
        Gate::define('manage-financials', function ($user): bool {
            if (! $user) {
                return false;
            }
            // Future role hook: prefer an explicit 'admin' role when present.
            if (property_exists($user, 'role') || isset($user->role)) {
                return in_array($user->role, [null, 'admin', 'cfo'], true);
            }
            return true;
        });

        Gate::define('view-financials', function ($user): bool {
            // Same policy as manage for now — viewing balances is restricted
            // to admins. Tighten or split later when more roles exist.
            return $user !== null;
        });
    }
}
