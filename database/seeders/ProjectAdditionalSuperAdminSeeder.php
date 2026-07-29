<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProjectAdditionalSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::query()->firstOrNew([
            'email' => 'elmo@gmail.com',
        ]);

        if (! $user->exists) {
            $user->name = 'Elmo Super Admin';
            $user->password = Hash::make('elmo123456');
        }

        $user->forceFill([
            'type' => UserType::ADMIN,
            'is_active' => true,
        ])->save();

        $user->syncRoles(['super-admin']);
    }
}
