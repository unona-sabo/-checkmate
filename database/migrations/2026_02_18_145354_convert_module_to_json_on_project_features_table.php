<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = DB::table('project_features')
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->get(['id', 'module']);

        foreach ($features as $feature) {
            // Skip values that are already valid JSON arrays
            if (str_starts_with($feature->module, '[')) {
                continue;
            }

            DB::table('project_features')
                ->where('id', $feature->id)
                ->update(['module' => json_encode([$feature->module])]);
        }
    }

    public function down(): void
    {
        $features = DB::table('project_features')
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->get(['id', 'module']);

        foreach ($features as $feature) {
            $decoded = json_decode($feature->module, true);

            if (is_array($decoded) && count($decoded) > 0) {
                DB::table('project_features')
                    ->where('id', $feature->id)
                    ->update(['module' => $decoded[0]]);
            }
        }
    }
};
