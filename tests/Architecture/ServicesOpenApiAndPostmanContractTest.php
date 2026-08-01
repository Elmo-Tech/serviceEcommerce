<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('parses the services openapi contract, resolves internal refs, and keeps exactly the approved twenty-eight operation ids', function () {
    $openApiPath = base_path('specs/004-services-catalog/contracts/openapi.yaml');
    $categoryOpenApi = file_get_contents(base_path('specs/003-categories-subcategories/contracts/openapi.yaml'));

    $expectedOperationIds = [
        'adminListServices',
        'adminCreateService',
        'adminShowService',
        'adminUpdateService',
        'adminDeleteService',
        'adminRestoreService',
        'adminListServiceSpecifications',
        'adminCreateServiceSpecification',
        'adminShowServiceSpecification',
        'adminUpdateServiceSpecification',
        'adminDeleteServiceSpecification',
        'adminListServiceOrderFields',
        'adminCreateServiceOrderField',
        'adminShowServiceOrderField',
        'adminUpdateServiceOrderField',
        'adminDeleteServiceOrderField',
        'adminListServicePricingOptions',
        'adminCreateServicePricingOption',
        'adminShowServicePricingOption',
        'adminUpdateServicePricingOption',
        'adminDeleteServicePricingOption',
        'adminListServiceMedia',
        'adminUploadServiceMedia',
        'adminUpdateServiceMediaAltText',
        'adminDeleteServiceMedia',
        'adminSetServiceMediaAsMain',
        'publicListServices',
        'publicShowService',
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
        ->and($result['operationIds'])->toHaveCount(28)
        ->and($result['uniqueOperationIds'])->toHaveCount(28)
        ->and($result['uniqueOperationIds'])->toBe(collect($expectedOperationIds)->sort()->values()->all())
        ->and($result['refCount'])->toBeGreaterThan(0)
        ->and($categoryOpenApi)->toContain('CATEGORY_HAS_SERVICES')
        ->and($categoryOpenApi)->toContain('SUBCATEGORY_HAS_SERVICES');
});

it('ships postman folders for the complete services feature with approved request shapes and no public sort parameter', function () {
    $collection = json_decode(
        file_get_contents(base_path('postman/Service-Commerce.postman_collection.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $topLevelFolders = collect($collection['item'] ?? []);

    $adminServicesFolder = $topLevelFolders->firstWhere('name', 'Admin Services');
    $publicServicesFolder = $topLevelFolders->firstWhere('name', 'Public Services');
    $adminCategoriesFolder = $topLevelFolders->firstWhere('name', 'Admin Categories');

    $adminServiceOperationNames = collect($adminServicesFolder['item'] ?? [])->pluck('name')->all();
    $publicServiceOperationNames = collect($publicServicesFolder['item'] ?? [])->pluck('name')->all();

    $listAdminServices = collect($adminServicesFolder['item'] ?? [])->firstWhere('name', 'List Services');
    $createService = collect($adminServicesFolder['item'] ?? [])->firstWhere('name', 'Create Service');
    $setMainMedia = collect($adminServicesFolder['item'] ?? [])->firstWhere('name', 'Set Service Media As Main');
    $listPublicServices = collect($publicServicesFolder['item'] ?? [])->firstWhere('name', 'List Public Services');
    $deleteCategory = collect($adminCategoriesFolder['item'] ?? [])->firstWhere('name', 'Delete Category');
    $deleteSubcategory = collect($adminCategoriesFolder['item'] ?? [])->firstWhere('name', 'Delete Subcategory');

    expect($adminServicesFolder)->toBeArray()
        ->and($publicServicesFolder)->toBeArray()
        ->and($adminServiceOperationNames)->toBe([
            'List Services',
            'Create Service',
            'Show Service',
            'Update Service',
            'Delete Service',
            'Restore Service',
            'List Service Specifications',
            'Create Service Specification',
            'Show Service Specification',
            'Update Service Specification',
            'Delete Service Specification',
            'List Service Order Fields',
            'Create Service Order Field',
            'Show Service Order Field',
            'Update Service Order Field',
            'Delete Service Order Field',
            'List Service Pricing Options',
            'Create Service Pricing Option',
            'Show Service Pricing Option',
            'Update Service Pricing Option',
            'Delete Service Pricing Option',
            'List Service Media',
            'Upload Service Media',
            'Update Service Media Alt Text',
            'Delete Service Media',
            'Set Service Media As Main',
        ])
        ->and($publicServiceOperationNames)->toBe([
            'List Public Services',
            'Show Public Service',
        ])
        ->and((string) data_get($listAdminServices, 'request.url'))->toContain('{{baseUrl}}/admin/services?filter[search]=')
        ->and((string) data_get($listAdminServices, 'request.url'))->toContain('filter[categoryId]=')
        ->and((string) data_get($listAdminServices, 'request.url'))->toContain('sort=-createdAt')
        ->and((string) data_get($listPublicServices, 'request.url'))->toContain('{{baseUrl}}/public/services?filter[search]=')
        ->and((string) data_get($listPublicServices, 'request.url'))->not->toContain('sort=')
        ->and(collect(data_get($createService, 'request.body.formdata', []))->pluck('key')->contains('pricingOptions[0][nameAr]'))->toBeTrue()
        ->and((string) data_get($setMainMedia, 'request.url'))->toBe('{{baseUrl}}/admin/services/1/media/1/set-as-main')
        ->and((string) ($deleteCategory['description'] ?? ''))->toContain('CATEGORY_HAS_SERVICES')
        ->and((string) ($deleteSubcategory['description'] ?? ''))->toContain('SUBCATEGORY_HAS_SERVICES');
});
