<?php

declare(strict_types=1);

use App\Actions\HeroSlides\CreateHeroSlideAction;
use App\Actions\HeroSlides\DeleteHeroSlideAction;
use App\Actions\HeroSlides\UpdateHeroSlideAction;
use App\Exceptions\ApiBusinessException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mode = $argv[1] ?? '';

try {
    $result = match ($mode) {
        'create' => (function () use ($app, $argv): array {
            $imagePath = tempnam(sys_get_temp_dir(), 'hero-concurrency-');
            $source = UploadedFile::fake()->image('hero.png');
            file_put_contents($imagePath, (string) file_get_contents($source->getRealPath()));
            $payload = [
                'titleAr' => 'عنوان متزامن', 'titleEn' => 'Concurrent title',
                'descriptionAr' => 'وصف متزامن', 'descriptionEn' => 'Concurrent description',
                'image' => new UploadedFile($imagePath, 'hero.png', 'image/png', UPLOAD_ERR_OK, true),
                'isActive' => 1,
            ];
            if (isset($argv[2]) && $argv[2] !== '') {
                $payload['position'] = (int) $argv[2];
            }

            try {
                $slide = $app->make(CreateHeroSlideAction::class)->execute($payload);

                return ['status' => 'success', 'id' => $slide->getKey(), 'position' => $slide->position];
            } finally {
                @unlink($imagePath);
            }
        })(),
        'move' => (function () use ($app, $argv): array {
            $slide = $app->make(UpdateHeroSlideAction::class)->execute((int) $argv[2], ['position' => (int) $argv[3]]);

            return ['status' => 'success', 'id' => $slide->getKey(), 'position' => $slide->position];
        })(),
        'replace' => (function () use ($app, $argv): array {
            $imagePath = tempnam(sys_get_temp_dir(), 'hero-concurrency-');
            $source = UploadedFile::fake()->image('replacement.png');
            file_put_contents($imagePath, (string) file_get_contents($source->getRealPath()));

            try {
                $slide = $app->make(UpdateHeroSlideAction::class)->execute((int) $argv[2], [
                    'image' => new UploadedFile($imagePath, 'replacement.png', 'image/png', UPLOAD_ERR_OK, true),
                ]);

                return ['status' => 'success', 'id' => $slide->getKey(), 'imagePath' => $slide->image_path];
            } finally {
                @unlink($imagePath);
            }
        })(),
        'delete' => (function () use ($app, $argv): array {
            $app->make(DeleteHeroSlideAction::class)->execute((int) $argv[2]);

            return ['status' => 'success', 'id' => (int) $argv[2]];
        })(),
        default => throw new InvalidArgumentException('Unknown Hero concurrency mode.'),
    };

    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
} catch (ApiBusinessException $exception) {
    echo json_encode([
        'status' => 'business_error',
        'code' => $exception->machineCode(),
        'httpStatus' => $exception->status()->value,
    ], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable::class.': '.$throwable->getMessage());
    exit(1);
}
