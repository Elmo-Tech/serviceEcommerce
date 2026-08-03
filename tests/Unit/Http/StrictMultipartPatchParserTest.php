<?php

declare(strict_types=1);

use App\Services\Http\StrictMultipartPatchParser;
use Illuminate\Validation\ValidationException;

function heroMultipartBody(string $boundary, array $parts): string
{
    $body = '';

    foreach ($parts as $part) {
        $body .= '--'.$boundary."\r\n";
        $body .= 'Content-Disposition: form-data; name="'.$part['name'].'"';

        if (isset($part['filename'])) {
            $body .= '; filename="'.$part['filename'].'"'."\r\n";
            $body .= 'Content-Type: '.($part['type'] ?? 'application/octet-stream')."\r\n";
        } else {
            $body .= "\r\n";
        }

        $body .= "\r\n".$part['value']."\r\n";
    }

    return $body.'--'.$boundary."--\r\n";
}

it('strictly parses approved scalar and one file parts and exposes a managed upload', function () {
    $boundary = 'feature008Boundary';
    $body = heroMultipartBody($boundary, [
        ['name' => 'titleEn', 'value' => 'Updated title'],
        ['name' => 'isActive', 'value' => '0'],
        ['name' => 'image', 'filename' => 'hero.png', 'type' => 'image/png', 'value' => "\x89PNG\r\n"],
    ]);

    $result = app(StrictMultipartPatchParser::class)->parse('multipart/form-data; boundary='.$boundary, $body);

    expect($result['fields'])->toBe(['titleEn' => 'Updated title', 'isActive' => '0'])
        ->and($result['file']?->getClientOriginalName())->toBe('hero.png')
        ->and(is_string($result['tempPath']) && is_file($result['tempPath']))->toBeTrue();

    @unlink((string) $result['tempPath']);
});

it('preserves boundary-like bytes inside file content', function () {
    $boundary = 'feature008Boundary';
    $contents = "binary--{$boundary}like-content";
    $body = heroMultipartBody($boundary, [
        ['name' => 'image', 'filename' => 'hero.png', 'type' => 'image/png', 'value' => $contents],
    ]);

    $result = app(StrictMultipartPatchParser::class)->parse('multipart/form-data; boundary='.$boundary, $body);

    expect(file_get_contents((string) $result['tempPath']))->toBe($contents);
    @unlink((string) $result['tempPath']);
});

it('accepts an exact five MiB file and rejects one byte more', function () {
    $parser = app(StrictMultipartPatchParser::class);
    $boundary = 'feature008SizeBoundary';
    $exact = heroMultipartBody($boundary, [[
        'name' => 'image', 'filename' => 'hero.png', 'type' => 'image/png',
        'value' => str_repeat('a', StrictMultipartPatchParser::MAX_FILE_BYTES),
    ]]);
    $result = $parser->parse('multipart/form-data; boundary='.$boundary, $exact);
    expect(filesize((string) $result['tempPath']))->toBe(StrictMultipartPatchParser::MAX_FILE_BYTES);
    @unlink((string) $result['tempPath']);

    $tooLarge = heroMultipartBody($boundary, [[
        'name' => 'image', 'filename' => 'hero.png', 'type' => 'image/png',
        'value' => str_repeat('a', StrictMultipartPatchParser::MAX_FILE_BYTES + 1),
    ]]);
    expect(fn () => $parser->parse('multipart/form-data; boundary='.$boundary, $tooLarge))
        ->toThrow(ValidationException::class);
});

it('cleans the managed temporary file when a later part is invalid', function () {
    $before = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'hero-slide-*') ?: [];
    $boundary = 'feature008CleanupBoundary';
    $body = heroMultipartBody($boundary, [
        ['name' => 'image', 'filename' => 'hero.png', 'type' => 'image/png', 'value' => 'bytes'],
        ['name' => 'unknown', 'value' => 'bad'],
    ]);

    expect(fn () => app(StrictMultipartPatchParser::class)->parse('multipart/form-data; boundary='.$boundary, $body))
        ->toThrow(ValidationException::class);

    expect(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'hero-slide-*') ?: [])->toBe($before);
});

it('rejects malformed empty duplicate unknown and oversized multipart payloads', function (string $contentType, string $body) {
    expect(fn () => app(StrictMultipartPatchParser::class)->parse($contentType, $body))
        ->toThrow(ValidationException::class);
})->with([
    'missing boundary' => ['multipart/form-data', ''],
    'empty' => ['multipart/form-data; boundary=x', '--x--\r\n'],
    'missing close' => ['multipart/form-data; boundary=x', '--x\r\nContent-Disposition: form-data; name="titleEn"\r\n\r\nA\r\n'],
    'duplicate scalar' => ['multipart/form-data; boundary=x', heroMultipartBody('x', [
        ['name' => 'titleEn', 'value' => 'A'],
        ['name' => 'titleEn', 'value' => 'B'],
    ])],
    'unknown' => ['multipart/form-data; boundary=x', heroMultipartBody('x', [
        ['name' => 'unknown', 'value' => 'A'],
    ])],
    'duplicate image' => ['multipart/form-data; boundary=x', heroMultipartBody('x', [
        ['name' => 'image', 'filename' => 'a.png', 'type' => 'image/png', 'value' => 'a'],
        ['name' => 'image', 'filename' => 'b.png', 'type' => 'image/png', 'value' => 'b'],
    ])],
    'header injection' => ['multipart/form-data; boundary=x', "--x\r\nContent-Disposition: form-data; name=\"titleEn\"\r\nInjected\r\n\r\nA\r\n--x--\r\n"],
    'raw body overhead' => ['multipart/form-data; boundary=x', str_repeat('a', StrictMultipartPatchParser::MAX_FILE_BYTES + StrictMultipartPatchParser::MAX_OVERHEAD_BYTES + 1)],
    'oversized file' => ['multipart/form-data; boundary=x', heroMultipartBody('x', [
        ['name' => 'image', 'filename' => 'a.png', 'type' => 'image/png', 'value' => str_repeat('a', StrictMultipartPatchParser::MAX_FILE_BYTES + 1)],
    ])],
]);
