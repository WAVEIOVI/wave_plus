<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Trait GeneratesReference
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @method static void creating(\Closure $callback)
 */
trait GeneratesReference
{
    /**
     * Boot the trait to hook into the model's creating event.
     *
     * @return void
     *
     * @method static creating(\Closure $callback)
     */
    public static function bootGeneratesReference()
    {
        static::creating(function ($model) {
            $model->generateReference();
        });
    }

    /**
     * Generate and set the reference for the model.
     *
     * @return void
     */
    public function generateReference()
    {
        $referenceField = $this->getReferenceColumn();

        if (empty($this->{$referenceField})) {
            // e.g. "SO" or "SO-2026" if sequences should reset yearly
            $sequenceType = $this->getDocumentSequenceName();
            $nextValue = static::getNextSequenceValue($sequenceType);

            $this->{$referenceField} = $this->formatReference($sequenceType, $nextValue);
        }
    }

    /**
     * Get the name used in the document_sequences table.
     * By default, it uses the prefix. Override to append the year (e.g., "SO-" . date('Y'))
     * if you want the sequence to reset every year.
     */
    protected function getDocumentSequenceName(): string
    {
        return $this->getDocumentTypePrefix();
    }

    /**
     * Format the reference string.
     * Override this method in your model to customize the format.
     */
    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%06d', $sequenceType, $nextValue);
    }

    /**
     * Get the next sequence value using a pessimistic lock.
     */
    protected static function getNextSequenceValue(string $documentType): int
    {
        return DB::transaction(function () use ($documentType) {
            // Retrieve the sequence row and lock it for update
            $sequence = DB::table('document_sequences')
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                // Increment the value
                $nextValue = $sequence->current_value + 1;

                DB::table('document_sequences')
                    ->where('id', $sequence->id)
                    ->update(['current_value' => $nextValue, 'updated_at' => now()]);

                return $nextValue;
            } else {
                // Insert the initial sequence value
                DB::table('document_sequences')->insert([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'document_type' => $documentType,
                    'current_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }
        });
    }

    /**
     * Get the document type prefix.
     * Override this method in the model or define a constant DOCUMENT_TYPE_PREFIX.
     */
    protected function getDocumentTypePrefix(): string
    {
        if (defined('static::DOCUMENT_TYPE_PREFIX')) {
            return static::DOCUMENT_TYPE_PREFIX;
        }

        $className = class_basename(static::class);
        $words = preg_split('/(?=[A-Z])/', $className, -1, PREG_SPLIT_NO_EMPTY);
        $prefix = collect($words)->map(fn ($word) => strtoupper(substr($word, 0, 1)))->join('');

        return $prefix;
    }

    /**
     * Get the name of the reference column.
     * Override this method in the model if the column is not 'reference'.
     */
    protected function getReferenceColumn(): string
    {
        return 'reference';
    }
}
