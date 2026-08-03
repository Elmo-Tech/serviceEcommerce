<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\HeroSlide;
use App\Models\User;
use Database\Seeders\HeroSlidesPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => HeroSlidesPermissionsSeeder::class, '--force' => true]);

$admin = User::query()->create([
    'name' => 'Hero Smoke Admin',
    'email' => 'hero-smoke@example.test',
    'password' => Hash::make(bin2hex(random_bytes(16))),
    'type' => UserType::ADMIN,
    'is_active' => true,
]);
$admin->givePermissionTo('hero-slides.update');

Storage::disk('public')->put('hero-slides/smoke-old.png', 'old');
$slide = HeroSlide::factory()->atPosition(1)->create(['image_path' => 'hero-slides/smoke-old.png']);

$tempImage = tempnam(sys_get_temp_dir(), 'hero-smoke-image-');
$image = imagecreatetruecolor(4, 4);
imagepng($image, $tempImage);
imagedestroy($image);

$oversized = tempnam(sys_get_temp_dir(), 'hero-smoke-oversized-');
$contents = (string) file_get_contents($tempImage);
file_put_contents($oversized, $contents.str_repeat("\0", (5 * 1024 * 1024 + 1) - strlen($contents)));

echo json_encode([
    'token' => $admin->createToken('hero-http-smoke')->plainTextToken,
    'slideId' => $slide->getKey(),
    'image' => $tempImage,
    'oversized' => $oversized,
], JSON_THROW_ON_ERROR);
