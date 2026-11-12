<?php

namespace App\Providers;

use App\Models\Accomplishment;
use App\Models\Allotment;
use App\Models\AllotmentEntry;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Office;
use App\Models\OutcomeArea;
use App\Models\PerformanceIndicator;
use App\Models\Program;
use App\Models\SystemSetting;
use App\Models\Target;
use App\Models\Uacs;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Policies\AccomplishmentPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Accomplishment::class, AccomplishmentPolicy::class);

        foreach ([User::class, FiscalYear::class, Target::class, OutcomeArea::class, Program::class, PerformanceIndicator::class, Accomplishment::class, Allotment::class, Expense::class, AllotmentEntry::class, Office::class, Uacs::class, Role::class, Permission::class, SystemSetting::class] as $model) {
            $model::observe(ActivityObserver::class);
        }
    }
}
