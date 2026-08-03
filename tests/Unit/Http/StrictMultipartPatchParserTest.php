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
    'oversized file' => ['multipart/form-data; boundary=x', heroMultipartBody('x', [
        ['name' => 'image', 'filename' => 'a.png', 'type' => 'image/png', 'value' => str_repeat('a', StrictMultipartPatchParser::MAX_FILE_BYTES + 1)],
    ])],
]);
