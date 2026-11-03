<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Safely migrates existing private collections from public disk to private local disk.
     */
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $privateRecords = DB::table('media')
            ->where('disk', 'public')
            ->where(function ($query) {
                $query->whereIn('collection_name', [
                    'document',
                    'site_plans',
                    'design_files',
                    'delivery_note',
                    'certificate_files',
                    'resume',
                    'resumes',
                    'contract_document',
                ])
                    ->orWhere(function ($sub) {
                        $sub->whereIn('collection_name', ['documents', 'certificates'])
                            ->where('model_type', 'not like', '%CatalogItem%');
                    });
            })
            ->get();

        foreach ($privateRecords as $record) {
            $mediaPath = "{$record->id}/{$record->file_name}";

            // Move physical file from public disk to local private disk if it exists
            if (Storage::disk('public')->exists($mediaPath)) {
                $contents = Storage::disk('public')->get($mediaPath);
                if ($contents !== null && $contents !== false) {
                    $putSuccess = Storage::disk('local')->put($mediaPath, $contents);
                    if (! $putSuccess || ! Storage::disk('local')->exists($mediaPath) || Storage::disk('local')->size($mediaPath) !== Storage::disk('public')->size($mediaPath)) {
                        throw new \RuntimeException("Migration verification failed: unable to copy and verify media {$record->id} ({$record->file_name}) from public to local disk.");
                    }
                    Storage::disk('public')->delete($mediaPath);
                }
            }

            // Move responsive images / conversions directory if present
            $conversionsDir = "{$record->id}/conversions";
            if (Storage::disk('public')->exists($conversionsDir)) {
                $conversionFiles = Storage::disk('public')->files($conversionsDir);
                foreach ($conversionFiles as $convFile) {
                    $convContents = Storage::disk('public')->get($convFile);
                    if ($convContents !== null && $convContents !== false) {
                        Storage::disk('local')->put($convFile, $convContents);
                        if (Storage::disk('local')->exists($convFile) && Storage::disk('local')->size($convFile) === Storage::disk('public')->size($convFile)) {
                            Storage::disk('public')->delete($convFile);
                        }
                    }
                }
                Storage::disk('public')->deleteDirectory($conversionsDir);
            }

            $updates = ['disk' => 'local'];
            if (Schema::hasColumn('media', 'conversions_disk') && isset($record->conversions_disk) && $record->conversions_disk === 'public') {
                $updates['conversions_disk'] = 'local';
            }

            DB::table('media')
                ->where('id', $record->id)
                ->update($updates);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $records = DB::table('media')
            ->where('disk', 'local')
            ->where(function ($query) {
                $query->whereIn('collection_name', [
                    'document',
                    'site_plans',
                    'design_files',
                    'delivery_note',
                    'certificate_files',
                    'resume',
                    'resumes',
                    'contract_document',
                ])
                    ->orWhere(function ($sub) {
                        $sub->whereIn('collection_name', ['documents', 'certificates'])
                            ->where('model_type', 'not like', '%CatalogItem%');
                    });
            })
            ->get();

        foreach ($records as $record) {
            $mediaPath = "{$record->id}/{$record->file_name}";

            if (Storage::disk('local')->exists($mediaPath)) {
                $contents = Storage::disk('local')->get($mediaPath);
                if ($contents !== null && $contents !== false) {
                    $putSuccess = Storage::disk('public')->put($mediaPath, $contents);
                    if (! $putSuccess || ! Storage::disk('public')->exists($mediaPath) || Storage::disk('public')->size($mediaPath) !== Storage::disk('local')->size($mediaPath)) {
                        throw new \RuntimeException("Migration rollback verification failed: unable to copy and verify media {$record->id} ({$record->file_name}) from local to public disk.");
                    }
                    Storage::disk('local')->delete($mediaPath);
                }
            }

            $conversionsDir = "{$record->id}/conversions";
            if (Storage::disk('local')->exists($conversionsDir)) {
                $conversionFiles = Storage::disk('local')->files($conversionsDir);
                foreach ($conversionFiles as $convFile) {
                    $convContents = Storage::disk('local')->get($convFile);
                    if ($convContents !== null && $convContents !== false) {
                        Storage::disk('public')->put($convFile, $convContents);
                        if (Storage::disk('public')->exists($convFile) && Storage::disk('public')->size($convFile) === Storage::disk('local')->size($convFile)) {
                            Storage::disk('local')->delete($convFile);
                        }
                    }
                }
                Storage::disk('local')->deleteDirectory($conversionsDir);
            }

            $updates = ['disk' => 'public'];
            if (Schema::hasColumn('media', 'conversions_disk') && isset($record->conversions_disk) && $record->conversions_disk === 'local') {
                $updates['conversions_disk'] = 'public';
            }

            DB::table('media')
                ->where('id', $record->id)
                ->update($updates);
        }
    }
};
