<?php

namespace App\Enums;

enum SidebarCategory: string
{
    case Checklists = 'checklists';
    case TestSuites = 'test-suites';
    case TestRuns = 'test-runs';
    case Bugreports = 'bugreports';
    case Design = 'design';
    case Automation = 'automation';
    case Releases = 'releases';
    case TestCoverage = 'test-coverage';
    case AiGenerator = 'ai-generator';
    case TestData = 'test-data';
    case PayoutMonitor = 'payout-monitor';
    case BalanceCalculator = 'balance-calculator';
    case Documentations = 'documentations';
    case Notes = 'notes';

    public function label(): string
    {
        return match ($this) {
            self::Checklists => 'Checklists',
            self::TestSuites => 'Test Suites',
            self::TestRuns => 'Test Runs',
            self::Bugreports => 'Bugreports',
            self::Design => 'Design',
            self::Automation => 'Automation',
            self::Releases => 'Releases',
            self::TestCoverage => 'Test Coverage',
            self::AiGenerator => 'AI Generator',
            self::TestData => 'Test Data',
            self::PayoutMonitor => 'Payout Monitor',
            self::BalanceCalculator => 'Balance Calculator',
            self::Documentations => 'Documentations',
            self::Notes => 'Notes',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
