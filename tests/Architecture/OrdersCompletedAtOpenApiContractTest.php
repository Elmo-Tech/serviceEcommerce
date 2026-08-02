<?php

declare(strict_types=1);

it('keeps completedAt read-only on authoritative admin order schemas', function (): void {
    $document = file_get_contents(base_path('specs/005-orders-management/contracts/openapi.yaml'));

    expect($document)->toBeString()
        ->and($document)->toContain("    CompletedAt:\n", '      readOnly: true')
        ->and($document)->toMatch('/OrderDetail:.*?required:.*?- completedAt.*?properties:.*?completedAt:\R\s+\$ref: \'#\/components\/schemas\/CompletedAt\'/s')
        ->and($document)->toMatch('/OrderIndexRow:.*?required:.*?- completedAt.*?properties:.*?completedAt:\R\s+\$ref: \'#\/components\/schemas\/CompletedAt\'/s');

    foreach ([
        'PublicCreateOrderRequest',
        'AdminCreateOrderRequest',
        'AdminUpdateOrderRequest',
        'OrderStatusRequest',
        'OrderPaymentRequest',
        'CreateOrderItemRequest',
        'AdminOrderItemUpdateRequest',
        'AttachmentUploadRequest',
    ] as $schema) {
        preg_match(
            '/^    '.preg_quote($schema, '/').":\R(?<block>(?:(?!^    [A-Za-z][A-Za-z0-9]*:).*(?:\R|$))*)/m",
            $document,
            $matches,
        );

        expect($matches)->toHaveKey('block')
            ->and((string) $matches['block'])->not->toContain('completedAt:')
            ->and((string) $matches['block'])->toContain('additionalProperties: false');
    }
});
