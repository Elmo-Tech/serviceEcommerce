<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('parses the orders openapi contract, resolves internal refs, and keeps exactly the approved seventeen operation ids', function () {
    $openApiPath = base_path('specs/005-orders-management/contracts/openapi.yaml');

    $expectedOperationIds = [
        'createPublicOrder',
        'listAdminOrders',
        'createAdminOrder',
        'showAdminOrder',
        'updateAdminOrder',
        'deleteAdminOrder',
        'changeAdminOrderStatus',
        'showAdminOrderPayment',
        'updateAdminOrderPayment',
        'listAdminOrderItems',
        'createAdminOrderItem',
        'showAdminOrderItem',
        'updateAdminOrderItem',
        'deleteAdminOrderItem',
        'uploadAdminOrderItemAttachments',
        'deleteAdminOrderItemAttachment',
        'downloadAdminOrderItemAttachment',
    ];

    $pythonScript = <<<'PY'
import json
import sys
import yaml

path = sys.argv[1]

with open(path, 'r', encoding='utf-8') as handle:
    document = yaml.safe_load(handle)

operation_ids = []
refs = []

def walk(node):
    if isinstance(node, dict):
        for key, value in node.items():
            if key == 'operationId' and isinstance(value, str):
                operation_ids.append(value)
            if key == '$ref' and isinstance(value, str) and value.startswith('#/'):
                refs.append(value)
            walk(value)
    elif isinstance(node, list):
        for item in node:
            walk(item)

def resolve_pointer(document, ref):
    node = document
    for part in ref[2:].split('/'):
        part = part.replace('~1', '/').replace('~0', '~')
        if isinstance(node, dict) and part in node:
            node = node[part]
        else:
            return False
    return True

walk(document)

unresolved = [ref for ref in refs if not resolve_pointer(document, ref)]

print(json.dumps({
    'operationIds': operation_ids,
    'uniqueOperationIds': sorted(set(operation_ids)),
    'refCount': len(refs),
    'unresolvedRefs': unresolved,
}, ensure_ascii=False))
PY;

    $process = new Process([
        'python',
        '-c',
        $pythonScript,
        $openApiPath,
    ]);

    $process->run();

    expect($process->isSuccessful())->toBeTrue();

    $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    expect($result['unresolvedRefs'])->toBeEmpty()
        ->and($result['operationIds'])->toHaveCount(17)
        ->and($result['uniqueOperationIds'])->toHaveCount(17)
        ->and($result['uniqueOperationIds'])->toBe(collect($expectedOperationIds)->sort()->values()->all())
        ->and($result['refCount'])->toBeGreaterThan(0);
});

it('ships postman folders for the complete orders feature with approved operations, urls, and key query or header examples', function () {
    $collection = json_decode(
        file_get_contents(base_path('postman/Service-Commerce.postman_collection.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $topLevelFolders = collect($collection['item'] ?? []);

    $publicOrdersFolder = $topLevelFolders->firstWhere('name', 'Public Orders');
    $adminOrdersFolder = $topLevelFolders->firstWhere('name', 'Admin Orders');
    $adminOrderItemsFolder = $topLevelFolders->firstWhere('name', 'Admin Order Items');
    $adminOrderAttachmentsFolder = $topLevelFolders->firstWhere('name', 'Admin Order Attachments');

    $publicOrderOperationNames = collect($publicOrdersFolder['item'] ?? [])->pluck('name')->all();
    $adminOrderOperationNames = collect($adminOrdersFolder['item'] ?? [])->pluck('name')->all();
    $adminOrderItemOperationNames = collect($adminOrderItemsFolder['item'] ?? [])->pluck('name')->all();
    $adminOrderAttachmentOperationNames = collect($adminOrderAttachmentsFolder['item'] ?? [])->pluck('name')->all();

    $createPublicOrder = collect($publicOrdersFolder['item'] ?? [])->firstWhere('name', 'Create Public Order');
    $listOrders = collect($adminOrdersFolder['item'] ?? [])->firstWhere('name', 'List Orders');
    $changeOrderStatus = collect($adminOrdersFolder['item'] ?? [])->firstWhere('name', 'Change Order Status');
    $createOrderItem = collect($adminOrderItemsFolder['item'] ?? [])->firstWhere('name', 'Create Order Item');
    $createOrderItemKeys = collect(data_get($createOrderItem, 'request.body.formdata', []))->pluck('key');
    $downloadAttachment = collect($adminOrderAttachmentsFolder['item'] ?? [])->firstWhere('name', 'Download Order Item Attachment');

    expect($publicOrdersFolder)->toBeArray()
        ->and($adminOrdersFolder)->toBeArray()
        ->and($adminOrderItemsFolder)->toBeArray()
        ->and($adminOrderAttachmentsFolder)->toBeArray()
        ->and($publicOrderOperationNames)->toBe([
            'Create Public Order',
        ])
        ->and($adminOrderOperationNames)->toBe([
            'List Orders',
            'Create Admin Order',
            'Show Order',
            'Update Order',
            'Delete Order',
            'Change Order Status',
            'Show Order Payment',
            'Update Order Payment',
        ])
        ->and($adminOrderItemOperationNames)->toBe([
            'List Order Items',
            'Create Order Item',
            'Show Order Item',
            'Update Order Item',
            'Delete Order Item',
        ])
        ->and($adminOrderAttachmentOperationNames)->toBe([
            'Upload Order Item Attachments',
            'Delete Order Item Attachment',
            'Download Order Item Attachment',
        ])
        ->and(collect(data_get($createPublicOrder, 'request.header', []))->pluck('key')->contains('Idempotency-Key'))->toBeTrue()
        ->and((string) data_get($createPublicOrder, 'request.url'))->toBe('{{baseUrl}}/public/orders')
        ->and((string) data_get($listOrders, 'request.url'))->toContain('{{baseUrl}}/admin/orders?filter[status]=0')
        ->and((string) data_get($listOrders, 'request.url'))->toContain('filter[paymentStatus]=0')
        ->and((string) data_get($listOrders, 'request.url'))->toContain('sort=-createdAt')
        ->and((string) data_get($changeOrderStatus, 'request.url'))->toBe('{{baseUrl}}/admin/orders/1/status')
        ->and((string) data_get($changeOrderStatus, 'description'))->toContain('status enum meanings')
        ->and((string) data_get($createOrderItem, 'request.body.mode'))->toBe('formdata')
        ->and($createOrderItemKeys)->toContain('answers[0][orderFieldId]')
        ->and($createOrderItemKeys)->toContain('attachments[0]')
        ->and((string) data_get($downloadAttachment, 'request.url'))->toBe('{{baseUrl}}/admin/orders/1/items/1/attachments/1/download');
});
