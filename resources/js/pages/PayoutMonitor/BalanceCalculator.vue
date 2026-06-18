<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Calculator,
    ChevronUp,
    ChevronDown,
    TrendingUp,
    ArrowRight,
    RotateCcw,
    Wallet,
    CircleDollarSign,
    Layers,
    Zap,
    Trash2,
    Save,
    History,
    Play,
    Clock,
    UserCircle,
    RefreshCw,
} from 'lucide-vue-next';
import { ref, reactive, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';

const props = defineProps<{
    project: Project;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Balance Calculator', href: `/projects/${props.project.id}/balance-calculator` },
];

// ── Types ──────────────────────────────────────────────────────────────────
type TabKey = 'active' | 'activeXL';

interface PriorityItem {
    id: 'overspending' | 'commission' | 'debt' | 'advancedUsed' | 'blockedAdvanced';
    label: string;
    sublabel: string;
    percentage: string;
}

interface TabState {
    approvedFunds: string;
    bonusFunds: string;
    advancedAvailable: string;
    advancedUsed: string;
    blockedAdvanced: string;
    frozenFunds: string;
    activeAvailable: string;
    activeCommission: string;
    activeDebt: string;
    activeOverspending: string;
    advancedIncome: string;
    income: string;
    priorityDistribution: string;
    priorityDistributionPct: string;
    priorities: PriorityItem[];
    factorRate: string;
    repaymentPercentage: string;
}

// ── Default priorities ─────────────────────────────────────────────────────
const defaultPrioritiesBase: PriorityItem[] = [
    { id: 'commission', label: 'Commission', sublabel: 'Active Funds · Used', percentage: '' },
    { id: 'overspending', label: 'Overspending', sublabel: 'Active Funds', percentage: '' },
    { id: 'debt', label: 'Debt Body', sublabel: 'Active Funds · Used', percentage: '' },
    { id: 'advancedUsed', label: 'Advanced Funds Used', sublabel: 'Advanced Funds', percentage: '' },
];

const defaultPrioritiesXL: PriorityItem[] = [
    ...defaultPrioritiesBase.filter((p) => p.id !== 'overspending').map((p) => ({ ...p })),
    { id: 'blockedAdvanced', label: 'Blocked Advanced Funds', sublabel: '% of Advanced Funds Income', percentage: '' },
];

function freshState(tab: TabKey): TabState {
    const priorities = tab === 'activeXL'
        ? defaultPrioritiesXL.map((p) => ({ ...p }))
        : defaultPrioritiesBase.map((p) => ({ ...p }));
    return {
        approvedFunds: '',
        bonusFunds: '',
        advancedAvailable: '',
        advancedUsed: '',
        blockedAdvanced: '',
        frozenFunds: '',
        activeAvailable: '',
        activeCommission: '',
        activeDebt: '',
        activeOverspending: '',
        advancedIncome: '',
        income: '',
        priorityDistribution: '',
        priorityDistributionPct: '',
        priorities,
        factorRate: '',
        repaymentPercentage: '',
    };
}

// ── Persistence ────────────────────────────────────────────────────────────
const STORAGE_KEY = 'balance-calculator-tabs';

function loadTabs(): Record<TabKey, TabState> {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw) as Record<TabKey, TabState>;
            return {
                active: { ...freshState('active'), ...parsed.active },
                activeXL: { ...freshState('activeXL'), ...parsed.activeXL },
            };
        }
    } catch {}
    return { active: freshState('active'), activeXL: freshState('activeXL') };
}

// ── Tabs state ─────────────────────────────────────────────────────────────
const activeTab = ref<TabKey>('active');

const tabs = reactive<Record<TabKey, TabState>>(loadTabs());

const s = computed(() => tabs[activeTab.value]);

watch(tabs, (val) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
}, { deep: true });

function clearAll(): void {
    Object.assign(tabs[activeTab.value], freshState(activeTab.value));
    localStorage.setItem(STORAGE_KEY, JSON.stringify(tabs));
}

// ── Helpers ────────────────────────────────────────────────────────────────
function parseNum(val: string | number): number {
    if (typeof val === 'number') return isNaN(val) ? 0 : val;
    const n = parseFloat(String(val).replace(',', '.'));
    return isNaN(n) ? 0 : n;
}

function fmt(n: number): string {
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtPct(n: number): string {
    return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function balanceClass(n: number): string {
    if (n > 0) return 'text-emerald-600 dark:text-emerald-400';
    if (n < 0) return 'text-red-500 dark:text-red-400';
    return 'text-muted-foreground';
}

function deltaClass(original: number, updated: number): string {
    const d = updated - original;
    if (d > 0) return 'text-emerald-600 dark:text-emerald-400';
    if (d < 0) return 'text-red-500 dark:text-red-400';
    return 'text-muted-foreground';
}

function deltaLabel(original: number, updated: number): string {
    const d = updated - original;
    if (d === 0) return '—';
    return (d > 0 ? '+' : '') + fmt(d);
}

// ── Computed totals (reactive to current tab) ──────────────────────────────
const advancedTotal = computed(() => {
    const base = parseNum(s.value.advancedAvailable) - parseNum(s.value.advancedUsed) - parseNum(s.value.activeOverspending);
    if (activeTab.value === 'activeXL') {
        return base - parseNum(s.value.blockedAdvanced);
    }
    return base - parseNum(s.value.frozenFunds);
});
const activeUsedTotal = computed(
    () => parseNum(s.value.activeCommission) + parseNum(s.value.activeDebt),
);
const activeTotal = computed(() => activeUsedTotal.value);

// ── Priority controls ──────────────────────────────────────────────────────
function moveUp(index: number): void {
    if (index === 0) return;
    const arr = [...s.value.priorities];
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    tabs[activeTab.value].priorities = arr;
}

function moveDown(index: number): void {
    if (index === s.value.priorities.length - 1) return;
    const arr = [...s.value.priorities];
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    tabs[activeTab.value].priorities = arr;
}

function resetPriorities(): void {
    const defaults = activeTab.value === 'activeXL' ? defaultPrioritiesXL : defaultPrioritiesBase;
    tabs[activeTab.value].priorities = defaults.map((p) => ({ ...p }));
}

// ── Auto repayment (XL only) ───────────────────────────────────────────────
const autoRepayment = computed(() => {
    if (activeTab.value !== 'activeXL') return null;
    const fr = parseNum(s.value.factorRate);
    const rp = parseNum(s.value.repaymentPercentage);
    if (fr <= 0 || rp <= 0) return null;
    return {
        debtPct: rp / fr,
        commissionPct: rp - rp / fr,
    };
});

// ── Results ────────────────────────────────────────────────────────────────
const results = computed(() => {
    const isXL = activeTab.value === 'activeXL';
    const advancedIncomeVal = parseNum(s.value.advancedIncome);

    // Blocked Advanced: % of advancedIncome, calculated separately (not from remaining)
    const autoRep = autoRepayment.value;
    const blockedPriorityItem = s.value.priorities.find((p) => p.id === 'blockedAdvanced');
    const blockedPct = autoRep
        ? parseNum(s.value.repaymentPercentage)
        : (blockedPriorityItem ? parseNum(blockedPriorityItem.percentage) : 0);
    const blockedFromIncome = isXL && blockedPct > 0 ? advancedIncomeVal * (blockedPct / 100) : 0;
    const totalBlocked = parseNum(s.value.blockedAdvanced) + blockedFromIncome;

    const incomeVal = parseNum(s.value.income);
    const priorityDistributionVal = !isXL ? parseNum(s.value.priorityDistribution) : 0;
    const priorityDistributionPctVal = !isXL ? parseNum(s.value.priorityDistributionPct) : 0;
    const priorityDistributionFromPct = priorityDistributionPctVal > 0 ? incomeVal * (priorityDistributionPctVal / 100) : 0;
    const totalPriorityDistribution = priorityDistributionVal + priorityDistributionFromPct;
    const distributionBase = totalPriorityDistribution > 0 ? totalPriorityDistribution : incomeVal;
    const directApprovedIncome = totalPriorityDistribution > 0 ? Math.max(0, incomeVal - totalPriorityDistribution) : 0;
    let remaining = distributionBase;
    let newOverspending = parseNum(s.value.activeOverspending);
    let newCommission = parseNum(s.value.activeCommission);
    let newDebt = parseNum(s.value.activeDebt);
    let newAdvancedUsed = parseNum(s.value.advancedUsed);

    // Pre-calculate distribution-based allocations for commission and debt
    const commissionPriorityItem = s.value.priorities.find((p) => p.id === 'commission');
    const debtPriorityItem = s.value.priorities.find((p) => p.id === 'debt');
    const commissionPct = autoRep ? autoRep.commissionPct : (commissionPriorityItem ? parseNum(commissionPriorityItem.percentage) : 0);
    const debtPct = autoRep ? autoRep.debtPct : (debtPriorityItem ? parseNum(debtPriorityItem.percentage) : 0);
    const commissionFromIncome = commissionPct > 0 ? distributionBase * (commissionPct / 100) : 0;
    const debtFromIncome = debtPct > 0 ? distributionBase * (debtPct / 100) : 0;

    const payments: Record<string, number> = {
        overspending: 0,
        commission: 0,
        debt: 0,
        advancedUsed: 0,
        blockedAdvanced: 0,
    };

    for (const p of s.value.priorities) {
        if (remaining <= 0) break;
        if (p.id === 'blockedAdvanced') continue;

        const pct = parseNum(p.percentage);
        const effectivePct = (autoRep && (p.id === 'commission' || p.id === 'debt'))
            ? (p.id === 'commission' ? autoRep.commissionPct : autoRep.debtPct)
            : pct;

        if (p.id === 'commission') {
            const budget   = effectivePct > 0 ? Math.min(remaining, distributionBase * (effectivePct / 100)) : remaining;
            const commPaid = Math.min(budget, Math.max(0, newCommission));
            payments.commission += commPaid;
            newCommission -= commPaid;
            remaining     -= commPaid;
            continue;
        }

        if (p.id === 'debt') {
            const budget   = effectivePct > 0 ? Math.min(remaining, distributionBase * (effectivePct / 100)) : remaining;
            const debtPaid = Math.min(budget, Math.max(0, newDebt));
            payments.debt += debtPaid;
            newDebt       -= debtPaid;
            remaining     -= debtPaid;
            continue;
        }

        // overspending & advancedUsed: % limits what portion of the balance is repaid
        let amount: number;
        switch (p.id) {
            case 'overspending': {
                const factor = (pct > 0 && pct <= 100) ? pct / 100 : 1;
                amount = Math.max(0, newOverspending) * factor;
                break;
            }
            case 'advancedUsed': {
                const factor = (pct > 0 && pct <= 100) ? pct / 100 : 1;
                amount = Math.max(0, newAdvancedUsed) * factor;
                break;
            }
            default: amount = 0;
        }

        const paid = Math.min(remaining, amount);
        payments[p.id] += paid;
        remaining -= paid;

        switch (p.id) {
            case 'overspending': newOverspending -= paid; break;
            case 'advancedUsed': newAdvancedUsed -= paid; break;
        }
    }

    payments.blockedAdvanced = blockedFromIncome;

    const newActiveUsed = newCommission + newDebt;
    const newActiveTotal = newActiveUsed;
    const advancedAvailableVal = parseNum(s.value.advancedAvailable);
    const frozenVal = isXL ? 0 : parseNum(s.value.frozenFunds);
    const newAdvancedTotal = advancedAvailableVal + advancedIncomeVal - newAdvancedUsed - newOverspending - (isXL ? totalBlocked : 0) - frozenVal;
    const newApproved = parseNum(s.value.approvedFunds) + remaining + directApprovedIncome;

    return {
        approvedFunds: newApproved,
        advancedAvailable: advancedIncomeVal,
        advancedUsed: newAdvancedUsed,
        blockedAdvanced: totalBlocked,
        blockedFromIncome,
        commissionFromIncome,
        debtFromIncome,
        advancedTotal: newAdvancedTotal,
        activeAvailable: parseNum(s.value.activeAvailable),
        activeCommission: newCommission,
        activeDebt: newDebt,
        activeUsed: newActiveUsed,
        activeOverspending: newOverspending,
        activeTotal: newActiveTotal,
        remainingIncome: remaining,
        payments,
        grandTotal: newApproved + newAdvancedTotal + (parseNum(s.value.activeAvailable) - newDebt) + parseNum(s.value.bonusFunds),
    };
});

const priorityBadgeColors: Record<string, string> = {
    overspending: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    commission: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    debt: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    advancedUsed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    blockedAdvanced: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
};

// ── Accrual History ────────────────────────────────────────────────────────
interface ResultsSnapshot {
    approvedFunds: number;
    advancedTotal: number;
    activeTotal: number;
    grandTotal: number;
}

interface AccrualRecord {
    id: string;
    partnerId: string;
    tabType: TabKey;
    state: TabState;
    snapshot: ResultsSnapshot;
    createdAt: string;
    updatedAt: string;
}

const HISTORY_KEY = 'balance-calculator-history';

function loadHistory(): AccrualRecord[] {
    try {
        const raw = localStorage.getItem(HISTORY_KEY);
        return raw ? (JSON.parse(raw) as AccrualRecord[]) : [];
    } catch { return []; }
}

const accrualHistory = ref<AccrualRecord[]>(loadHistory());
const showHistory = ref(false);
const continuingRecordId = ref<string | null>(null);
const partnerIds = reactive<Record<TabKey, string>>({ active: '', activeXL: '' });
const showSaveForm = reactive<Record<TabKey, boolean>>({ active: false, activeXL: false });

function persistHistory(): void {
    localStorage.setItem(HISTORY_KEY, JSON.stringify(accrualHistory.value));
}

function saveRecord(): void {
    const tab = activeTab.value;
    const partnerId = partnerIds[tab].trim();
    if (!partnerId) return;

    const now = new Date().toISOString();
    const snap: ResultsSnapshot = {
        approvedFunds: results.value.approvedFunds,
        advancedTotal: results.value.advancedTotal,
        activeTotal: results.value.activeTotal,
        grandTotal: results.value.grandTotal,
    };

    if (continuingRecordId.value) {
        const idx = accrualHistory.value.findIndex((r) => r.id === continuingRecordId.value);
        if (idx >= 0) {
            accrualHistory.value[idx] = {
                ...accrualHistory.value[idx],
                state: { ...s.value, priorities: s.value.priorities.map((p) => ({ ...p })) },
                snapshot: snap,
                updatedAt: now,
            };
            persistHistory();
            showSaveForm[tab] = false;
            return;
        }
    }

    const record: AccrualRecord = {
        id: `${tab}-${now}-${Math.random().toString(36).slice(2, 7)}`,
        partnerId,
        tabType: tab,
        state: { ...s.value, priorities: s.value.priorities.map((p) => ({ ...p })) },
        snapshot: snap,
        createdAt: now,
        updatedAt: now,
    };
    accrualHistory.value.unshift(record);
    persistHistory();
    showSaveForm[tab] = false;
}

function continueRecord(record: AccrualRecord): void {
    Object.assign(tabs[record.tabType], {
        ...record.state,
        priorities: record.state.priorities.map((p) => ({ ...p })),
    });
    partnerIds[record.tabType] = record.partnerId;
    activeTab.value = record.tabType;
    showHistory.value = false;
    continuingRecordId.value = record.id;
}

function stopContinuing(): void {
    continuingRecordId.value = null;
}

function deleteRecord(id: string): void {
    accrualHistory.value = accrualHistory.value.filter((r) => r.id !== id);
    if (continuingRecordId.value === id) continuingRecordId.value = null;
    persistHistory();
}

function fmtDateTime(iso: string): string {
    return new Date(iso).toLocaleString('uk-UA', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

const groupedHistory = computed(() => {
    const map = new Map<string, AccrualRecord[]>();
    for (const record of accrualHistory.value) {
        if (!map.has(record.partnerId)) map.set(record.partnerId, []);
        map.get(record.partnerId)!.push(record);
    }
    return map;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Balance Calculator" />

        <div class="flex flex-1 flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <Calculator class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold">Balance Calculator</h1>
                    <p class="text-sm text-muted-foreground">Simulate income accrual and see how balances change</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex items-center justify-between border-b">
                <div class="flex gap-0">
                    <button
                        class="relative px-5 py-2.5 text-sm font-medium transition-colors cursor-pointer"
                        :class="!showHistory && activeTab === 'active'
                            ? 'text-foreground after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-primary'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'active'; showHistory = false"
                    >
                        Active Funds
                    </button>
                    <button
                        class="relative px-5 py-2.5 text-sm font-medium transition-colors cursor-pointer"
                        :class="!showHistory && activeTab === 'activeXL'
                            ? 'text-foreground after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-primary'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'activeXL'; showHistory = false"
                    >
                        Active Funds XL
                    </button>
                    <button
                        class="relative px-5 py-2.5 text-sm font-medium transition-colors cursor-pointer flex items-center gap-1.5"
                        :class="showHistory
                            ? 'text-foreground after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-primary'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="showHistory = true"
                    >
                        <History class="h-3.5 w-3.5" />
                        Accrual History
                        <span v-if="accrualHistory.length > 0" class="ml-1 rounded-full bg-primary/15 px-1.5 py-0.5 text-xs font-medium text-primary">
                            {{ accrualHistory.length }}
                        </span>
                    </button>
                </div>
                <div v-if="!showHistory" class="mb-1 flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5 text-muted-foreground hover:text-primary hover:border-primary cursor-pointer"
                        @click="showSaveForm[activeTab] = !showSaveForm[activeTab]"
                    >
                        <Save class="h-3.5 w-3.5" />
                        Save
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5 text-muted-foreground hover:text-destructive hover:border-destructive cursor-pointer"
                        @click="clearAll"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        Clear all
                    </Button>
                </div>
            </div>

            <!-- Save form -->
            <div v-if="!showHistory && showSaveForm[activeTab]" class="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3">
                <UserCircle class="h-4 w-4 shrink-0 text-muted-foreground" />
                <Input
                    v-model="partnerIds[activeTab]"
                    type="text"
                    placeholder="Enter Partner ID"
                    class="h-8 max-w-xs font-mono text-sm"
                    @keydown.enter="saveRecord"
                />
                <span v-if="continuingRecordId" class="text-xs text-muted-foreground">
                    Updating existing record
                </span>
                <Button size="sm" class="gap-1.5 cursor-pointer" :disabled="!partnerIds[activeTab].trim()" @click="saveRecord">
                    <Save class="h-3.5 w-3.5" />
                    {{ continuingRecordId ? 'Update' : 'Save' }}
                </Button>
                <Button v-if="continuingRecordId" variant="ghost" size="sm" class="gap-1.5 cursor-pointer text-muted-foreground" @click="stopContinuing">
                    <RefreshCw class="h-3.5 w-3.5" />
                    New record
                </Button>
            </div>

            <!-- Continuing banner -->
            <div v-if="!showHistory && continuingRecordId" class="flex items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-4 py-2.5">
                <Play class="h-3.5 w-3.5 text-primary" />
                <span class="text-sm text-primary">
                    Continuing from partner <strong>{{ partnerIds[activeTab] }}</strong>
                </span>
                <button class="ml-auto text-xs text-muted-foreground underline cursor-pointer hover:text-foreground" @click="stopContinuing">
                    Stop continuing
                </button>
            </div>

            <!-- ── Accrual History tab ──────────────────────────────────────── -->
            <div v-if="showHistory">
                <div v-if="accrualHistory.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                    <History class="mb-3 h-8 w-8 text-muted-foreground/40" />
                    <p class="text-sm font-medium text-muted-foreground">No saved records yet</p>
                    <p class="mt-1 text-xs text-muted-foreground/60">Save calculations from Active Funds or Active Funds XL tabs</p>
                </div>
                <div v-else class="space-y-6">
                    <div v-for="[partnerId, records] in groupedHistory" :key="partnerId" class="space-y-3">
                        <!-- Partner header -->
                        <div class="flex items-center gap-2">
                            <UserCircle class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-semibold">{{ partnerId }}</span>
                            <span class="text-xs text-muted-foreground">({{ records.length }} record{{ records.length > 1 ? 's' : '' }})</span>
                        </div>
                        <!-- Records -->
                        <div class="space-y-2">
                            <div
                                v-for="record in records"
                                :key="record.id"
                                class="rounded-lg border bg-card p-4"
                                :class="continuingRecordId === record.id ? 'border-primary/40 bg-primary/5' : ''"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="record.tabType === 'activeXL' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'">
                                            {{ record.tabType === 'activeXL' ? 'Active Funds XL' : 'Active Funds' }}
                                        </span>
                                        <span v-if="continuingRecordId === record.id" class="rounded-md bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary">
                                            Continuing
                                        </span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 gap-1.5 px-2.5 text-xs cursor-pointer"
                                            @click="continueRecord(record)"
                                        >
                                            <Play class="h-3 w-3" />
                                            Continue
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 cursor-pointer text-muted-foreground hover:text-destructive"
                                            @click="deleteRecord(record.id)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                <!-- Key numbers -->
                                <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs text-muted-foreground">Approved</span>
                                        <span class="font-mono text-xs font-medium" :class="balanceClass(record.snapshot?.approvedFunds ?? 0)">{{ fmt(record.snapshot?.approvedFunds ?? 0) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs text-muted-foreground">Advanced Total</span>
                                        <span class="font-mono text-xs font-medium" :class="balanceClass(record.snapshot?.advancedTotal ?? 0)">{{ fmt(record.snapshot?.advancedTotal ?? 0) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs text-muted-foreground">Active Total</span>
                                        <span class="font-mono text-xs font-medium text-red-500 dark:text-red-400">−{{ fmt(record.snapshot?.activeTotal ?? 0) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2 py-1">
                                        <span class="text-xs font-medium">Grand Total</span>
                                        <span class="font-mono text-xs font-bold" :class="balanceClass(record.snapshot?.grandTotal ?? 0)">{{ fmt(record.snapshot?.grandTotal ?? 0) }}</span>
                                    </div>
                                </div>
                                <!-- Timestamps -->
                                <div class="mt-3 flex flex-wrap items-center gap-3 border-t pt-2.5">
                                    <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Clock class="h-3 w-3" />
                                        Created: {{ fmtDateTime(record.createdAt) }}
                                    </div>
                                    <div v-if="record.updatedAt !== record.createdAt" class="flex items-center gap-1 text-xs text-muted-foreground">
                                        <RefreshCw class="h-3 w-3" />
                                        Updated: {{ fmtDateTime(record.updatedAt) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab content -->
            <div v-if="!showHistory" class="grid gap-6 xl:grid-cols-2">

                <!-- ── Block 1: Current Balances ─────────────────────────────── -->
                <Card>
                    <CardHeader class="pb-4">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Wallet class="h-4 w-4 text-muted-foreground" />
                            Current Balances
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">

                        <!-- Approved Funds -->
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                                <Label class="text-sm font-medium">Approved Funds</Label>
                            </div>
                            <Input
                                v-model="s.approvedFunds"
                                type="text"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="font-mono"
                            />
                        </div>

                        <!-- Bonus Funds -->
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full bg-amber-400"></div>
                                <Label class="text-sm font-medium">Bonus Funds</Label>
                            </div>
                            <Input
                                v-model="s.bonusFunds"
                                type="text"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="font-mono"
                            />
                        </div>

                        <Separator />

                        <!-- Advanced Funds -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                                    <Label class="text-sm font-medium">Advanced Funds</Label>
                                </div>
                                <span class="font-mono text-sm font-semibold" :class="balanceClass(advancedTotal)">
                                    {{ fmt(advancedTotal) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 rounded-lg bg-muted/40 p-3">
                                <div class="space-y-1.5">
                                    <Label class="text-xs text-muted-foreground">Available</Label>
                                    <Input
                                        v-model="s.advancedAvailable"
                                        type="text"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                        class="h-8 font-mono text-sm"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs text-muted-foreground">Used</Label>
                                    <Input
                                        v-model="s.advancedUsed"
                                        type="text"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                        class="h-8 font-mono text-sm"
                                    />
                                </div>
                                <template v-if="activeTab === 'activeXL'">
                                    <div class="col-span-2 space-y-1.5">
                                        <Label class="text-xs text-muted-foreground">Overspending</Label>
                                        <Input
                                            v-model="s.activeOverspending"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="h-8 font-mono text-sm"
                                        />
                                    </div>
                                    <div class="col-span-2 space-y-1.5">
                                        <Label class="text-xs text-muted-foreground">Blocked Advanced Funds</Label>
                                        <Input
                                            v-model="s.blockedAdvanced"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="h-8 font-mono text-sm"
                                        />
                                    </div>
                                </template>
                                <template v-if="activeTab === 'active'">
                                    <div class="col-span-2 space-y-1.5">
                                        <Label class="text-xs text-muted-foreground">Overspending</Label>
                                        <Input
                                            v-model="s.activeOverspending"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="h-8 font-mono text-sm"
                                        />
                                    </div>
                                    <div class="col-span-2 space-y-1.5">
                                        <Label class="text-xs text-muted-foreground">Frozen Funds</Label>
                                        <Input
                                            v-model="s.frozenFunds"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="h-8 font-mono text-sm"
                                        />
                                    </div>
                                </template>
                                <div class="col-span-2 flex items-center justify-between border-t pt-2">
                                    <span class="text-xs text-muted-foreground">
                                        {{ activeTab === 'activeXL' ? 'Total = Available − Used − Overspending − Blocked' : 'Total = Available − Used − Overspending − Frozen' }}
                                    </span>
                                    <span class="font-mono text-xs font-medium" :class="balanceClass(advancedTotal)">
                                        {{ fmt(advancedTotal) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <!-- Active Funds -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full bg-violet-500"></div>
                                    <Label class="text-sm font-medium">Active Funds</Label>
                                </div>
                                <span class="font-mono text-sm font-semibold text-red-500 dark:text-red-400">
                                    −{{ fmt(activeTotal) }}
                                </span>
                            </div>
                            <div class="space-y-3 rounded-lg bg-muted/40 p-3">
                                <!-- Available -->
                                <div class="space-y-1.5">
                                    <Label class="text-xs text-muted-foreground">Available</Label>
                                    <Input
                                        v-model="s.activeAvailable"
                                        type="text"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                        class="h-8 font-mono text-sm"
                                    />
                                </div>

                                <!-- Used breakdown -->
                                <div class="space-y-2 rounded-md border bg-background p-2.5">
                                    <div class="flex items-center justify-between">
                                        <Label class="text-xs font-medium">Used</Label>
                                        <span class="font-mono text-xs text-muted-foreground">{{ fmt(activeUsedTotal) }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="space-y-1">
                                            <Label class="text-xs text-muted-foreground">Debt Body</Label>
                                            <Input
                                                v-model="s.activeDebt"
                                                type="text"
                                                inputmode="decimal"
                                                placeholder="0.00"
                                                class="h-8 font-mono text-sm"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs text-muted-foreground">Commission</Label>
                                            <Input
                                                v-model="s.activeCommission"
                                                type="text"
                                                inputmode="decimal"
                                                placeholder="0.00"
                                                class="h-8 font-mono text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>


                                <div class="flex items-center justify-between border-t pt-2">
                                    <span class="text-xs text-muted-foreground">Total Used = Commission + Debt Body</span>
                                    <span class="font-mono text-xs font-medium text-red-500 dark:text-red-400">
                                        −{{ fmt(activeTotal) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                    </CardContent>
                </Card>

                <!-- Right column: Income + Priority -->
                <div class="flex flex-col gap-6">

                    <!-- ── Block 2: Income Accrual ─────────────────────────────── -->
                    <Card>
                        <CardHeader class="pb-4">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <TrendingUp class="h-4 w-4 text-muted-foreground" />
                                Income Accrual
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <template v-if="activeTab === 'activeXL'">
                                    <div class="space-y-2">
                                        <Label>Advanced Funds Income</Label>
                                        <Input
                                            v-model="s.advancedIncome"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="font-mono text-lg"
                                        />
                                    </div>
                                </template>
                                <div class="space-y-2">
                                    <Label>Income</Label>
                                    <Input
                                        v-model="s.income"
                                        type="text"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                        class="font-mono text-lg"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Amount to be distributed across balances per the repayment priority below.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ── Block 3: Repayment Priority ──────────────────────────── -->
                    <Card>
                        <CardHeader class="pb-4">
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Layers class="h-4 w-4 text-muted-foreground" />
                                    Repayment Priority
                                </CardTitle>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1.5 px-2 text-xs text-muted-foreground cursor-pointer"
                                    @click="resetPriorities"
                                >
                                    <RotateCcw class="h-3 w-3" />
                                    Reset
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <!-- Priority Distribution (Active Funds only) -->
                                <template v-if="activeTab === 'active'">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="space-y-1.5">
                                            <Label class="text-sm">Priority Distribution Amount</Label>
                                            <Input
                                                v-model="s.priorityDistribution"
                                                type="text"
                                                inputmode="decimal"
                                                placeholder="0.00"
                                                class="font-mono"
                                            />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-sm">Priority Distribution %</Label>
                                            <div class="relative">
                                                <Input
                                                    v-model="s.priorityDistributionPct"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0"
                                                    class="font-mono pr-7"
                                                />
                                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-muted-foreground">Both are subtracted from income and distributed via priorities. Remainder goes to Approved Funds.</p>
                                    <Separator />
                                </template>
                                <template v-if="activeTab === 'activeXL'">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="space-y-1.5">
                                            <Label class="text-sm">Factor Rate</Label>
                                            <Input
                                                v-model="s.factorRate"
                                                type="text"
                                                inputmode="decimal"
                                                placeholder="0,00"
                                                class="font-mono"
                                            />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-sm">Repayment Percentage</Label>
                                            <div class="relative">
                                                <Input
                                                    v-model="s.repaymentPercentage"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0"
                                                    class="font-mono pr-7"
                                                />
                                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <Separator />
                                </template>
                                <div class="space-y-2">
                                <div
                                    v-for="(item, index) in s.priorities"
                                    :key="item.id"
                                    class="flex items-center gap-3 rounded-lg border bg-card px-3 py-2.5 transition-colors"
                                >
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                                        {{ index + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium leading-none">{{ item.label }}</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">{{ item.sublabel }}</p>
                                    </div>
                                    <!-- Percentage input -->
                                    <div class="flex shrink-0 items-center gap-1">
                                        <template v-if="autoRepayment && (item.id === 'commission' || item.id === 'debt' || item.id === 'blockedAdvanced')">
                                            <span class="flex h-7 w-14 items-center justify-center rounded-md border bg-muted px-2 text-center font-mono text-xs font-medium">
                                                {{ item.id === 'debt' ? fmtPct(autoRepayment.debtPct) : item.id === 'commission' ? fmtPct(autoRepayment.commissionPct) : fmtPct(parseNum(s.repaymentPercentage)) }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <Input
                                                v-model="item.percentage"
                                                type="text"
                                                inputmode="decimal"
                                                :placeholder="item.id === 'blockedAdvanced' ? '0' : '100'"
                                                class="h-7 w-14 px-2 text-center font-mono text-xs"
                                            />
                                        </template>
                                        <span class="text-xs text-muted-foreground">%</span>
                                    </div>
                                    <span
                                        class="shrink-0 rounded-md px-1.5 py-0.5 text-xs font-medium"
                                        :class="priorityBadgeColors[item.id]"
                                    >
                                        {{ index === 0 ? '1st' : index === 1 ? '2nd' : index === 2 ? '3rd' : `${index + 1}th` }}
                                    </span>
                                    <div class="flex shrink-0 flex-col gap-0.5">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-5 w-5 p-0 cursor-pointer"
                                            :disabled="index === 0"
                                            @click="moveUp(index)"
                                        >
                                            <ChevronUp class="h-3 w-3" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-5 w-5 p-0 cursor-pointer"
                                            :disabled="index === s.priorities.length - 1"
                                            @click="moveDown(index)"
                                        >
                                            <ChevronDown class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ── Block 4: Results ──────────────────────────────────────────── -->
            <Card v-if="!showHistory" class="border-primary/20">
                <CardHeader class="pb-4">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Zap class="h-4 w-4 text-primary" />
                        Balances After Income Applied
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-3">

                        <!-- Approved Funds Result -->
                        <div class="rounded-lg border bg-muted/20 p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                                <span class="text-sm font-medium">Approved Funds</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Balance</span>
                                    <span class="font-mono text-sm font-semibold" :class="balanceClass(results.approvedFunds)">
                                        {{ fmt(results.approvedFunds) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Change</span>
                                    <span class="font-mono text-xs" :class="deltaClass(parseNum(s.approvedFunds), results.approvedFunds)">
                                        {{ deltaLabel(parseNum(s.approvedFunds), results.approvedFunds) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Funds Result -->
                        <div class="rounded-lg border bg-muted/20 p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                                <span class="text-sm font-medium">Advanced Funds</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Total</span>
                                    <span class="font-mono text-sm font-semibold" :class="balanceClass(results.advancedTotal)">
                                        {{ fmt(results.advancedTotal) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Change</span>
                                    <span class="font-mono text-xs" :class="deltaClass(advancedTotal, results.advancedTotal)">
                                        {{ deltaLabel(advancedTotal, results.advancedTotal) }}
                                    </span>
                                </div>
                                <Separator class="my-1" />
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Advanced Funds Income</span>
                                    <span class="font-mono text-xs">{{ fmt(results.advancedAvailable) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Used</span>
                                    <span class="font-mono text-xs" :class="balanceClass(-results.advancedUsed)">
                                        {{ fmt(results.advancedUsed) }}
                                    </span>
                                </div>
                                <div v-if="results.payments.advancedUsed > 0" class="flex items-center justify-between">
                                    <span class="text-xs text-emerald-600 dark:text-emerald-400">Repaid</span>
                                    <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                        −{{ fmt(results.payments.advancedUsed) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Overspending</span>
                                    <div class="flex items-center gap-1.5">
                                        <span v-if="results.payments.overspending > 0" class="font-mono text-xs text-emerald-600 dark:text-emerald-400">−{{ fmt(results.payments.overspending) }}</span>
                                        <span class="font-mono text-xs" :class="results.activeOverspending > 0 ? 'text-red-500 dark:text-red-400' : ''">
                                            {{ fmt(results.activeOverspending) }}
                                        </span>
                                    </div>
                                </div>
                                <template v-if="activeTab === 'activeXL' && results.blockedAdvanced > 0">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-muted-foreground">Blocked</span>
                                        <span class="font-mono text-xs text-purple-600 dark:text-purple-400">
                                            −{{ fmt(results.blockedAdvanced) }}
                                        </span>
                                    </div>
                                    <div v-if="results.blockedFromIncome > 0" class="flex items-center justify-between">
                                        <span class="text-xs text-muted-foreground pl-2">↳ from income</span>
                                        <span class="font-mono text-xs text-purple-500 dark:text-purple-300">
                                            {{ fmt(results.blockedFromIncome) }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Active Funds Result -->
                        <div class="rounded-lg border bg-muted/20 p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full bg-violet-500"></div>
                                <span class="text-sm font-medium">Active Funds</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Total Used</span>
                                    <span class="font-mono text-sm font-semibold text-red-500 dark:text-red-400">
                                        −{{ fmt(results.activeTotal) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Change</span>
                                    <span class="font-mono text-xs" :class="deltaClass(-activeTotal, -results.activeTotal)">
                                        {{ deltaLabel(-activeTotal, -results.activeTotal) }}
                                    </span>
                                </div>
                                <Separator class="my-1" />
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Debt Body</span>
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-mono text-xs">{{ fmt(results.activeDebt) }}</span>
                                        <div v-if="results.payments.debt > 0" class="flex items-center gap-1">
                                            <span class="text-xs text-muted-foreground">repaid:</span>
                                            <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">−{{ fmt(results.payments.debt) }}</span>
                                        </div>
                                        <div v-if="results.debtFromIncome > 0" class="flex items-center gap-1">
                                            <span class="text-xs text-muted-foreground">from income:</span>
                                            <span class="font-mono text-xs text-blue-600 dark:text-blue-400">{{ fmt(results.debtFromIncome) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">Commission</span>
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-mono text-xs">{{ fmt(results.activeCommission) }}</span>
                                        <div v-if="results.payments.commission > 0" class="flex items-center gap-1">
                                            <span class="text-xs text-muted-foreground">repaid:</span>
                                            <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">−{{ fmt(results.payments.commission) }}</span>
                                        </div>
                                        <div v-if="results.commissionFromIncome > 0" class="flex items-center gap-1">
                                            <span class="text-xs text-muted-foreground">from income:</span>
                                            <span class="font-mono text-xs text-blue-600 dark:text-blue-400">{{ fmt(results.commissionFromIncome) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between rounded-md bg-muted/40 px-2 py-1">
                                    <span class="text-xs text-muted-foreground">New Available</span>
                                    <span class="font-mono text-xs font-medium" :class="balanceClass(results.activeAvailable - results.activeDebt)">
                                        {{ fmt(results.activeAvailable - results.activeDebt) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary row -->
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <!-- Repayment breakdown -->
                        <div class="sm:col-span-2 rounded-lg border border-dashed bg-muted/10 p-3">
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Repayment Breakdown</p>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="p in s.priorities"
                                    :key="p.id"
                                    class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5"
                                >
                                    <ArrowRight class="h-3 w-3 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">{{ p.label }}:</span>
                                    <span
                                        class="font-mono text-xs font-medium"
                                        :class="results.payments[p.id] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                                    >
                                        {{ results.payments[p.id] > 0 ? fmt(results.payments[p.id]) : '—' }}
                                    </span>
                                </div>
                                <div v-if="results.remainingIncome > 0" class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5">
                                    <ArrowRight class="h-3 w-3 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">Approved Funds:</span>
                                    <span class="font-mono text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        +{{ fmt(results.remainingIncome) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Grand Total -->
                        <div class="flex flex-col items-center justify-center rounded-lg border bg-primary/5 p-4 text-center">
                            <div class="mb-1 flex items-center gap-1.5">
                                <CircleDollarSign class="h-4 w-4 text-primary" />
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Grand Total</span>
                            </div>
                            <span class="font-mono text-2xl font-bold" :class="balanceClass(results.grandTotal)">
                                {{ fmt(results.grandTotal) }}
                            </span>
                            <span class="mt-1 text-xs text-muted-foreground">All funds combined</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
