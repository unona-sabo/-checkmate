export interface Project {
    id: number;
    user_id: number;
    name: string;
    created_at: string;
    updated_at: string;
    checklists_count?: number;
    test_suites_count?: number;
    test_runs_count?: number;
    checklists?: Checklist[];
    test_suites?: TestSuite[];
    test_runs?: TestRun[];
}

export interface Checklist {
    id: number;
    project_id: number;
    name: string;
    columns_config: ColumnConfig[] | null;
    created_at: string;
    updated_at: string;
    rows_count?: number;
    rows?: ChecklistRow[];
    note?: ChecklistNote;
}

export interface SelectOption {
    value: string;
    label: string;
    color?: string;
}

export interface ColumnConfig {
    key: string;
    label: string;
    type: 'text' | 'checkbox' | 'select' | 'date';
    options?: SelectOption[];
    width?: number;
}

export interface ChecklistRow {
    id: number;
    checklist_id: number;
    data: Record<string, unknown>;
    order: number;
    row_type: 'normal' | 'section_header';
    background_color: string | null;
    font_color: string | null;
    font_weight: 'normal' | 'medium' | 'semibold' | 'bold';
    created_at: string;
    updated_at: string;
}

export interface ChecklistNote {
    id: number;
    checklist_id: number;
    content: string | null;
    created_at: string;
    updated_at: string;
}

export interface TestSuite {
    id: number;
    project_id: number;
    parent_id: number | null;
    name: string;
    description: string | null;
    order: number;
    created_at: string;
    updated_at: string;
    test_cases_count?: number;
    parent?: TestSuite;
    children?: TestSuite[];
    test_cases?: TestCase[];
}

export interface TestCase {
    id: number;
    test_suite_id: number;
    title: string;
    description: string | null;
    preconditions: string | null;
    steps: TestStep[] | null;
    expected_result: string | null;
    priority: 'low' | 'medium' | 'high' | 'critical';
    severity: 'trivial' | 'minor' | 'major' | 'critical' | 'blocker';
    type: 'functional' | 'smoke' | 'regression' | 'integration' | 'acceptance' | 'performance' | 'security' | 'usability' | 'other';
    automation_status: 'not_automated' | 'to_be_automated' | 'automated';
    tags: string[] | null;
    order: number;
    created_at: string;
    updated_at: string;
    test_suite?: TestSuite;
    note?: TestCaseNote;
}

export interface TestStep {
    action: string;
    expected: string | null;
}

export interface TestCaseNote {
    id: number;
    test_case_id: number;
    content: string | null;
    created_at: string;
    updated_at: string;
}

export interface TestRun {
    id: number;
    project_id: number;
    name: string;
    description: string | null;
    environment: string | null;
    milestone: string | null;
    status: 'active' | 'completed' | 'archived';
    progress: number;
    stats: TestRunStats | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    updated_at: string;
    test_run_cases_count?: number;
    test_run_cases?: TestRunCase[];
}

export interface TestRunStats {
    untested?: number;
    passed?: number;
    failed?: number;
    blocked?: number;
    skipped?: number;
    retest?: number;
}

export interface TestRunCase {
    id: number;
    test_run_id: number;
    test_case_id: number;
    status: 'untested' | 'passed' | 'failed' | 'blocked' | 'skipped' | 'retest';
    actual_result: string | null;
    time_spent: number | null;
    clickup_link: string | null;
    qase_link: string | null;
    assigned_to: number | null;
    tested_at: string | null;
    created_at: string;
    updated_at: string;
    test_case?: TestCase;
    assigned_user?: {
        id: number;
        name: string;
        email: string;
    };
}

export type TestRunCaseStatus = TestRunCase['status'];
export type TestCasePriority = TestCase['priority'];
export type TestCaseSeverity = TestCase['severity'];
export type TestCaseType = TestCase['type'];
export type TestCaseAutomationStatus = TestCase['automation_status'];
