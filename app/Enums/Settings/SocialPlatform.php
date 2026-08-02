<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum SocialPlatform: int
{
    case FACEBOOK = 0;
    case INSTAGRAM = 1;
    case LINKEDIN = 2;
    case YOUTUBE = 3;
    case TIKTOK = 4;
    case X = 5;
    case TELEGRAM = 6;
    case PINTEREST = 7;
    case SNAPCHAT = 8;

    public function key(): string
    {
        return match ($this) {
            self::FACEBOOK => 'facebook',
            self::INSTAGRAM => 'instagram',
            self::LINKEDIN => 'linkedin',
            self::YOUTUBE => 'youtube',
            self::TIKTOK => 'tiktok',
            self::X => 'x',
            self::TELEGRAM => 'telegram',
            self::PINTEREST => 'pinterest',
            self::SNAPCHAT => 'snapchat',
        };
    }

    public static function fromKey(string $key): ?self
    {
        return match (mb_strtolower(trim($key))) {
            'facebook' => self::FACEBOOK,
            'instagram' => self::INSTAGRAM,
            'linkedin' => self::LINKEDIN,
            'youtube' => self::YOUTUBE,
            'tiktok' => self::TIKTOK,
            'x' => self::X,
            'telegram' => self::TELEGRAM,
            'pinterest' => self::PINTEREST,
            'snapchat' => self::SNAPCHAT,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            static fn (self $platform): string => $platform->key(),
            self::cases(),
        );
    }
}
