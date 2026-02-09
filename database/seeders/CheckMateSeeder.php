<?php

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistRow;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Database\Seeder;

class CheckMateSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a demo user
        $user = User::firstOrCreate(
            ['email' => 'demo@checkmate.test'],
            [
                'name' => 'Demo User',
                'password' => bcrypt('password'),
            ]
        );

        // Create projects
        $projects = collect([
            'E-Commerce Platform',
            'Mobile Banking App',
            'Admin Dashboard',
        ])->map(fn ($name) => Project::create([
            'user_id' => $user->id,
            'name' => $name,
        ]));

        foreach ($projects as $project) {
            // Create test suites
            $suites = collect([
                ['name' => 'Authentication', 'description' => 'User authentication and authorization tests'],
                ['name' => 'User Management', 'description' => 'User CRUD operations and profile management'],
                ['name' => 'Dashboard', 'description' => 'Dashboard functionality and widgets'],
            ])->map(fn ($data, $index) => TestSuite::create([
                'project_id' => $project->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'order' => $index,
            ]));

            // Create test cases for each suite
            foreach ($suites as $suite) {
                $testCases = $this->getTestCasesForSuite($suite->name);

                foreach ($testCases as $index => $tcData) {
                    TestCase::create([
                        'test_suite_id' => $suite->id,
                        'title' => $tcData['title'],
                        'description' => $tcData['description'] ?? null,
                        'preconditions' => $tcData['preconditions'] ?? null,
                        'steps' => $tcData['steps'] ?? null,
                        'expected_result' => $tcData['expected_result'] ?? null,
                        'priority' => $tcData['priority'] ?? 'medium',
                        'severity' => $tcData['severity'] ?? 'major',
                        'type' => $tcData['type'] ?? 'functional',
                        'automation_status' => $tcData['automation_status'] ?? 'not_automated',
                        'tags' => $tcData['tags'] ?? null,
                        'order' => $index,
                    ]);
                }
            }

            // Create a test run with some test cases
            $testRun = TestRun::create([
                'project_id' => $project->id,
                'name' => 'Sprint 1 Regression',
                'description' => 'Regression testing for Sprint 1 release',
                'environment' => 'Staging',
                'milestone' => 'v1.0',
                'status' => 'active',
                'started_at' => now(),
            ]);

            // Add test cases to the run
            $allTestCases = TestCase::whereIn('test_suite_id', $suites->pluck('id'))->get();
            $statuses = ['untested', 'passed', 'failed', 'blocked', 'skipped'];

            foreach ($allTestCases->take(10) as $testCase) {
                TestRunCase::create([
                    'test_run_id' => $testRun->id,
                    'test_case_id' => $testCase->id,
                    'status' => fake()->randomElement($statuses),
                    'assigned_to' => fake()->boolean(30) ? $user->id : null,
                ]);
            }

            $testRun->updateProgress();
            $testRun->updateStats();

            // Create a checklist
            $checklist = Checklist::create([
                'project_id' => $project->id,
                'name' => 'Deployment Checklist',
                'columns_config' => [
                    ['key' => 'item', 'label' => 'Task', 'type' => 'text'],
                    ['key' => 'done', 'label' => 'Done', 'type' => 'checkbox'],
                    ['key' => 'assignee', 'label' => 'Assignee', 'type' => 'text'],
                ],
            ]);

            $checklistItems = [
                ['item' => 'Run all tests', 'done' => true, 'assignee' => 'Dev Team'],
                ['item' => 'Code review completed', 'done' => true, 'assignee' => 'Tech Lead'],
                ['item' => 'Database migrations tested', 'done' => false, 'assignee' => 'DBA'],
                ['item' => 'Backup created', 'done' => false, 'assignee' => 'DevOps'],
                ['item' => 'Rollback plan documented', 'done' => false, 'assignee' => 'DevOps'],
            ];

            foreach ($checklistItems as $index => $item) {
                ChecklistRow::create([
                    'checklist_id' => $checklist->id,
                    'data' => $item,
                    'order' => $index,
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTestCasesForSuite(string $suiteName): array
    {
        return match ($suiteName) {
            'Authentication' => [
                [
                    'title' => 'Verify user can login with valid credentials',
                    'description' => 'Test that a registered user can successfully login',
                    'preconditions' => 'User must be registered in the system',
                    'steps' => [
                        ['action' => 'Navigate to login page', 'expected' => 'Login form is displayed'],
                        ['action' => 'Enter valid email', 'expected' => 'Email is accepted'],
                        ['action' => 'Enter valid password', 'expected' => 'Password field shows masked characters'],
                        ['action' => 'Click Login button', 'expected' => 'User is redirected to dashboard'],
                    ],
                    'expected_result' => 'User successfully logs in and sees the dashboard',
                    'priority' => 'critical',
                    'severity' => 'blocker',
                    'type' => 'smoke',
                    'tags' => ['login', 'smoke', 'P1'],
                ],
                [
                    'title' => 'Verify error message for invalid password',
                    'description' => 'Test that an appropriate error message is shown for wrong password',
                    'priority' => 'high',
                    'severity' => 'major',
                    'type' => 'functional',
                    'tags' => ['login', 'negative'],
                ],
                [
                    'title' => 'Verify password reset functionality',
                    'priority' => 'high',
                    'severity' => 'major',
                    'type' => 'functional',
                ],
                [
                    'title' => 'Verify logout functionality',
                    'priority' => 'high',
                    'severity' => 'critical',
                    'type' => 'smoke',
                    'tags' => ['logout', 'smoke'],
                ],
            ],
            'User Management' => [
                [
                    'title' => 'Verify user can update profile information',
                    'priority' => 'medium',
                    'severity' => 'major',
                    'type' => 'functional',
                ],
                [
                    'title' => 'Verify user can change password',
                    'priority' => 'high',
                    'severity' => 'major',
                    'type' => 'functional',
                ],
                [
                    'title' => 'Verify user can upload avatar',
                    'priority' => 'low',
                    'severity' => 'minor',
                    'type' => 'functional',
                ],
            ],
            'Dashboard' => [
                [
                    'title' => 'Verify dashboard loads correctly',
                    'priority' => 'critical',
                    'severity' => 'blocker',
                    'type' => 'smoke',
                    'tags' => ['smoke', 'P1'],
                ],
                [
                    'title' => 'Verify statistics are accurate',
                    'priority' => 'high',
                    'severity' => 'major',
                    'type' => 'functional',
                ],
                [
                    'title' => 'Verify charts render correctly',
                    'priority' => 'medium',
                    'severity' => 'minor',
                    'type' => 'functional',
                ],
            ],
            default => [],
        };
    }
}
