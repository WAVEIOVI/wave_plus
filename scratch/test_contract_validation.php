<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$data = [
    'type' => 'msa',
    'client_id' => 1,
    'title' => 'Test Contract',
    'start_date' => '2026-09-01',
    'status' => 'draft',
    'currency' => 'TND',
    'governing_law' => 'Tunisia',
    'confidentiality_applies' => false,
    'exclusivity_applies' => false,
    'auto_renewal' => false,
    'lines' => [],
];

$rules = (new \App\Modules\Crm\Requests\StoreContractRequest())->rules();
$validator = \Illuminate\Support\Facades\Validator::make($data, $rules);

echo "VALIDATION ERRORS:\n";
echo json_encode($validator->errors()->toArray(), JSON_PRETTY_PRINT) . "\n";
