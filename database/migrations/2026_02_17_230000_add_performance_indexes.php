<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('test_cases', function (Blueprint $table) {
                $table->index('test_suite_id');
                $table->index('priority');
                $table->index('type');
                $table->index('created_by');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('test_runs', function (Blueprint $table) {
                $table->index(['project_id', 'status']);
                $table->index('created_by');
                $table->index('checklist_id');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('test_run_cases', function (Blueprint $table) {
                $table->index(['test_run_id', 'status']);
                $table->index('test_case_id');
                $table->index('assigned_to');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('bugreports', function (Blueprint $table) {
                $table->index(['project_id', 'status']);
                $table->index('severity');
                $table->index('priority');
                $table->index('assigned_to');
                $table->index('reported_by');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('test_suites', function (Blueprint $table) {
                $table->index(['project_id', 'parent_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('checklists', function (Blueprint $table) {
                $table->index('project_id');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('checklist_rows', function (Blueprint $table) {
                $table->index(['checklist_id', 'order']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('documentations', function (Blueprint $table) {
                $table->index('project_id');
                $table->index('parent_id');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('notes', function (Blueprint $table) {
                $table->index('project_id');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('design_links', function (Blueprint $table) {
                $table->index('project_id');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('release_checklist_items', function (Blueprint $table) {
                $table->index(['release_id', 'status']);
                $table->index(['release_id', 'category']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('release_features', function (Blueprint $table) {
                $table->index('release_id');
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        try {
            Schema::table('test_cases', function (Blueprint $table) {
                $table->dropIndex(['test_suite_id']);
                $table->dropIndex(['priority']);
                $table->dropIndex(['type']);
                $table->dropIndex(['created_by']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('test_runs', function (Blueprint $table) {
                $table->dropIndex(['project_id', 'status']);
                $table->dropIndex(['created_by']);
                $table->dropIndex(['checklist_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('test_run_cases', function (Blueprint $table) {
                $table->dropIndex(['test_run_id', 'status']);
                $table->dropIndex(['test_case_id']);
                $table->dropIndex(['assigned_to']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('bugreports', function (Blueprint $table) {
                $table->dropIndex(['project_id', 'status']);
                $table->dropIndex(['severity']);
                $table->dropIndex(['priority']);
                $table->dropIndex(['assigned_to']);
                $table->dropIndex(['reported_by']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('test_suites', function (Blueprint $table) {
                $table->dropIndex(['project_id', 'parent_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('checklists', function (Blueprint $table) {
                $table->dropIndex(['project_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('checklist_rows', function (Blueprint $table) {
                $table->dropIndex(['checklist_id', 'order']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('documentations', function (Blueprint $table) {
                $table->dropIndex(['project_id']);
                $table->dropIndex(['parent_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropIndex(['project_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('design_links', function (Blueprint $table) {
                $table->dropIndex(['project_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('release_checklist_items', function (Blueprint $table) {
                $table->dropIndex(['release_id', 'status']);
                $table->dropIndex(['release_id', 'category']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('release_features', function (Blueprint $table) {
                $table->dropIndex(['release_id']);
            });
        } catch (\Throwable) {
        }
    }
};
