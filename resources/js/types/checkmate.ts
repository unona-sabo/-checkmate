export interface CursorPagination<T> {
    data: T[];
    path: string;
    per_page: number;
    next_cursor: string | null;
    next_page_url: string | null;
    prev_cursor: string | null;
    prev_page_url: string | null;
}

export interface Attachment {
    id: number;
    original_filename: string;
    stored_path: string;
    mime_type: string;
    size: number;
    url: string;
    created_at: string;
    updated_at: string;
}

export interface Bugreport {
    id: number;
    project_id: number;
    title: string;
    status: 'new' | 'open' | 'in_progress' | 'resolved' | 'closed' | 'reopened';
    severity: 'blocker' | 'critical' | 'major' | 'minor' | 'trivial';
    priority: 'critical' | 'high' | 'medium' | 'low';
    created_at: string;
    updated_at: string;
}

export interface Documentation {
    id: number;
    project_id: number;
    title: string;
    category: string | null;
    order: number;
    parent_id: number | null;
    created_at: string;
    updated_at: string;
}

export interface Project {
    id: number;
    user_id: number;
    name: string;
    created_at: string;
    updated_at: string;
    checklists_count?: number;
    test_suites_count?: number;
    test_runs_count?: number;
    bugreports_count?: number;
    documentations_count?: number;
    checklists?: Checklist[];
    test_suites?: TestSuite[];
    test_runs?: TestRun[];
    bugreports?: Bugreport[];
    documentations?: Documentation[];
}

export interface Checklist {
    id: number;
    project_id: number;
    name: string;
    columns_config: ColumnConfig[] | null;
    order: number;
    category: string | null;
    created_at: string;
    updated_at: string;
    rows_count?: number;
    rows?: ChecklistRow[];
    section_headers?: Pick<ChecklistRow, 'id' | 'checklist_id' | 'data' | 'order'>[];
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
    type: string;
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
    created_by: number | null;
    created_at: string;
    updated_at: string;
    test_suite?: TestSuite;
    creator?: { id: number; name: string };
    note?: TestCaseNote;
    attachments?: Attachment[];
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
    source?: string | null;
    checklist_id?: number | null;
    checklist?: { id: number; name: string } | null;
    progress: number;
    stats: TestRunStats | null;
    started_at: string | null;
    completed_at: string | null;
    completed_by: number | null;
    created_by: number | null;
    paused_at: string | null;
    total_paused_seconds: number;
    elapsed_seconds?: number | null;
    is_paused?: boolean;
    created_at: string;
    updated_at: string;
    creator?: { id: number; name: string } | null;
    completed_by_user?: { id: number; name: string };
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
    test_case_id: number | null;
    title?: string | null;
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

export interface TestUser {
    id: number;
    project_id: number;
    name: string;
    email: string;
    password: string | null;
    role: string | null;
    environment: 'develop' | 'staging' | 'production' | null;
    description: string | null;
    is_valid: boolean;
    additional_info: Record<string, unknown> | null;
    tags: string[] | null;
    order: number;
    created_by: number | null;
    creator: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

export interface TestPaymentMethod {
    id: number;
    project_id: number;
    name: string;
    type: 'card' | 'crypto' | 'bank' | 'paypal' | 'other';
    system: string | null;
    credentials: Record<string, string> | null;
    environment: 'develop' | 'staging' | 'production' | null;
    is_valid: boolean;
    description: string | null;
    tags: string[] | null;
    order: number;
    created_by: number | null;
    creator: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

export interface Release {
    id: number;
    project_id: number;
    version: string;
    name: string;
    description: string | null;
    planned_date: string | null;
    actual_date: string | null;
    status: 'planning' | 'development' | 'testing' | 'staging' | 'ready' | 'released' | 'cancelled';
    health: 'green' | 'yellow' | 'red';
    decision: 'pending' | 'go' | 'no_go' | 'conditional';
    decision_notes: string | null;
    metadata: Record<string, unknown> | null;
    created_by: number | null;
    creator?: { id: number; name: string } | null;
    features_count?: number;
    checklist_items_count?: number;
    checklist_progress?: number;
    features?: ReleaseFeature[];
    checklist_items?: ReleaseChecklistItem[];
    metrics_snapshots?: ReleaseMetricsSnapshot[];
    latest_metrics?: ReleaseMetricsSnapshot | null;
    test_runs?: TestRun[];
    created_at: string;
    updated_at: string;
}

export interface ReleaseFeature {
    id: number;
    release_id: number;
    feature_id: number | null;
    feature_name: string;
    description: string | null;
    status: 'planned' | 'in_progress' | 'completed' | 'deferred';
    test_coverage_percentage: number;
    tests_planned: number;
    tests_executed: number;
    tests_passed: number;
    created_at: string;
    updated_at: string;
}

export interface ReleaseChecklistItem {
    id: number;
    release_id: number;
    category: string;
    title: string;
    description: string | null;
    status: 'pending' | 'in_progress' | 'completed' | 'na';
    priority: 'low' | 'medium' | 'high' | 'critical';
    is_blocker: boolean;
    assigned_to: number | null;
    assignee?: { id: number; name: string } | null;
    completed_at: string | null;
    notes: string | null;
    order: number;
    created_at: string;
    updated_at: string;
}

export interface ReleaseMetricsSnapshot {
    id: number;
    release_id: number;
    test_completion_percentage: number;
    test_pass_rate: number;
    total_bugs: number;
    critical_bugs: number;
    high_bugs: number;
    bug_closure_rate: number;
    regression_pass_rate: number;
    performance_score: number;
    security_status: string;
    snapshot_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface ProjectSearchResultItem {
    id: number;
    title: string;
    subtitle: string | null;
    badge: string | null;
    extra_badge?: string | null;
    url: string;
}

export interface ProjectSearchResultGroup {
    type: string;
    label: string;
    count: number;
    items: ProjectSearchResultItem[];
}

export interface ProjectSearchResponse {
    query: string;
    results: ProjectSearchResultGroup[];
    total: number;
}

export interface HomeSection {
    key: string;
    title: string;
    description: string;
    features: string[];
    author: string;
    count: number;
    latest_created_at: string | null;
    latest_updated_at: string | null;
}

export interface SectionFeature {
    id: number;
    section_key: string;
    feature_index: number;
    title: string;
    description: string | null;
    is_custom: boolean;
    created_by: number | null;
    updated_by: number | null;
    creator?: { id: number; name: string } | null;
    updater?: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

export interface Workspace {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
    role?: string;
    created_at?: string;
    updated_at?: string;
}

export interface WorkspaceMember {
    id: number;
    name: string;
    email: string;
    role: string;
}

export type TestRunCaseStatus = TestRunCase['status'];
export type TestCasePriority = TestCase['priority'];
export type TestCaseSeverity = TestCase['severity'];
export type TestCaseType = TestCase['type'];
export type TestCaseAutomationStatus = TestCase['automation_status'];

export interface ProjectFeature {
    id: number;
    project_id: number;
    name: string;
    description: string | null;
    module: string | null;
    category: string | null;
    priority: 'critical' | 'high' | 'medium' | 'low';
    is_active: boolean;
    test_cases_count?: number;
    test_cases?: { id: number; title: string; test_suite_id: number; test_suite?: { id: number; name: string } }[];
    created_at: string;
    updated_at: string;
}

export interface TestCaseSummary {
    id: number;
    title: string;
    test_suite?: { id: number; name: string };
}

export interface CoverageAnalysis {
    id: number;
    project_id: number;
    analysis_data: AIAnalysisData | null;
    overall_coverage: number | null;
    total_features: number | null;
    covered_features: number | null;
    total_test_cases: number | null;
    gaps_count: number | null;
    analyzed_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface AIAnalysisData {
    summary: string;
    overall_coverage: number;
    gaps: AIGap[];
    well_covered: AIWellCovered[];
    risks: AIRisk[];
    recommendations: AIRecommendation[];
    coverage_by_category: Record<string, number>;
}

export interface AIGap {
    id: string;
    feature: string;
    description: string;
    priority: string;
    category: string;
    module: string;
    suggested_test_count: number;
    reasoning: string;
}

export interface AIWellCovered {
    feature: string;
    module: string;
    test_count: number;
    coverage: number;
    strength: string;
}

export interface AIRisk {
    id: string;
    area: string;
    level: string;
    reason: string;
    impact: string;
    recommendation: string;
}

export interface AIRecommendation {
    priority: number;
    action: string;
    benefit: string;
    effort: string;
}

export interface CoverageModuleStats {
    module: string;
    total_features: number;
    covered_features: number;
    test_cases_count: number;
    coverage_percentage: number;
}

export interface CoverageStatistics {
    overall_coverage: number;
    total_features: number;
    covered_features: number;
    uncovered_features: number;
    total_test_cases: number;
    gaps_count: number;
}

export interface CoverageGap {
    id: number;
    feature: string;
    description: string | null;
    module: string | null;
    category: string | null;
    priority: string;
}

export interface AiGeneratedTestCase {
    id: number;
    project_id: number;
    feature_id: number | null;
    title: string;
    preconditions: string | null;
    test_steps: string[];
    expected_result: string;
    priority: string;
    type: string;
    is_approved: boolean;
    approved_by: number | null;
    approved_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface AutomationTestResult {
    id: number;
    project_id: number;
    test_case_id: number | null;
    environment_id: number | null;
    template_id: number | null;
    test_file: string;
    test_name: string;
    status: 'passed' | 'failed' | 'skipped' | 'timedout';
    duration_ms: number;
    error_message: string | null;
    error_stack: string[] | null;
    screenshot_path: string | null;
    video_path: string | null;
    tags: string[] | null;
    executed_at: string;
    created_at: string;
    updated_at: string;
    test_case?: TestCase;
    environment?: { id: number; name: string } | null;
}

export interface AutomationScanResult {
    tests_path: string;
    total_files: number;
    total_tests: number;
    all_tags: string[];
    files: AutomationScanFile[];
}

export interface AutomationScanFile {
    file: string;
    full_path: string;
    suite: string;
    tests: AutomationScanTest[];
    skipped_tests: string[];
}

export interface AutomationScanTest {
    name: string;
    full_name: string;
    tags: string[];
}

export interface AutomationRunStats {
    total: number;
    passed: number;
    failed: number;
    skipped: number;
    timedout: number;
    executed_at: string;
}

export interface TestEnvironment {
    id: number;
    project_id: number;
    name: string;
    base_url: string | null;
    variables: Record<string, string> | null;
    workers: number;
    retries: number;
    browser: 'chromium' | 'firefox' | 'webkit';
    headed: boolean;
    timeout: number;
    description: string | null;
    is_default: boolean;
    created_at: string;
    updated_at: string;
}

export interface TestRunTemplate {
    id: number;
    project_id: number;
    name: string;
    description: string | null;
    environment_id: number | null;
    environment?: { id: number; name: string } | null;
    tags: string[] | null;
    tag_mode: 'or' | 'and';
    file_pattern: string | null;
    options: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}
