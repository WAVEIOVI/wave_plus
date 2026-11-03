<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Services;

use App\Modules\ClientAssets\Enums\WorkwearItemCondition;
use App\Modules\ClientAssets\Enums\WorkwearItemStatus;
use App\Modules\ClientAssets\Models\WorkwearAssignment;
use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Models\WorkwearItemOptionValue;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Models\WorkwearModelOptionValue;
use App\Modules\ClientAssets\Models\WorkwearModelVersion;
use App\Modules\ClientAssets\Support\WorkwearStudioTemplateCatalog;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Logistics\Models\DeliveryNote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkwearService
{
    // ═══════════════════════════════════════════════════════════════
    //  REFERENCE / OWNERSHIP VALIDATION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Validate that a workwear item's related records are consistent.
     *
     * - the model belongs to the item's client
     * - the optional Sales Order / Delivery Note belong to the same client
     * - every selected option value belongs to the item's model, one per option
     *
     * SO/DN are optional: null / empty values are never an error.
     *
     * @param  array<int, string>  $optionValueIds
     * @return array<string, string> field => message (empty when valid)
     */
    public function referenceErrors(
        ?string $clientId,
        ?string $modelId,
        ?string $salesOrderId = null,
        ?string $deliveryNoteId = null,
        array $optionValueIds = [],
    ): array {
        $errors = [];

        if ($modelId && $clientId) {
            $modelClientId = WorkwearModel::query()->whereKey($modelId)->value('client_id');
            if ($modelClientId !== null && $modelClientId !== $clientId) {
                $errors['model_id'] = 'The selected model does not belong to the selected client.';
            }
        }

        if ($salesOrderId && $clientId) {
            $so = SalesOrder::query()->whereKey($salesOrderId)->first(['id', 'client_id']);
            if ($so !== null && $so->client_id !== $clientId) {
                $errors['sales_order_id'] = 'The selected sales order does not belong to the selected client.';
            }
        }

        if ($deliveryNoteId && $clientId) {
            $dn = DeliveryNote::query()->whereKey($deliveryNoteId)->first(['id', 'client_id']);
            if ($dn !== null && $dn->client_id !== $clientId) {
                $errors['delivery_note_id'] = 'The selected delivery note does not belong to the selected client.';
            }
        }

        $optionValueIds = array_values(array_filter($optionValueIds));
        if ($optionValueIds !== []) {
            if (! $modelId) {
                $errors['option_value_ids'] = 'Option values require a workwear model.';
            } else {
                $rows = WorkwearModelOptionValue::query()
                    ->whereIn('workwear_model_option_values.id', $optionValueIds)
                    ->join('workwear_model_options as o', 'o.id', '=', 'workwear_model_option_values.option_id')
                    ->get(['workwear_model_option_values.id', 'workwear_model_option_values.option_id', 'o.workwear_model_id']);

                $foreign = $rows->contains(fn ($r) => $r->workwear_model_id !== $modelId);
                $optionIds = $rows->pluck('option_id');

                if ($foreign) {
                    $errors['option_value_ids'] = 'One or more selected option values do not belong to the selected model.';
                } elseif ($optionIds->count() !== $optionIds->unique()->count() || count($optionValueIds) !== count(array_unique($optionValueIds))) {
                    $errors['option_value_ids'] = 'Only one value can be selected per option.';
                }
            }
        }

        return $errors;
    }

    /**
     * Throw a ValidationException when {@see referenceErrors()} finds problems.
     *
     * @param  array<int, string>  $optionValueIds
     * @param  string  $prefix  Field prefix for nested payloads (e.g. "items.0.")
     */
    public function assertReferences(
        ?string $clientId,
        ?string $modelId,
        ?string $salesOrderId = null,
        ?string $deliveryNoteId = null,
        array $optionValueIds = [],
        string $prefix = '',
    ): void {
        $errors = $this->referenceErrors($clientId, $modelId, $salesOrderId, $deliveryNoteId, $optionValueIds);

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn ($m, $k) => [$prefix.$k => [$m]])->all()
            );
        }
    }

    /**
     * Replace the option-value selections of an item (already validated).
     *
     * @param  array<int, string>  $optionValueIds
     */
    public function syncItemOptionValues(WorkwearItem $item, array $optionValueIds): void
    {
        $item->itemOptionValues()->delete();

        foreach (array_unique(array_filter($optionValueIds)) as $valueId) {
            $optVal = WorkwearModelOptionValue::find($valueId);
            if ($optVal) {
                WorkwearItemOptionValue::create([
                    'item_id' => $item->id,
                    'option_id' => $optVal->option_id,
                    'value_id' => $optVal->id,
                ]);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  BATCH ITEM CREATION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Create multiple items in a single transaction.
     *
     * All rows are validated first and persisted atomically: if any row is
     * invalid nothing is created (the previous implementation swallowed row
     * exceptions inside one transaction, which could leave partial data).
     *
     * @param  array  $payload  {model_id, client_id, sales_order_id?, delivery_note_id?, items: [{garment_type, size, item_condition?, option_value_ids?}]}
     * @return array{created: WorkwearItem[], errors: array}
     */
    public function batchCreateItems(array $payload): array
    {
        $modelId = $payload['model_id'];
        $clientId = $payload['client_id'];
        $salesOrderId = $payload['sales_order_id'] ?? null;
        $deliveryNoteId = $payload['delivery_note_id'] ?? null;

        WorkwearModel::findOrFail($modelId);

        // Header-level ownership (model + optional SO/DN)
        $this->assertReferences($clientId, $modelId, $salesOrderId, $deliveryNoteId);

        // Row-level option ownership
        foreach ($payload['items'] as $idx => $itemData) {
            $this->assertReferences($clientId, $modelId, null, null, $itemData['option_value_ids'] ?? [], "items.{$idx}.");
        }

        // validated() may emit rows out of order (keys are set in rule order); keep the submitted order.
        $rows = $payload['items'];
        ksort($rows);

        $created = DB::transaction(function () use ($rows, $payload, $modelId, $clientId, $salesOrderId, $deliveryNoteId) {
            $created = [];

            foreach ($rows as $itemData) {
                $item = WorkwearItem::create([
                    'model_id' => $modelId,
                    'client_id' => $clientId,
                    'garment_type' => $itemData['garment_type'] ?? null,
                    'size' => $itemData['size'] ?? null,
                    'color' => $itemData['color'] ?? null,
                    'sku' => $itemData['sku'] ?? null,
                    'supplier_id' => $itemData['supplier_id'] ?? $payload['supplier_id'] ?? null,
                    'item_condition' => $itemData['item_condition'] ?? WorkwearItemCondition::NEW->value,
                    'status' => WorkwearItemStatus::IN_STOCK->value,
                    'unit_cost' => $itemData['unit_cost'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'option_values' => $itemData['option_values'] ?? null,
                    'sales_order_id' => $salesOrderId,
                    'delivery_note_id' => $deliveryNoteId,
                ]);

                if (! empty($itemData['option_value_ids'])) {
                    $this->syncItemOptionValues($item, $itemData['option_value_ids']);
                }

                $item->load(['workwearModel', 'client', 'salesOrder', 'deliveryNote', 'itemOptionValues.option', 'itemOptionValues.optionValue']);
                $created[] = $item;
            }

            return $created;
        });

        return ['created' => $created, 'errors' => []];
    }

    // ═══════════════════════════════════════════════════════════════
    //  BATCH EMPLOYEE CREATION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Create multiple employees in a single transaction.
     *
     * @param  array  $payload  {client_id, employees: [{first_name, last_name, ...}]}
     * @return array{created: WorkwearEmployee[], errors: array}
     */
    public function batchCreateEmployees(array $payload): array
    {
        $clientId = $payload['client_id'];
        $created = [];
        $errors = [];

        DB::transaction(function () use ($payload, $clientId, &$created, &$errors) {
            foreach ($payload['employees'] as $idx => $empData) {
                try {
                    $emp = WorkwearEmployee::create([
                        'client_id' => $clientId,
                        'employee_ref' => $empData['employee_ref'] ?? null,
                        'first_name' => $empData['first_name'],
                        'last_name' => $empData['last_name'],
                        'email' => $empData['email'] ?? null,
                        'phone' => $empData['phone'] ?? null,
                        'department' => $empData['department'] ?? null,
                        'role' => $empData['role'] ?? null,
                        'position' => $empData['position'] ?? null,
                        'status' => $empData['status'] ?? 'active',
                        'sizes' => $empData['sizes'] ?? null,
                        'measurements' => $empData['measurements'] ?? null,
                        'workwear_requirements' => $empData['workwear_requirements'] ?? null,
                        'notes' => $empData['notes'] ?? null,
                    ]);

                    $emp->load('client');
                    $created[] = $emp;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $idx,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

        return ['created' => $created, 'errors' => $errors];
    }

    // ═══════════════════════════════════════════════════════════════
    //  ASSIGNMENT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Assign one or more items to an employee.
     *
     * @param  array  $payload  {employee_id, item_ids: [...], condition_at_assign?, notes?}
     * @return array{assignments: WorkwearAssignment[], errors: array}
     */
    public function assignItems(array $payload): array
    {
        $employeeId = $payload['employee_id'];
        $employee = WorkwearEmployee::findOrFail($employeeId);

        $assignments = [];
        $errors = [];

        DB::transaction(function () use ($payload, $employee, &$assignments, &$errors) {
            foreach ($payload['item_ids'] as $idx => $itemId) {
                try {
                    $item = WorkwearItem::lockForUpdate()->findOrFail($itemId);

                    // Check item is available for assignment
                    if (! $item->isAvailableForAssignment()) {
                        $errors[] = [
                            'item_id' => $itemId,
                            'message' => "Item {$item->item_code} is not available (status: {$item->status->value}).",
                        ];

                        continue;
                    }

                    // Prevent duplicate active assignment
                    $existingActive = WorkwearAssignment::where('item_id', $itemId)
                        ->whereNull('returned_at')
                        ->exists();

                    if ($existingActive) {
                        $errors[] = [
                            'item_id' => $itemId,
                            'message' => "Item {$item->item_code} already has an active assignment.",
                        ];

                        continue;
                    }

                    // Enforce client consistency
                    if ($item->client_id !== $employee->client_id) {
                        $errors[] = [
                            'item_id' => $itemId,
                            'message' => "Item {$item->item_code} belongs to a different client.",
                        ];

                        continue;
                    }

                    $assignment = WorkwearAssignment::create([
                        'item_id' => $item->id,
                        'employee_id' => $employee->id,
                        'assigned_by' => Auth::id(),
                        'assigned_at' => now(),
                        'condition_at_assign' => $payload['condition_at_assign'] ?? $item->item_condition?->value ?? 'good',
                        'notes' => $payload['notes'] ?? null,
                    ]);

                    $item->update(['status' => WorkwearItemStatus::ASSIGNED->value]);
                    $assignment->load(['item', 'employee']);
                    $assignments[] = $assignment;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'item_id' => $itemId,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

        return ['assignments' => $assignments, 'errors' => $errors];
    }

    // ═══════════════════════════════════════════════════════════════
    //  RETURN
    // ═══════════════════════════════════════════════════════════════

    /**
     * Process a return for an assignment.
     *
     * @param  array  $payload  {condition_at_return, return_action, notes?}
     */
    public function returnAssignment(string $assignmentId, array $payload): WorkwearAssignment
    {
        return DB::transaction(function () use ($assignmentId, $payload) {
            $assignment = WorkwearAssignment::lockForUpdate()->findOrFail($assignmentId);

            if ($assignment->returned_at !== null) {
                throw ValidationException::withMessages([
                    'assignment' => ['This assignment has already been returned.'],
                ]);
            }

            $item = WorkwearItem::lockForUpdate()->findOrFail($assignment->item_id);

            $returnAction = $payload['return_action'] ?? 'restock';
            $conditionAtReturn = $payload['condition_at_return'] ?? null;

            // Determine new item status based on return action
            $newStatus = match ($returnAction) {
                'restock' => WorkwearItemStatus::AVAILABLE,
                'repair' => WorkwearItemStatus::REPAIR_NEEDED,
                'retire' => WorkwearItemStatus::RETIRED,
                'lost' => WorkwearItemStatus::LOST,
                'stolen' => WorkwearItemStatus::STOLEN,
                default => WorkwearItemStatus::RETURNED,
            };

            // Update assignment
            $assignment->update([
                'returned_at' => now(),
                'condition_at_return' => $conditionAtReturn,
                'return_action' => $returnAction,
                'returned_by' => Auth::id(),
                'notes' => $payload['notes'] ?? $assignment->notes,
            ]);

            // Update item
            $updateData = ['status' => $newStatus->value];
            if ($conditionAtReturn) {
                $updateData['item_condition'] = $conditionAtReturn;
            }
            if ($returnAction === 'stolen') {
                $updateData['is_stolen'] = true;
                $updateData['stolen_data'] = [
                    'reported_at' => now()->toISOString(),
                    'notes' => $payload['notes'] ?? null,
                    'reported_by' => Auth::id(),
                ];
            }
            if ($returnAction === 'retire') {
                $updateData['retired_data'] = [
                    'retired_at' => now()->toISOString(),
                    'reason' => $payload['notes'] ?? 'Retired on return',
                ];
            }

            $item->update($updateData);

            $assignment->load(['item.workwearModel', 'employee', 'assignedByUser', 'returnedByUser']);

            return $assignment;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    //  KPI / STATS
    // ═══════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════
    //  MODEL STUDIO — placements, revisions
    // ═══════════════════════════════════════════════════════════════

    /**
     * Replace-sync the customization placements of a model.
     * Rows carrying a known id are updated, new rows created, omitted rows removed.
     *
     * @param  array<int, array<string, mixed>>  $placements
     */
    public function syncModelPlacements(WorkwearModel $model, array $placements): void
    {
        $existing = $model->placements()->get()->keyBy('id');
        $keep = [];
        $silhouette = $model->silhouette ?: ($model->garment_types[0] ?? 'coverall');

        foreach (array_values($placements) as $idx => $p) {
            $normalized = WorkwearStudioTemplateCatalog::normalizePlacementForTemplate($silhouette, $p);

            $attributes = [
                'view' => $normalized['view'],
                'type' => $p['type'],
                'label' => $p['label'],
                'zone' => $normalized['zone'],
                'text' => $p['text'] ?? null,
                'method' => $p['method'] ?? null,
                'color' => $p['color'] ?? null,
                'width_mm' => $p['width_mm'] ?? $normalized['width_mm'],
                'height_mm' => $p['height_mm'] ?? $normalized['height_mm'],
                'offset_x_mm' => $p['offset_x_mm'] ?? 0,
                'offset_y_mm' => $p['offset_y_mm'] ?? 0,
                'media_id' => $p['media_id'] ?? null,
                'notes' => $p['notes'] ?? null,
                'sort_order' => $p['sort_order'] ?? $idx,
            ];

            if (! empty($p['id']) && $existing->has($p['id'])) {
                $existing[$p['id']]->update($attributes);
                $keep[] = $p['id'];
            } else {
                $keep[] = $model->placements()->create($attributes)->id;
            }
        }

        $model->placements()->whereNotIn('id', $keep)->delete();
    }

    /**
     * Persist a revision of the model (specification, options and placements).
     */
    public function recordModelVersion(WorkwearModel $model, ?string $summary = null): WorkwearModelVersion
    {
        $snapshot = $model->fresh(['options.values', 'placements'])->toArray();

        return WorkwearModelVersion::create([
            'workwear_model_id' => $model->id,
            'version_number' => ((int) $model->versions()->max('version_number')) + 1,
            'snapshot' => $snapshot,
            'change_summary' => $summary,
            'created_by' => Auth::id(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  EMPLOYEE MODEL ENTITLEMENTS (assignment ≠ issuance)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Synchronise the models assigned to an employee.
     *
     * Entitlements describe what the employee is meant to wear (model, size, quantity);
     * they never change item stock. Removed entitlements are ended, not deleted,
     * so the assignment history is kept.
     *
     * @param  array<int, array{model_id: string, quantity?: int, size?: string|null, notes?: string|null}>  $assignments
     */
    public function syncEmployeeModels(WorkwearEmployee $employee, array $assignments): void
    {
        $modelIds = array_column($assignments, 'model_id');

        if (count($modelIds) !== count(array_unique($modelIds))) {
            throw ValidationException::withMessages(['model_assignments' => 'A model can only be assigned once per employee.']);
        }

        $foreign = WorkwearModel::query()
            ->whereIn('id', $modelIds)
            ->where('client_id', '!=', $employee->client_id)
            ->exists();

        if ($foreign || WorkwearModel::query()->whereIn('id', $modelIds)->count() !== count($modelIds)) {
            throw ValidationException::withMessages(['model_assignments' => 'Models must belong to the same client as the employee.']);
        }

        DB::transaction(function () use ($employee, $assignments, $modelIds) {
            $active = $employee->activeModelAssignments()->get()->keyBy('model_id');

            foreach ($assignments as $row) {
                $attributes = [
                    'quantity' => $row['quantity'] ?? 1,
                    'size' => $row['size'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ];

                if ($current = $active->get($row['model_id'])) {
                    $current->update($attributes);
                } else {
                    $employee->modelAssignments()->create($attributes + [
                        'model_id' => $row['model_id'],
                        'assigned_at' => now(),
                        'assigned_by' => Auth::id(),
                    ]);
                }
            }

            $employee->activeModelAssignments()
                ->whereNotIn('model_id', $modelIds)
                ->update(['ended_at' => now()]);
        });
    }

    // ═══════════════════════════════════════════════════════════════
    //  STOCK AVAILABILITY (read-only — never touches the inventory module)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Per-variant (model / sku / size / colour) stock availability of workwear items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stockSummary(?string $clientId = null, ?string $modelId = null, ?string $supplierId = null): array
    {
        $assignable = "'".implode("','", WorkwearItemStatus::assignableStatuses())."'";
        $inactive = "'".implode("','", [
            WorkwearItemStatus::RETIRED->value,
            WorkwearItemStatus::LOST->value,
            WorkwearItemStatus::STOLEN->value,
        ])."'";

        return WorkwearItem::query()
            ->join('workwear_models as wm', 'wm.id', '=', 'workwear_items.model_id')
            ->when($clientId, fn ($q) => $q->where('workwear_items.client_id', $clientId))
            ->when($modelId, fn ($q) => $q->where('workwear_items.model_id', $modelId))
            ->when($supplierId, fn ($q) => $q->where('workwear_items.supplier_id', $supplierId))
            ->groupBy('workwear_items.model_id', 'wm.name', 'wm.model_ref', 'workwear_items.sku', 'workwear_items.size', 'workwear_items.color')
            ->orderBy('wm.name')->orderBy('workwear_items.size')
            ->selectRaw('workwear_items.model_id, wm.name as model_name, wm.model_ref, workwear_items.sku, workwear_items.size, workwear_items.color')
            ->selectRaw("SUM(CASE WHEN workwear_items.status NOT IN ({$inactive}) THEN 1 ELSE 0 END) as total")
            ->selectRaw("SUM(CASE WHEN workwear_items.status IN ({$assignable}) THEN 1 ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN workwear_items.status = 'assigned' THEN 1 ELSE 0 END) as assigned")
            ->selectRaw("SUM(CASE WHEN workwear_items.status = 'repair_needed' THEN 1 ELSE 0 END) as repair_needed")
            ->get()
            ->map(fn ($r) => [
                'model_id' => $r->model_id,
                'model_name' => $r->model_name,
                'model_ref' => $r->model_ref,
                'sku' => $r->sku,
                'size' => $r->size,
                'color' => $r->color,
                'total' => (int) $r->total,
                'available' => (int) $r->available,
                'assigned' => (int) $r->assigned,
                'repair_needed' => (int) $r->repair_needed,
            ])
            ->all();
    }

    /**
     * Gather server-side workwear KPI data.
     */
    public function getKpis(?string $clientId = null): array
    {
        $itemQuery = WorkwearItem::query();
        $modelQuery = WorkwearModel::query();
        $employeeQuery = WorkwearEmployee::query();
        $assignmentQuery = WorkwearAssignment::query();

        if ($clientId) {
            $itemQuery->where('client_id', $clientId);
            $modelQuery->where('client_id', $clientId);
            $employeeQuery->where('client_id', $clientId);
            $assignmentQuery->whereHas('employee', fn ($q) => $q->where('client_id', $clientId));
        }

        return [
            'total_items' => (clone $itemQuery)->count(),
            'in_stock' => (clone $itemQuery)->where('status', WorkwearItemStatus::IN_STOCK)->count(),
            'available' => (clone $itemQuery)->whereIn('status', WorkwearItemStatus::assignableStatuses())->count(),
            'assigned' => (clone $itemQuery)->where('status', WorkwearItemStatus::ASSIGNED)->count(),
            'returned' => (clone $itemQuery)->where('status', WorkwearItemStatus::RETURNED)->count(),
            'repair_needed' => (clone $itemQuery)->where('status', WorkwearItemStatus::REPAIR_NEEDED)->count(),
            'retired' => (clone $itemQuery)->where('status', WorkwearItemStatus::RETIRED)->count(),
            'lost_or_stolen' => (clone $itemQuery)->whereIn('status', [WorkwearItemStatus::LOST, WorkwearItemStatus::STOLEN])->count(),
            'total_models' => $modelQuery->count(),
            'active_models' => (clone $modelQuery)->where('status', 'active')->count(),
            'total_employees' => $employeeQuery->count(),
            'active_employees' => (clone $employeeQuery)->where('status', 'active')->count(),
            'active_assignments' => (clone $assignmentQuery)->whereNull('returned_at')->count(),
        ];
    }
}
