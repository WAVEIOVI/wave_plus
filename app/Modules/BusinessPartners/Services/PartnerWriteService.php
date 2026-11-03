<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerWriteService
{
    public function create(string $modelClass, array $data, ?Request $request = null): Model
    {
        unset($data['logo']);
        if ($request) {
            unset($data['logo_path'], $data['logo_source'], $data['logo_domain'], $data['logo_external_url']);
        }

        return DB::transaction(function () use ($modelClass, $data, $request) {
            $record = $modelClass::query()->create($data);

            $this->syncLogo($record, $request);

            return $record->fresh();
        });
    }

    public function update(Model $record, array $data, ?Request $request = null): Model
    {
        unset($data['logo']);
        if ($request) {
            unset($data['logo_path'], $data['logo_source'], $data['logo_domain'], $data['logo_external_url']);
        }

        return DB::transaction(function () use ($record, $data, $request) {
            $record->update($data);

            $this->syncLogo($record, $request);

            return $record->fresh();
        });
    }

    /**
     * Store an uploaded logo and clear any provider reference, so exactly one
     * logo source (upload OR provider) is ever authoritative. Deleting the
     * previous media first prevents orphaned files in the singleFile collection.
     */
    private function syncLogo(Model $record, ?Request $request): void
    {
        \App\Modules\Shared\Services\BrandingSyncService::syncLogo($record, $request);
    }

    public function delete(Model $record): void
    {
        try {
            $record->delete();
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? null;
            if ($sqlState === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                abort(409, 'This business partner cannot be deleted while related records still exist.');
            }

            throw $e;
        }
    }
}
