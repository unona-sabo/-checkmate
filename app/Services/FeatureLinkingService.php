<?php

namespace App\Services;

use App\Models\ProjectFeature;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Model;

class FeatureLinkingService
{
    /**
     * Sync the given feature IDs to a model's projectFeatures relationship.
     *
     * @param  array<int, int>  $featureIds
     */
    public function sync(Model $model, array $featureIds): void
    {
        $model->projectFeatures()->sync($featureIds);
    }

    /**
     * Sync features to a test suite and cascade the link to all its test cases.
     *
     * @param  array<int, int>  $featureIds
     */
    public function syncWithCascadeToTestCases(TestSuite $testSuite, array $featureIds): void
    {
        $testSuite->projectFeatures()->sync($featureIds);

        if ($featureIds === []) {
            return;
        }

        $testCaseIds = $testSuite->testCases()->pluck('id')->toArray();

        if ($testCaseIds === []) {
            return;
        }

        foreach ($featureIds as $featureId) {
            $feature = ProjectFeature::query()->find($featureId);
            $feature?->testCases()->syncWithoutDetaching($testCaseIds);
        }
    }
}
