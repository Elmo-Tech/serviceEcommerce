<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('freezes the single strict dashboard OpenAPI contract with resolved references', function (): void {
    $path = base_path('specs/007-admin-dashboard-analytics/contracts/openapi.yaml');
    $script = <<<'PY'
import json
import sys
import yaml

with open(sys.argv[1], 'r', encoding='utf-8') as handle:
    document = yaml.safe_load(handle)

refs = []
def walk(node):
    if isinstance(node, dict):
        for key, value in node.items():
            if key == '$ref' and isinstance(value, str) and value.startswith('#/'):
                refs.append(value)
            walk(value)
    elif isinstance(node, list):
        for value in node:
            walk(value)

def resolves(ref):
    node = document
    for part in ref[2:].split('/'):
        part = part.replace('~1', '/').replace('~0', '~')
        if not isinstance(node, dict) or part not in node:
            return False
        node = node[part]
    return True

walk(document)
path = document['paths']['/api/v1/admin/dashboard']
operation = path['get']
filter_parameter = operation['parameters'][0]
payload = document['components']['schemas']['DashboardPayload']

print(json.dumps({
    'openapi': document['openapi'],
    'paths': list(document['paths'].keys()),
    'methods': list(path.keys()),
    'operationId': operation['operationId'],
    'filterStyle': filter_parameter['style'],
    'filterExplode': filter_parameter['explode'],
    'payloadProperties': list(payload['properties'].keys()),
    'payloadRequired': payload['required'],
    'unresolved': [ref for ref in refs if not resolves(ref)],
    'completedAtReadOnly': document['components']['schemas']['CompletedAt']['readOnly'],
}))
PY;

    $process = new Process(['python', '-c', $script, $path]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue();
    $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    expect($result['openapi'])->toBe('3.1.0')
        ->and($result['paths'])->toBe(['/api/v1/admin/dashboard'])
        ->and($result['methods'])->toBe(['get'])
        ->and($result['operationId'])->toBe('showAdminDashboardAnalytics')
        ->and($result['filterStyle'])->toBe('deepObject')
        ->and($result['filterExplode'])->toBeTrue()
        ->and($result['payloadProperties'])->toBe([
            'salesPeriod', 'sales', 'collectedSales', 'uncollectedSales', 'orders', 'performance',
        ])
        ->and($result['payloadRequired'])->toBe($result['payloadProperties'])
        ->and($result['unresolved'])->toBeEmpty()
        ->and($result['completedAtReadOnly'])->toBeTrue();
});
