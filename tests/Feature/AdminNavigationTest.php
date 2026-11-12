<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('RICTU');
    $this->actingAs($this->administrator);
});

it('orders admin navigation groups and their items', function () {
    $navigation = collect(Filament::getCurrentPanel()->getNavigation());
    $groups = $navigation->filter(fn ($group): bool => filled($group->getLabel()))->values();

    expect($groups->map(fn ($group) => $group->getLabel())->all())
        ->toBe(['System Management', 'User Management']);
    expect(collect($groups[0]->getItems())->map(fn ($item) => $item->getLabel())->all())
        ->toBe(['System Management', 'Fiscal Years', 'UACS', 'Offices']);
    expect(collect($groups[1]->getItems())->map(fn ($item) => $item->getLabel())->all())
        ->toBe(['Users', 'Roles', 'Permission', 'Activity Logs']);

    $dashboard = $navigation->first(fn ($group): bool => blank($group->getLabel()));
    expect(collect($dashboard->getItems())->map(fn ($item) => $item->getLabel())->all())->toContain('Dashboard');
    expect(collect($groups[0]->getItems())->first()->getUrl())->toContain('/admin/system-settings');
});

it('hides grouped navigation items when their view permission is revoked', function () {
    $this->administrator->roles()->firstOrFail()->revokePermissionTo('uacs.view');
    $this->administrator->unsetRelation('roles');

    $group = collect(Filament::getCurrentPanel()->getNavigation())
        ->first(fn ($group): bool => $group->getLabel() === 'System Management');

    expect(collect($group->getItems())->map(fn ($item) => $item->getLabel())->all())
        ->toBe(['System Management', 'Fiscal Years', 'Offices']);
    $this->get('/admin/uacs')->assertForbidden();
});
