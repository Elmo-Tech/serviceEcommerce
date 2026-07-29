<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Rules\Auth\AdminPasswordRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = (string) env('SUPER_ADMIN_PASSWORD', '');
        $seedUsers = array_merge(
            [$this->makeSeedUser(
                (string) env('SUPER_ADMIN_NAME', ''),
                (string) env('SUPER_ADMIN_EMAIL', ''),
                $defaultPassword,
            )],
            $this->additionalSeedUsers($defaultPassword),
        );

        $this->validateConfiguration($seedUsers);
        $this->call(RolesAndPermissionsSeeder::class);

        foreach ($seedUsers as $seedUser) {
            $user = User::query()->firstOrNew([
                'email' => $seedUser['email'],
            ]);

            if (! $user->exists) {
                $user->name = $seedUser['name'];
                $user->password = Hash::make($seedUser['password']);
            }

            $user->forceFill([
                'type' => UserType::ADMIN,
                'is_active' => true,
            ])->save();

            $user->syncRoles(['super-admin']);
        }
    }

    /**
     * @return array<int, array{name: string, email: string, password: string}>
     */
    private function additionalSeedUsers(string $defaultPassword): array
    {
        $rawAdditionalUsers = trim((string) env('SUPER_ADMIN_ADDITIONAL_USERS', ''));

        if ($rawAdditionalUsers === '') {
            return [];
        }

        $decoded = json_decode($rawAdditionalUsers, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Super Admin seeding configuration is missing or invalid.');
        }

        $additionalUsers = [];

        foreach ($decoded as $seedUser) {
            if (! is_array($seedUser)) {
                throw new InvalidArgumentException('Super Admin seeding configuration is missing or invalid.');
            }

            $additionalUsers[] = $this->makeSeedUser(
                (string) ($seedUser['name'] ?? ''),
                (string) ($seedUser['email'] ?? ''),
                (string) ($seedUser['password'] ?? $defaultPassword),
            );
        }

        return $additionalUsers;
    }

    /**
     * @return array{name: string, email: string, password: string}
     */
    private function makeSeedUser(string $name, string $email, string $password): array
    {
        return [
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
        ];
    }

    /**
     * @param  array<int, array{name: string, email: string, password: string}>  $seedUsers
     */
    private function validateConfiguration(array $seedUsers): void
    {
        foreach ($seedUsers as $seedUser) {
            $validator = Validator::make($seedUser, [
                'name' => ['required', 'string', 'min:1', 'max:150'],
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
                'password' => AdminPasswordRules::ruleSet(),
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException('Super Admin seeding configuration is missing or invalid.');
            }
        }
    }
}
