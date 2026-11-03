<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Models\IprLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IprWriteService
{
    public function __construct(private readonly ProcurementStateMachine $stateMachine) {}

    public function create(array $validated): InternalPurchaseRequest
    {
        return DB::transaction(function () use ($validated) {
            $lines = Arr::pull($validated, 'lines');
            $validated['status'] = 'draft';

            $ipr = InternalPurchaseRequest::create($validated);

            foreach ($lines as $lineData) {
                $lineData['ipr_id'] = $ipr->id;
                IprLine::create($lineData);
            }

            return $ipr->fresh();
        });
    }

    public function update(InternalPurchaseRequest $ipr, array $validated): InternalPurchaseRequest
    {
        $this->stateMachine->assertEditable('ipr', $ipr->status);

        return DB::transaction(function () use ($ipr, $validated) {
            $linesData = Arr::pull($validated, 'lines');
            unset($validated['status']);
            $ipr->update($validated);

            $existingLineIds = $ipr->lines()->pluck('id')->all();
            $newLineIds = [];

            foreach ($linesData as $lineItem) {
                if (! empty($lineItem['id']) && in_array($lineItem['id'], $existingLineIds, true)) {
                    $line = IprLine::find($lineItem['id']);
                    $line->update(Arr::except($lineItem, ['id', 'ipr_id']));
                    $newLineIds[] = $line->id;
                } else {
                    $lineItem['ipr_id'] = $ipr->id;
                    $newLineIds[] = IprLine::create(Arr::except($lineItem, ['id']))->id;
                }
            }

            $toDelete = array_diff($existingLineIds, $newLineIds);
            if ($toDelete !== []) {
                IprLine::whereIn('id', $toDelete)->delete();
            }

            return $ipr->fresh();
        });
    }

    public function transition(InternalPurchaseRequest $ipr, array $payload): InternalPurchaseRequest
    {
        $to = (string) $payload['status'];
        $this->stateMachine->assertUserTransition('ipr', $ipr->status, $to);

        if ($to === 'rejected' && empty($payload['rejection_reason'])) {
            abort(422, 'A rejection reason is required.');
        }

        $ipr->update(Arr::only($payload, ['status', 'approval_note', 'rejection_reason']));

        return $ipr->fresh();
    }

    public function delete(InternalPurchaseRequest $ipr): void
    {
        $this->stateMachine->assertDeletable('ipr', $ipr->status);

        DB::transaction(function () use ($ipr) {
            $ipr->lines()->delete();
            $ipr->delete();
        });
    }
}
