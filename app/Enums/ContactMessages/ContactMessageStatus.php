<?php

declare(strict_types=1);

namespace App\Enums\ContactMessages;

enum ContactMessageStatus: int
{
    case NEW = 0;
    case READ = 1;

    public function key(): string
    {
        return match ($this) {
            self::NEW => 'new',
            self::READ => 'read',
        };
    }

    public static function fromKey(string $key): ?self
    {
        return match ($key) {
            'new' => self::NEW,
            'read' => self::READ,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            static fn (self $status): string => $status->key(),
            self::cases(),
        );
    }
}
