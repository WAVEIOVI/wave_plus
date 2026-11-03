<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentSequenceSeeder extends Seeder
{
    public function run(): void
    {
        $sequences = [
            'IPR' => 12,
            'RFQ' => 8,
            'PO' => 10,
            'GRN' => 7,
            'QUOT' => 15,
            'SO' => 9,
            'LSA' => 5,
            'GCS' => 4,
            'CTR' => 3,
            'SM' => 50,
            'EMP' => 0,
            'JOB' => 0,
            'CAN' => 0,
            'LV' => 0,
        ];

        foreach ($sequences as $type => $value) {
            $exists = DB::table('document_sequences')
                ->where('document_type', $type)
                ->exists();

            if ($exists) {
                DB::table('document_sequences')
                    ->where('document_type', $type)
                    ->update([
                        'current_value' => $value,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('document_sequences')->insert([
                    'id' => (string) Str::ulid(),
                    'document_type' => $type,
                    'current_value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
