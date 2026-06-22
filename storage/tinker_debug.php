<?php
$p = App\Models\Project::find(12);
$features = $p->features()->with(['testCases:id,module', 'checklists:id,module'])->get();
foreach ($features as $f) {
    echo "Feature: " . $f->name . " module=" . json_encode($f->module) . "\n";
    foreach ($f->testCases as $tc) {
        echo "  TC id=" . $tc->id . " module=" . json_encode($tc->module) . "\n";
    }
    foreach ($f->checklists as $cl) {
        echo "  CL id=" . $cl->id . " module=" . json_encode($cl->module) . "\n";
    }
}
