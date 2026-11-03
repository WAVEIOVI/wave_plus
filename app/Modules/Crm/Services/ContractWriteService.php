<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Enums\ContractStatus;
use App\Modules\Crm\Models\Contract;
use Illuminate\Support\Facades\DB;

class ContractWriteService
{
    public function __construct(
        private readonly CrmStateMachine $stateMachine,
    ) {}

    public function create(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $payload = collect($data)->except('lines')->toArray();
            $payload['status'] = $payload['status'] ?? ContractStatus::Draft->value;
            if (($payload['status'] ?? null) !== ContractStatus::Draft->value) {
                $payload['status'] = ContractStatus::Draft->value;
            }

            $ct = Contract::create($payload);

            foreach ($data['lines'] ?? [] as $line) {
                $ct->lines()->create($line);
            }

            return $ct;
        });
    }

    public function update(Contract $contract, array $data): Contract
    {
        $this->stateMachine->assertEditable('contract', $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status);

        return DB::transaction(function () use ($contract, $data) {
            $contract->update(collect($data)->except(['lines', 'status'])->toArray());

            if (isset($data['lines'])) {
                $keepIds = collect($data['lines'])->pluck('id')->filter()->toArray();
                $contract->lines()->whereNotIn('id', $keepIds)->delete();

                foreach ($data['lines'] as $line) {
                    if (isset($line['id'])) {
                        $contract->lines()->where('id', $line['id'])->update($line);
                    } else {
                        $contract->lines()->create($line);
                    }
                }
            }

            return $contract;
        });
    }

    public function transition(Contract $contract, string $to): Contract
    {
        $from = $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status;
        $this->stateMachine->assertUserTransition('contract', $from, $to);
        $contract->update(['status' => $to]);

        return $contract;
    }

    public function delete(Contract $contract): void
    {
        $status = $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status;
        $this->stateMachine->assertDeletable('contract', $status);
        $contract->delete();
    }
}
