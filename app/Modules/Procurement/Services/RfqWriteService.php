<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Procurement\Models\RfqHeader;
use App\Modules\Procurement\Models\RfqInvitation;
use App\Modules\Procurement\Models\RfqLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RfqWriteService
{
    public function __construct(private readonly ProcurementStateMachine $stateMachine) {}

    public function create(array $validated): RfqHeader
    {
        return DB::transaction(function () use ($validated) {
            $linesData = Arr::pull($validated, 'lines');
            $invitationsData = Arr::pull($validated, 'invitations', []);
            $validated['status'] = 'draft';

            $rfq = RfqHeader::create($validated);

            foreach ($linesData as $lineData) {
                $lineData['rfq_id'] = $rfq->id;
                RfqLine::create($lineData);
            }

            foreach ($invitationsData as $invitationData) {
                RfqInvitation::create([
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $invitationData['supplier_id'],
                    'status' => 'sent',
                ]);
            }

            return $rfq->fresh();
        });
    }

    public function update(RfqHeader $rfq, array $validated): RfqHeader
    {
        $this->stateMachine->assertEditable('rfq', $rfq->status);

        return DB::transaction(function () use ($rfq, $validated) {
            $linesData = Arr::pull($validated, 'lines');
            $invitationsData = Arr::pull($validated, 'invitations', []);
            unset($validated['status'], $validated['awarded_to_supplier_id'], $validated['awarded_at'], $validated['awarded_by']);

            $rfq->update($validated);
            $this->syncLines($rfq, $linesData ?? []);
            $this->syncInvitations($rfq, $invitationsData);

            return $rfq->fresh();
        });
    }

    public function transition(RfqHeader $rfq, string $status, User $user, ?string $awardedSupplierId = null): RfqHeader
    {
        $this->stateMachine->assertUserTransition('rfq', $rfq->status, $status);

        $payload = ['status' => $status];

        if ($status === 'awarded') {
            $supplierId = $awardedSupplierId ?: $rfq->awarded_to_supplier_id;
            if (! $supplierId) {
                abort(422, 'An awarded RFQ must name a winning supplier.');
            }
            $payload['awarded_to_supplier_id'] = $supplierId;
            $payload['awarded_by'] = $user->id;
            $payload['awarded_at'] = now();
        }

        $rfq->update($payload);

        if ($status === 'awarded' && ! empty($payload['awarded_to_supplier_id'])) {
            $rfq->responses()->update(['is_awarded' => false]);
            $rfq->invitations()->where('status', 'awarded')->update(['status' => 'responded']);
            $rfq->responses()->where('supplier_id', $payload['awarded_to_supplier_id'])->update(['is_awarded' => true]);
            $rfq->invitations()->where('supplier_id', $payload['awarded_to_supplier_id'])->update(['status' => 'awarded']);
        }

        return $rfq->fresh();
    }

    public function delete(RfqHeader $rfq): void
    {
        $this->stateMachine->assertDeletable('rfq', $rfq->status);

        DB::transaction(function () use ($rfq) {
            $rfq->lines()->delete();
            $rfq->invitations()->delete();
            $rfq->delete();
        });
    }

    private function syncLines(RfqHeader $rfq, array $linesData): void
    {
        $existingLineIds = $rfq->lines()->pluck('id')->all();
        $newLineIds = [];

        foreach ($linesData as $lineItem) {
            if (! empty($lineItem['id']) && in_array($lineItem['id'], $existingLineIds, true)) {
                $line = RfqLine::find($lineItem['id']);
                $line->update(Arr::except($lineItem, ['id', 'rfq_id']));
                $newLineIds[] = $line->id;
            } else {
                $lineItem['rfq_id'] = $rfq->id;
                $newLineIds[] = RfqLine::create(Arr::except($lineItem, ['id']))->id;
            }
        }

        $toDelete = array_diff($existingLineIds, $newLineIds);
        if ($toDelete !== []) {
            RfqLine::whereIn('id', $toDelete)->delete();
        }
    }

    private function syncInvitations(RfqHeader $rfq, array $invitationsData): void
    {
        $existingInvitationIds = $rfq->invitations()->pluck('id')->all();
        $newInvitationIds = [];

        foreach ($invitationsData as $invData) {
            $invitation = RfqInvitation::firstOrCreate(
                [
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $invData['supplier_id'],
                ],
                [
                    'status' => $invData['status'] ?? 'sent',
                ]
            );
            $newInvitationIds[] = $invitation->id;
        }

        $toDelete = array_diff($existingInvitationIds, $newInvitationIds);
        if ($toDelete !== []) {
            RfqInvitation::whereIn('id', $toDelete)->delete();
        }
    }
}
