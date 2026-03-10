<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Activity,
    RefreshCw,
    FileText,
    AlertTriangle,
    CheckCircle,
    XCircle,
    Clock,
    ChevronDown,
    ChevronRight,
    Search,
    Settings,
    X,
    ArrowRight,
    User,
    Globe,
    CreditCard,
    FileCode,
    Building,
} from 'lucide-vue-next';

interface PayoutEvent {
    timestamp: string | null;
    level: string;
    event_type: string;
    payload: Record<string, unknown>;
    collapsed?: boolean;
}

interface PayoutAnomaly {
    type: string;
    severity: 'info' | 'warning' | 'error';
    description: string;
}

interface SenderDetails {
    name?: string;
    business_name?: string;
    business_country?: string;
    address_country?: string;
    address?: string;
    city?: string;
    state?: string;
    zip?: string;
    phone?: string;
    phone_country?: string;
    email?: string;
    registration_type?: string;
    registration_number?: string;
    registration_issue_date?: string;
    registration_valid_thru?: string;
    country?: string;
    msisdn?: string;
}

interface RecipientDetails {
    first_name?: string;
    middle_name?: string;
    last_name?: string;
    full_name?: string;
    business_name?: string;
    business_country?: string;
    address_country?: string;
    bank_account?: string;
    iban?: string;
    bank_name?: string;
    address?: string;
    city?: string;
    state?: string;
    zip?: string;
    postal_code?: string;
    country?: string;
    receiving_country?: string;
    instrument_type?: string;
    msisdn?: string;
    phone?: string;
    phone_country?: string;
    email?: string;
    registration_type?: string;
    registration_number?: string;
    registration_issue_date?: string;
    registration_valid_thru?: string;
}

interface PayoutHighlights {
    beneficiary_country?: string;
    destination_currency?: string;
    payout_method?: string;
    beneficiary_name?: string;
    request_amount?: string;
    request_currency?: string;
    sending_amount?: string;
    sending_currency?: string;
    receiving_amount?: string;
    receiving_currency?: string;
    fx_rate?: string;
    quote_id?: string;
    send_amount?: string;
    send_currency?: string;
    transaction_id?: string;
    transaction_type?: string;
    account_number?: string;
    receiving_country?: string;
    instrument_type?: string;
    remittance_purpose?: string;
    source_of_funds?: string;
    sender_name?: string;
    sender_country?: string;
    sender_msisdn?: string;
    sender_business_name?: string;
    sender_business_country?: string;
    sender_address_country?: string;
    sender?: SenderDetails;
    recipient?: RecipientDetails;
}

interface PayoutGroup {
    payout_id: string;
    status: string;
    events: PayoutEvent[];
    categories: Record<string, PayoutEvent[]>;
    anomalies: PayoutAnomaly[];
    highlights: PayoutHighlights;
    event_count: number;
}

interface ParseResult {
    payouts: PayoutGroup[];
    summary: {
        total_payouts: number;
        total_events: number;
        errors: number;
        anomalies: number;
    };
}

const props = defineProps<{
    project: Project;
    isConfigured: boolean;
    logPath: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Payout Monitor', href: `/projects/${props.project.id}/payout-monitor` },
];

// localStorage key scoped to project
const storageKey = `payout-monitor-${props.project.id}`;

const loadSaved = (): { result: ParseResult | null; rawLog: string; activeTab: 'fetch' | 'paste' } => {
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            return JSON.parse(saved);
        }
    } catch {
        // ignore
    }
    return { result: null, rawLog: '', activeTab: 'fetch' };
};

const saved = loadSaved();

// State
const activeTab = ref<'fetch' | 'paste'>(saved.activeTab);
const loading = ref(false);
const error = ref('');
const result = ref<ParseResult | null>(saved.result);
const expandedPayouts = ref<Set<string>>(new Set(
    saved.result ? saved.result.payouts.slice(0, 3).map(p => String(p.payout_id)) : [],
));
const searchQuery = ref('');
const statusFilter = ref<string>('all');
const showRawData = ref<Set<string>>(new Set());

// Fetch Latest
const minutesBack = ref(60);
const timeOptions = [
    { value: 15, label: '15m' },
    { value: 60, label: '1h' },
    { value: 360, label: '6h' },
    { value: 1440, label: '24h' },
];

// Paste Log
const rawLog = ref(saved.rawLog);

// Persist to localStorage
const saveState = () => {
    try {
        localStorage.setItem(storageKey, JSON.stringify({
            result: result.value,
            rawLog: rawLog.value,
            activeTab: activeTab.value,
        }));
    } catch {
        // ignore quota errors
    }
};

watch(result, saveState);
watch(rawLog, saveState);
watch(activeTab, saveState);

const filteredPayouts = computed(() => {
    if (!result.value) return [];
    let payouts = result.value.payouts;

    if (statusFilter.value !== 'all') {
        payouts = payouts.filter(p => p.status === statusFilter.value);
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        payouts = payouts.filter(p =>
            String(p.payout_id).includes(q) ||
            (p.highlights?.beneficiary_name || '').toLowerCase().includes(q) ||
            p.events.some(e => e.event_type.includes(q)),
        );
    }

    return payouts;
});

const hasActiveFilters = computed(() => searchQuery.value.trim() !== '' || statusFilter.value !== 'all');

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = 'all';
};

const togglePayout = (id: string | number) => {
    const key = String(id);
    if (expandedPayouts.value.has(key)) {
        expandedPayouts.value.delete(key);
    } else {
        expandedPayouts.value.add(key);
    }
};

const toggleRawData = (id: string | number) => {
    const key = String(id);
    if (showRawData.value.has(key)) {
        showRawData.value.delete(key);
    } else {
        showRawData.value.add(key);
    }
};

const fetchLatest = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(`/projects/${props.project.id}/payout-monitor/fetch-latest`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ minutes_back: minutesBack.value }),
        });
        const data = await response.json();
        if (!response.ok) {
            error.value = data.error || 'Failed to fetch logs';
            return;
        }
        result.value = data;
        expandedPayouts.value = new Set(data.payouts.slice(0, 3).map((p: PayoutGroup) => String(p.payout_id)));
    } catch (e) {
        error.value = 'Network error. Please try again.';
    } finally {
        loading.value = false;
    }
};

const parseLog = async () => {
    if (!rawLog.value.trim()) return;
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(`/projects/${props.project.id}/payout-monitor/parse-log`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ raw_log: rawLog.value }),
        });
        const data = await response.json();
        if (!response.ok) {
            error.value = data.error || 'Failed to parse logs';
            return;
        }
        result.value = data;
        expandedPayouts.value = new Set(data.payouts.slice(0, 3).map((p: PayoutGroup) => String(p.payout_id)));
    } catch (e) {
        error.value = 'Network error. Please try again.';
    } finally {
        loading.value = false;
    }
};

const clearInput = () => {
    rawLog.value = '';
};

const clearResults = () => {
    result.value = null;
    searchQuery.value = '';
    expandedPayouts.value = new Set();
    showRawData.value = new Set();
    error.value = '';
    localStorage.removeItem(storageKey);
};

const getStatusConfig = (status: string) => {
    const map: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string; color: string; bgColor: string; icon: string }> = {
        done: { variant: 'default', label: 'COMPLETED', color: 'text-emerald-700', bgColor: 'bg-emerald-500/10 border-emerald-500/30', icon: '✓' },
        in_progress: { variant: 'secondary', label: 'IN PROGRESS', color: 'text-sky-700', bgColor: 'bg-sky-500/10 border-sky-500/30', icon: '→' },
        pending: { variant: 'outline', label: 'PENDING', color: 'text-amber-700', bgColor: 'bg-amber-500/10 border-amber-500/30', icon: '⏳' },
        failed: { variant: 'destructive', label: 'FAILED', color: 'text-red-700', bgColor: 'bg-red-500/10 border-red-500/30', icon: '✗' },
    };
    return map[status] || { variant: 'outline' as const, label: status.toUpperCase(), color: 'text-muted-foreground', bgColor: 'bg-muted/50', icon: '?' };
};

const getTimelineSteps = (payout: PayoutGroup) => {
    const steps: Array<{ label: string; time: string; completed: boolean; color: string; detail?: string }> = [];
    const events = payout.events;

    const findEvent = (type: string) => events.find(e => e.event_type === type);

    const verify = findEvent('verify.request');
    const verifyDone = findEvent('verify.completed');
    const verifyFail = findEvent('verify.failed');
    if (verify) {
        steps.push({
            label: 'Verify',
            time: verify.timestamp?.split(' ')[1] || '',
            completed: !!verifyDone,
            color: verifyFail ? 'text-red-500' : verifyDone ? 'text-emerald-500' : 'text-muted-foreground',
            detail: verifyFail ? 'Failed' : verifyDone ? 'OK' : 'Sent',
        });
    }

    const quoteReq = findEvent('quotation.request');
    const quoteDone = findEvent('quotation.created');
    if (quoteReq || quoteDone) {
        const h = payout.highlights;
        steps.push({
            label: 'Quote',
            time: (quoteDone || quoteReq)?.timestamp?.split(' ')[1] || '',
            completed: !!quoteDone,
            color: quoteDone ? 'text-emerald-500' : 'text-sky-500',
            detail: quoteDone && h.sending_amount
                ? `${h.sending_amount} ${h.sending_currency || ''} → ${h.receiving_amount || '?'} ${h.receiving_currency || ''}`
                : 'Requested',
        });
    }

    const sendReq = findEvent('send.request');
    const sendDone = findEvent('send.completed');
    if (sendReq) {
        steps.push({
            label: 'Send',
            time: sendReq.timestamp?.split(' ')[1] || '',
            completed: !!sendDone,
            color: sendDone ? 'text-emerald-500' : 'text-sky-500',
            detail: sendDone ? 'Completed' : 'Sent',
        });
    }

    if (sendDone) {
        const sendTime = sendReq?.timestamp ? new Date(`2000-01-01 ${sendReq.timestamp.split(' ')[1]}`).getTime() : 0;
        const doneTime = new Date(`2000-01-01 ${sendDone.timestamp?.split(' ')[1]}`).getTime();
        const elapsed = sendTime ? Math.round((doneTime - sendTime) / 1000) : null;
        steps.push({
            label: 'Done',
            time: sendDone.timestamp?.split(' ')[1] || '',
            completed: true,
            color: 'text-emerald-500',
            detail: elapsed !== null ? `in ${elapsed}s` : 'Completed',
        });
    }

    const pendingEvents = events.filter(e => e.event_type === 'check.pending');
    if (pendingEvents.length > 0 && !sendDone) {
        steps.push({
            label: 'Pending',
            time: pendingEvents[pendingEvents.length - 1].timestamp?.split(' ')[1] || '',
            completed: false,
            color: 'text-amber-500',
            detail: `${pendingEvents.length} checks`,
        });
    }

    return steps;
};

const getCategoryConfig = (key: string) => {
    const map: Record<string, { label: string; borderColor: string; bgColor: string; textColor: string; labelColor: string }> = {
        verify: { label: 'Verify Account', borderColor: 'border-l-sky-500', bgColor: 'bg-sky-500/5', textColor: 'text-sky-900', labelColor: 'text-sky-700' },
        quotation: { label: 'Create Quotation', borderColor: 'border-l-violet-500', bgColor: 'bg-violet-500/5', textColor: 'text-violet-900', labelColor: 'text-violet-700' },
        transaction: { label: 'Create Transaction', borderColor: 'border-l-teal-500', bgColor: 'bg-teal-500/5', textColor: 'text-teal-900', labelColor: 'text-teal-700' },
        other: { label: 'Other Events', borderColor: 'border-l-gray-400', bgColor: 'bg-muted/30', textColor: 'text-foreground', labelColor: 'text-muted-foreground' },
    };
    return map[key] || map.other;
};

const hasAnomaly = (payout: PayoutGroup, ...types: string[]): boolean => {
    return payout.anomalies.some(a => types.includes(a.type));
};

const getAnomalyDescription = (payout: PayoutGroup, ...types: string[]): string => {
    return payout.anomalies.find(a => types.includes(a.type))?.description || '';
};

const getEventColor = (event: PayoutEvent): string => {
    if (event.level === 'ERROR') return 'text-red-500';
    if (event.event_type.includes('failed')) return 'text-red-500';
    if (event.event_type.includes('completed')) return 'text-emerald-600';
    if (event.event_type.includes('request')) return 'text-sky-600';
    if (event.event_type.includes('pending')) return 'text-amber-600';
    if (event.event_type.includes('created')) return 'text-emerald-600';
    if (event.event_type.includes('started')) return 'text-sky-500';
    return 'text-muted-foreground';
};

const formatPayloadValue = (key: string, value: unknown): { display: string; highlight?: string } => {
    if (value === null || value === undefined) return { display: '—' };
    if (typeof value === 'object') return { display: JSON.stringify(value) };

    const str = String(value);

    // Highlight amounts
    if (['amount', 'sending_amount', 'receiving_amount', 'requestAmount'].includes(key)) {
        return { display: str, highlight: 'font-semibold text-foreground' };
    }
    // Highlight currencies
    if (['currency', 'sending_currency', 'receiving_currency', 'requestCurrency'].includes(key)) {
        return { display: str, highlight: 'font-semibold' };
    }
    // Highlight FX rate
    if (key === 'fx_rate') {
        return { display: str, highlight: 'font-semibold text-violet-600' };
    }
    // Highlight IDs
    if (['payout_id', 'quote_id', 'transaction_id', 'payment_system_guid'].includes(key)) {
        return { display: str, highlight: 'text-muted-foreground' };
    }

    return { display: str };
};

const flattenPayload = (payload: Record<string, unknown>, prefix = ''): Array<{ key: string; fullKey: string; value: string; highlight?: string; depth: number }> => {
    const rows: Array<{ key: string; fullKey: string; value: string; highlight?: string; depth: number }> = [];
    const depth = prefix ? prefix.split('.').length : 0;

    for (const [key, value] of Object.entries(payload)) {
        const fullKey = prefix ? `${prefix}.${key}` : key;

        if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
            rows.push(...flattenPayload(value as Record<string, unknown>, fullKey));
        } else if (Array.isArray(value)) {
            rows.push({ key, fullKey, value: JSON.stringify(value), depth });
        } else {
            const formatted = formatPayloadValue(key, value);
            rows.push({ key, fullKey, value: formatted.display, highlight: formatted.highlight, depth });
        }
    }
    return rows;
};
</script>

<template>
    <Head :title="`Payout Monitor - ${project.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Activity class="h-6 w-6 shrink-0 text-primary" />
                        Payout Monitor
                    </h1>
                    <p class="text-muted-foreground">Analyze TerraPay payout logs from Grafana/Loki</p>
                </div>
                <a href="/settings/grafana">
                    <Button variant="outline" size="sm" class="gap-2 cursor-pointer">
                        <Settings class="h-4 w-4" />
                        Settings
                    </Button>
                </a>
            </div>

            <!-- Not configured warning -->
            <Card v-if="!isConfigured" class="border-amber-500/50 bg-amber-500/5">
                <CardContent class="flex items-center gap-3 py-4">
                    <AlertTriangle class="h-5 w-5 text-amber-500 shrink-0" />
                    <div>
                        <p class="text-sm font-medium">Grafana not configured</p>
                        <p class="text-xs text-muted-foreground">
                            Go to <a href="/settings/grafana" class="underline hover:text-foreground">Settings &gt; Grafana</a> to set up your API token for the "Fetch Latest" feature. You can still use "Paste Log" without configuration.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Tabs -->
            <div class="flex items-center gap-2 border-b pb-2">
                <Button
                    :variant="activeTab === 'fetch' ? 'default' : 'ghost'"
                    size="sm"
                    class="gap-2 cursor-pointer"
                    @click="activeTab = 'fetch'"
                >
                    <RefreshCw class="h-4 w-4" />
                    Fetch Latest
                </Button>
                <Button
                    :variant="activeTab === 'paste' ? 'default' : 'ghost'"
                    size="sm"
                    class="gap-2 cursor-pointer"
                    @click="activeTab = 'paste'"
                >
                    <FileText class="h-4 w-4" />
                    Paste Log
                </Button>
            </div>

            <!-- Fetch Latest Tab -->
            <div v-if="activeTab === 'fetch'" class="flex items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <Label class="text-xs text-muted-foreground whitespace-nowrap">Time range:</Label>
                    <div class="flex gap-1">
                        <Button
                            v-for="opt in timeOptions"
                            :key="opt.value"
                            :variant="minutesBack === opt.value ? 'default' : 'outline'"
                            size="sm"
                            class="h-7 px-2.5 text-xs cursor-pointer"
                            @click="minutesBack = opt.value"
                        >
                            {{ opt.label }}
                        </Button>
                    </div>
                </div>
                <Button
                    :disabled="loading || !isConfigured"
                    class="gap-2 cursor-pointer"
                    size="sm"
                    @click="fetchLatest"
                >
                    <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                    Fetch
                </Button>
            </div>

            <!-- Paste Log Tab -->
            <div v-if="activeTab === 'paste'" class="space-y-3">
                <Textarea
                    v-model="rawLog"
                    placeholder="Paste Laravel log output here...

[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {&quot;payout_id&quot;:54692,...}"
                    rows="6"
                    class="font-mono text-xs"
                />
                <div class="flex items-center gap-2">
                    <Button :disabled="loading || !rawLog.trim()" class="gap-2 cursor-pointer" size="sm" @click="parseLog">
                        <Activity class="h-4 w-4" :class="{ 'animate-pulse': loading }" />
                        Analyze
                    </Button>
                    <Button
                        v-if="rawLog.trim()"
                        variant="outline"
                        size="sm"
                        class="gap-1.5 cursor-pointer"
                        @click="clearInput"
                    >
                        <X class="h-3.5 w-3.5" />
                        Clear Input
                    </Button>
                </div>
            </div>

            <!-- Error -->
            <Card v-if="error" class="border-red-500/50 bg-red-500/5">
                <CardContent class="flex items-center gap-3 py-3">
                    <XCircle class="h-5 w-5 text-red-500 shrink-0" />
                    <p class="text-sm">{{ error }}</p>
                </CardContent>
            </Card>

            <!-- Summary + Toolbar -->
            <div v-if="result" class="flex flex-wrap items-center gap-3 text-sm">
                <Badge variant="outline" class="gap-1.5">
                    <Activity class="h-3 w-3" />
                    {{ result.summary.total_payouts }} {{ result.summary.total_payouts === 1 ? 'payout' : 'payouts' }}
                </Badge>
                <Badge variant="outline" class="gap-1.5">
                    {{ result.summary.total_events }} events
                </Badge>
                <Badge v-if="result.summary.errors > 0" variant="destructive" class="gap-1.5">
                    <XCircle class="h-3 w-3" />
                    {{ result.summary.errors }} {{ result.summary.errors === 1 ? 'error' : 'errors' }}
                </Badge>
                <Badge v-if="result.summary.anomalies > 0" class="gap-1.5 bg-amber-500/10 text-amber-600 border-amber-500/30">
                    <AlertTriangle class="h-3 w-3" />
                    {{ result.summary.anomalies }} {{ result.summary.anomalies === 1 ? 'anomaly' : 'anomalies' }}
                </Badge>

                <div class="ml-auto flex items-center gap-2">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search by ID or name..."
                            class="w-52 pl-9 pr-8 bg-background/60"
                        />
                        <button
                            v-if="searchQuery"
                            class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                            @click="searchQuery = ''"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-36 cursor-pointer bg-background/60">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all" class="cursor-pointer">All Statuses</SelectItem>
                            <SelectItem value="done" class="cursor-pointer">Completed</SelectItem>
                            <SelectItem value="in_progress" class="cursor-pointer">In Progress</SelectItem>
                            <SelectItem value="pending" class="cursor-pointer">Pending</SelectItem>
                            <SelectItem value="failed" class="cursor-pointer">Failed</SelectItem>
                        </SelectContent>
                    </Select>

                    <button
                        v-if="hasActiveFilters"
                        class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground cursor-pointer"
                        @click="clearFilters"
                    >
                        <X class="h-3.5 w-3.5" />
                        Clear filters
                    </button>

                    <Button variant="outline" size="sm" class="gap-1.5 cursor-pointer" @click="clearResults">
                        <X class="h-3.5 w-3.5" />
                        Clear Results
                    </Button>
                </div>

                <span v-if="hasActiveFilters" class="w-full text-right text-xs text-muted-foreground">
                    {{ filteredPayouts.length }} of {{ result.payouts.length }} payouts
                </span>
            </div>

            <!-- Results -->
            <div v-if="result && filteredPayouts.length" class="space-y-4">
                <Card
                    v-for="payout in filteredPayouts"
                    :key="payout.payout_id"
                    class="overflow-hidden"
                    :class="{
                        'border-red-500/30': payout.status === 'failed',
                        'border-emerald-500/30': payout.status === 'done',
                        'border-amber-500/30': payout.status === 'pending',
                    }"
                >
                    <!-- Payout Header -->
                    <div
                        class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-muted/50 transition-colors"
                        @click="togglePayout(payout.payout_id)"
                    >
                        <component
                            :is="expandedPayouts.has(String(payout.payout_id)) ? ChevronDown : ChevronRight"
                            class="h-4 w-4 text-muted-foreground shrink-0"
                        />
                        <span class="font-mono text-sm font-bold">
                            #{{ payout.payout_id }}
                        </span>
                        <Badge :variant="getStatusConfig(payout.status).variant" class="text-[10px]">
                            {{ getStatusConfig(payout.status).label }}
                        </Badge>
                        <span v-if="payout.highlights?.beneficiary_name" class="text-xs text-muted-foreground truncate max-w-40">
                            {{ payout.highlights.beneficiary_name }}
                        </span>
                        <span v-if="payout.highlights?.sending_amount && payout.highlights?.sending_currency" class="text-xs font-mono text-muted-foreground">
                            {{ payout.highlights.sending_amount }} {{ payout.highlights.sending_currency }}
                            <template v-if="payout.highlights?.receiving_amount && payout.highlights?.receiving_currency">
                                → {{ payout.highlights.receiving_amount }} {{ payout.highlights.receiving_currency }}
                            </template>
                        </span>
                        <span class="text-[10px] text-muted-foreground">{{ payout.event_count }} events</span>
                        <div v-if="payout.anomalies.length > 0" class="flex items-center gap-1.5 ml-auto rounded-md px-2 py-1" :class="payout.anomalies.some(a => a.severity === 'error') ? 'bg-red-50 dark:bg-red-950/20' : 'bg-amber-50 dark:bg-amber-950/20'">
                            <AlertTriangle class="h-3.5 w-3.5 shrink-0" :class="payout.anomalies.some(a => a.severity === 'error') ? 'text-red-500' : 'text-amber-500'" />
                            <span class="text-[11px] font-medium max-w-60 truncate" :class="payout.anomalies.some(a => a.severity === 'error') ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'">
                                {{ payout.anomalies[0].description }}
                            </span>
                            <Badge v-if="payout.anomalies.length > 1" class="text-[9px] px-1 py-0 shrink-0" :class="payout.anomalies.some(a => a.severity === 'error') ? 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:text-red-300 dark:border-red-700' : 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-700'">
                                +{{ payout.anomalies.length - 1 }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Payout Body (Expanded) -->
                    <div v-if="expandedPayouts.has(String(payout.payout_id))" class="border-t">

                        <!-- ═══ Status & Timeline ═══ -->
                        <div class="px-4 py-3 border-b" :class="getStatusConfig(payout.status).bgColor">
                            <div class="flex items-center gap-2 mb-3">
                                <component
                                    :is="payout.status === 'done' ? CheckCircle : payout.status === 'failed' ? XCircle : Clock"
                                    class="h-5 w-5"
                                    :class="getStatusConfig(payout.status).color"
                                />
                                <h3 class="text-sm font-bold" :class="getStatusConfig(payout.status).color">
                                    Status: {{ getStatusConfig(payout.status).label }}
                                </h3>
                            </div>
                            <!-- Timeline -->
                            <div class="flex items-center gap-1">
                                <template v-for="(step, idx) in getTimelineSteps(payout)" :key="idx">
                                    <div class="flex items-center gap-1.5 rounded-md border bg-background/80 px-2.5 py-1.5">
                                        <component
                                            :is="step.completed ? CheckCircle : Clock"
                                            class="h-3.5 w-3.5 shrink-0"
                                            :class="step.color"
                                        />
                                        <div>
                                            <div class="text-[10px] font-semibold uppercase tracking-wide" :class="step.color">{{ step.label }}</div>
                                            <div class="text-[10px] text-muted-foreground font-mono">{{ step.time }}</div>
                                            <div v-if="step.detail" class="text-[10px] text-muted-foreground">{{ step.detail }}</div>
                                        </div>
                                    </div>
                                    <ArrowRight v-if="idx < getTimelineSteps(payout).length - 1" class="h-3 w-3 text-muted-foreground shrink-0" />
                                </template>
                            </div>
                        </div>

                        <!-- ═══ Anomalies / Errors ═══ -->
                        <div v-if="payout.anomalies.length > 0" class="px-4 py-3 border-b" :class="payout.anomalies.some(a => a.severity === 'error') ? 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900/30' : 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/30'">
                            <h3 class="text-xs font-bold uppercase tracking-wide mb-2" :class="payout.anomalies.some(a => a.severity === 'error') ? 'text-red-700 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'">
                                Anomalies Detected
                            </h3>
                            <div class="space-y-1.5">
                                <div
                                    v-for="(anomaly, idx) in payout.anomalies"
                                    :key="idx"
                                    class="flex items-start gap-2 rounded-md px-2.5 py-1.5"
                                    :class="{
                                        'bg-red-100/80 dark:bg-red-900/20': anomaly.severity === 'error',
                                        'bg-amber-100/80 dark:bg-amber-900/20': anomaly.severity === 'warning',
                                        'bg-sky-100/80 dark:bg-sky-900/20': anomaly.severity === 'info',
                                    }"
                                >
                                    <component
                                        :is="anomaly.severity === 'error' ? XCircle : AlertTriangle"
                                        class="h-3.5 w-3.5 shrink-0 mt-0.5"
                                        :class="{
                                            'text-red-600 dark:text-red-400': anomaly.severity === 'error',
                                            'text-amber-600 dark:text-amber-400': anomaly.severity === 'warning',
                                            'text-sky-600 dark:text-sky-400': anomaly.severity === 'info',
                                        }"
                                    />
                                    <span class="text-xs font-medium flex-1" :class="{
                                        'text-red-800 dark:text-red-300': anomaly.severity === 'error',
                                        'text-amber-800 dark:text-amber-300': anomaly.severity === 'warning',
                                        'text-sky-800 dark:text-sky-300': anomaly.severity === 'info',
                                    }">
                                        {{ anomaly.description }}
                                    </span>
                                    <Badge class="text-[9px] px-1.5 py-0 shrink-0" :class="{
                                        'bg-red-200 text-red-700 border-red-300 dark:bg-red-900/40 dark:text-red-300 dark:border-red-700': anomaly.severity === 'error',
                                        'bg-amber-200 text-amber-700 border-amber-300 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-700': anomaly.severity === 'warning',
                                        'bg-sky-200 text-sky-700 border-sky-300 dark:bg-sky-900/40 dark:text-sky-300 dark:border-sky-700': anomaly.severity === 'info',
                                    }">
                                        {{ anomaly.severity }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ Sender (Debit Party) ═══ -->
                        <div class="border-b">
                            <div class="bg-blue-50/50 dark:bg-blue-950/10 border-b border-blue-100 dark:border-blue-900/30 px-4 py-1.5 flex items-center gap-2">
                                <Building class="h-3.5 w-3.5 text-blue-500/70 dark:text-blue-400/70" />
                                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-blue-600/80 dark:text-blue-400/70">Sender (Debit Party)</h3>
                                <span v-if="payout.highlights.sender?.business_name" class="ml-auto text-xs text-blue-500/60 dark:text-blue-400/50 font-medium">{{ payout.highlights.sender.business_name }}</span>
                            </div>
                            <div class="space-y-0 text-sm font-mono">
                                <div v-if="payout.highlights.sender?.business_name" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessName</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground font-semibold">{{ payout.highlights.sender.business_name }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.address" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessAddress1</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.address }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.city" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessAddressCity</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.city }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.state" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessAddressState</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.state }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.address_country || payout.highlights.sender?.business_country" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessCountryCode</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span :class="payout.highlights.sender?.address_country && payout.highlights.sender?.business_country && payout.highlights.sender.address_country !== payout.highlights.sender.business_country ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-foreground'">
                                        {{ payout.highlights.sender?.address_country || payout.highlights.sender?.business_country }}
                                        <AlertTriangle v-if="payout.highlights.sender?.address_country && payout.highlights.sender?.business_country && payout.highlights.sender.address_country !== payout.highlights.sender.business_country" class="inline h-3 w-3 text-red-500 ml-0.5" />
                                    </span>
                                </div>
                                <div v-if="payout.highlights.sender?.zip" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessAddressZip</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.zip }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.phone_country" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessPrimaryContactCountryCode</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.phone_country }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.phone || payout.highlights.sender?.msisdn" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessPrimaryContactNo</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender?.phone || payout.highlights.sender?.msisdn }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.email" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessEmail</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground break-all">{{ payout.highlights.sender.email }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.registration_type" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessRegistrationType</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.registration_type }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.registration_number" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessRegistrationNumber</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.registration_number }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.registration_issue_date" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessRegistrationIssueDate</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.registration_issue_date }}</span>
                                </div>
                                <div v-if="payout.highlights.sender?.registration_valid_thru" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">businessIDValidThru</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.sender.registration_valid_thru }}</span>
                                </div>
                                <div v-if="payout.highlights.sending_amount" class="flex gap-2 px-3 py-2 bg-blue-50/30 dark:bg-blue-950/5 border-l-3 border-blue-400/60 hover:bg-blue-50/60 dark:hover:bg-blue-950/10 transition-colors">
                                    <span class="text-blue-600/80 dark:text-blue-400/70 font-medium shrink-0">sendingAmount</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="font-semibold" :class="hasAnomaly(payout, 'amount_mismatch', 'suspicious_amount') ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                                        {{ payout.highlights.sending_amount }} {{ payout.highlights.sending_currency }}
                                        <AlertTriangle v-if="hasAnomaly(payout, 'amount_mismatch', 'suspicious_amount')" class="inline h-3 w-3 text-red-500 ml-0.5" />
                                    </span>
                                </div>
                                <!-- Empty state -->
                                <p v-if="!payout.highlights.sender || Object.keys(payout.highlights.sender).length === 0" class="text-xs text-muted-foreground italic px-3 py-2">
                                    No sender details in logs
                                </p>
                            </div>
                        </div>

                        <!-- ═══ Recipient (Credit Party) ═══ -->
                        <div class="border-b">
                            <div class="bg-emerald-50/50 dark:bg-emerald-950/10 border-b border-emerald-100 dark:border-emerald-900/30 px-4 py-1.5 flex items-center gap-2">
                                <User class="h-3.5 w-3.5 text-emerald-500/70 dark:text-emerald-400/70" />
                                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600/80 dark:text-emerald-400/70">Recipient (Credit Party)</h3>
                                <span v-if="payout.highlights.beneficiary_name || payout.highlights.recipient?.business_name" class="ml-auto text-xs text-emerald-500/60 dark:text-emerald-400/50 font-medium">{{ payout.highlights.beneficiary_name || payout.highlights.recipient?.business_name }}</span>
                            </div>
                            <div class="space-y-0 text-sm font-mono">
                                <div v-if="payout.highlights.recipient?.first_name" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">firstName</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.first_name }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.middle_name" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">middleName</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.middle_name }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.last_name" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">lastName</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.last_name }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.full_name || payout.highlights.beneficiary_name" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">fullName</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground font-semibold">{{ payout.highlights.recipient?.full_name || payout.highlights.beneficiary_name }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.business_name" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">businessName</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground font-semibold">{{ payout.highlights.recipient.business_name }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.address" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">addressLine1</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.address }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.city" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">city</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.city }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.postal_code || payout.highlights.recipient?.zip" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">postalCode</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.postal_code || payout.highlights.recipient.zip }}</span>
                                </div>
                                <div v-if="payout.highlights.beneficiary_country || payout.highlights.recipient?.business_country || payout.highlights.recipient?.address_country" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">country</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.beneficiary_country || payout.highlights.recipient?.business_country || payout.highlights.recipient?.address_country }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.bank_name" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">organisationid</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.bank_name }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.bank_account || payout.highlights.account_number" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">bankaccountno</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient?.bank_account || payout.highlights.account_number }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.iban" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">accountIBAN</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.iban }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.instrument_type" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">instrumentType</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.instrument_type }}</span>
                                </div>
                                <div v-if="payout.highlights.recipient?.msisdn" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">msisdn</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.recipient.msisdn }}</span>
                                </div>
                                <div v-if="payout.highlights.receiving_amount" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">receivingAmount</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="font-semibold" :class="hasAnomaly(payout, 'suspicious_amount', 'send_amount_mismatch') ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                                        {{ payout.highlights.receiving_amount }} {{ payout.highlights.receiving_currency }}
                                        <AlertTriangle v-if="hasAnomaly(payout, 'suspicious_amount', 'send_amount_mismatch')" class="inline h-3 w-3 text-red-500 ml-0.5" />
                                    </span>
                                </div>
                                <div v-if="payout.highlights.payout_method" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">payoutMethod</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.payout_method }}</span>
                                </div>
                                <div v-if="payout.highlights.receiving_country" class="flex gap-2 px-3 py-2 bg-emerald-50/30 dark:bg-emerald-950/5 border-l-3 border-emerald-400/60 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/10 transition-colors">
                                    <span class="text-emerald-600/80 dark:text-emerald-400/70 font-medium shrink-0">receivingCountry</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.receiving_country }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ Transfer Information ═══ -->
                        <div v-if="payout.highlights?.fx_rate || payout.highlights?.quote_id || payout.highlights?.transaction_id || payout.highlights?.remittance_purpose" class="border-b">
                            <div class="px-4 py-1.5 flex items-center gap-2" :class="hasAnomaly(payout, 'abnormal_fx_rate', 'fx_rate_mismatch', 'suspicious_amount') ? 'bg-red-50/50 dark:bg-red-950/10 border-b border-red-100 dark:border-red-900/30' : 'bg-purple-50/50 dark:bg-purple-950/10 border-b border-purple-100 dark:border-purple-900/30'">
                                <Globe class="h-3.5 w-3.5" :class="hasAnomaly(payout, 'abnormal_fx_rate', 'fx_rate_mismatch', 'suspicious_amount') ? 'text-red-500/70 dark:text-red-400/70' : 'text-purple-500/70 dark:text-purple-400/70'" />
                                <h3 class="text-[11px] font-semibold uppercase tracking-wide" :class="hasAnomaly(payout, 'abnormal_fx_rate', 'fx_rate_mismatch', 'suspicious_amount') ? 'text-red-600/80 dark:text-red-400/70' : 'text-purple-600/80 dark:text-purple-400/70'">Transfer Information</h3>
                                <AlertTriangle v-if="hasAnomaly(payout, 'abnormal_fx_rate', 'fx_rate_mismatch', 'suspicious_amount')" class="h-3.5 w-3.5 text-red-500/70 ml-auto" />
                            </div>
                            <div class="space-y-0 text-sm font-mono">
                                <div v-if="payout.highlights.fx_rate" class="flex gap-2 px-3 py-2 border-l-3 transition-colors" :class="hasAnomaly(payout, 'abnormal_fx_rate', 'fx_rate_mismatch') ? 'bg-red-50/40 dark:bg-red-950/10 border-red-400/60 hover:bg-red-50/60 dark:hover:bg-red-950/15' : 'bg-purple-50/30 dark:bg-purple-950/5 border-purple-400/60 hover:bg-purple-50/60 dark:hover:bg-purple-950/10'">
                                    <span class="font-medium shrink-0" :class="hasAnomaly(payout, 'abnormal_fx_rate', 'fx_rate_mismatch') ? 'text-red-600/80 dark:text-red-400/70' : 'text-purple-600/80 dark:text-purple-400/70'">fxRate</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="font-semibold" :class="hasAnomaly(payout, 'abnormal_fx_rate', 'fx_rate_mismatch') ? 'text-red-700 dark:text-red-400' : 'text-foreground'">
                                        {{ payout.highlights.fx_rate }}
                                        <XCircle v-if="hasAnomaly(payout, 'abnormal_fx_rate')" class="inline h-3.5 w-3.5 text-red-500 ml-0.5" />
                                    </span>
                                </div>
                                <div v-if="hasAnomaly(payout, 'abnormal_fx_rate')" class="px-3 py-1.5 bg-red-50/40 dark:bg-red-900/10 border-l-3 border-red-400/60 text-[11px] text-red-700 dark:text-red-400 font-medium font-sans">
                                    {{ getAnomalyDescription(payout, 'abnormal_fx_rate') }}
                                </div>
                                <div v-if="hasAnomaly(payout, 'fx_rate_mismatch')" class="px-3 py-1.5 bg-amber-50/40 dark:bg-amber-900/10 border-l-3 border-amber-400/60 text-[11px] text-amber-700 dark:text-amber-400 font-medium font-sans">
                                    {{ getAnomalyDescription(payout, 'fx_rate_mismatch') }}
                                </div>
                                <div v-if="payout.highlights.quote_id" class="flex gap-2 px-3 py-2 bg-purple-50/30 dark:bg-purple-950/5 border-l-3 border-purple-400/60 hover:bg-purple-50/60 dark:hover:bg-purple-950/10 transition-colors">
                                    <span class="text-purple-600/80 dark:text-purple-400/70 font-medium shrink-0">quoteId</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.quote_id }}</span>
                                </div>
                                <div v-if="payout.highlights.transaction_id" class="flex gap-2 px-3 py-2 bg-purple-50/30 dark:bg-purple-950/5 border-l-3 border-purple-400/60 hover:bg-purple-50/60 dark:hover:bg-purple-950/10 transition-colors">
                                    <span class="text-purple-600/80 dark:text-purple-400/70 font-medium shrink-0">transactionId</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.transaction_id }}</span>
                                </div>
                                <div v-if="payout.highlights.remittance_purpose" class="flex gap-2 px-3 py-2 bg-purple-50/30 dark:bg-purple-950/5 border-l-3 border-purple-400/60 hover:bg-purple-50/60 dark:hover:bg-purple-950/10 transition-colors">
                                    <span class="text-purple-600/80 dark:text-purple-400/70 font-medium shrink-0">remittancePurpose</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.remittance_purpose }}</span>
                                </div>
                                <div v-if="payout.highlights.source_of_funds" class="flex gap-2 px-3 py-2 bg-purple-50/30 dark:bg-purple-950/5 border-l-3 border-purple-400/60 hover:bg-purple-50/60 dark:hover:bg-purple-950/10 transition-colors">
                                    <span class="text-purple-600/80 dark:text-purple-400/70 font-medium shrink-0">sourceOfFunds</span>
                                    <span class="text-muted-foreground">:</span>
                                    <span class="text-foreground">{{ payout.highlights.source_of_funds }}</span>
                                </div>
                                <!-- Suspicious amount inline alert -->
                                <div v-if="hasAnomaly(payout, 'suspicious_amount')" class="px-3 py-1.5 bg-red-50/40 dark:bg-red-900/10 border-l-3 border-red-400/60 text-[11px] text-red-700 dark:text-red-400 font-medium font-sans flex items-center gap-1.5">
                                    <XCircle class="h-3.5 w-3.5 shrink-0" />
                                    {{ getAnomalyDescription(payout, 'suspicious_amount') }}
                                </div>
                            </div>
                        </div>

                        <!-- ═══ Event Categories (color-coded) ═══ -->
                        <div class="space-y-0">
                            <div
                                v-for="(events, catKey) in payout.categories"
                                :key="catKey"
                                class="px-4 py-3 border-b border-l-4"
                                :class="[getCategoryConfig(catKey).bgColor, getCategoryConfig(catKey).borderColor]"
                            >
                                <h4 class="text-xs font-bold uppercase tracking-wide mb-2.5 flex items-center gap-1.5" :class="getCategoryConfig(catKey).labelColor">
                                    <component
                                        :is="catKey === 'verify' ? User : catKey === 'quotation' ? CreditCard : catKey === 'transaction' ? Globe : FileText"
                                        class="h-3.5 w-3.5"
                                    />
                                    {{ getCategoryConfig(catKey).label }}
                                </h4>
                                <div class="space-y-2">
                                    <div v-for="(event, eIdx) in events" :key="eIdx">
                                        <!-- Collapsed check.pending -->
                                        <div v-if="event.collapsed" class="flex items-center gap-2 rounded-md bg-amber-100/60 dark:bg-amber-900/20 px-2.5 py-1.5">
                                            <Clock class="h-3.5 w-3.5 text-amber-600" />
                                            <span class="text-xs font-mono text-amber-700 dark:text-amber-400 font-semibold">
                                                CHECK PENDING x{{ event.payload.count }}
                                            </span>
                                        </div>
                                        <!-- Normal event -->
                                        <div v-else class="rounded-md border bg-background/60 px-3 py-2">
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <component
                                                    :is="event.level === 'ERROR' ? XCircle : CheckCircle"
                                                    class="h-3.5 w-3.5 shrink-0"
                                                    :class="getEventColor(event)"
                                                />
                                                <span class="text-xs font-mono font-bold" :class="getEventColor(event)">
                                                    {{ event.event_type }}
                                                </span>
                                                <span v-if="event.timestamp" class="text-[10px] text-muted-foreground font-mono ml-auto">
                                                    {{ event.timestamp }}
                                                </span>
                                            </div>
                                            <!-- Payload as table -->
                                            <div v-if="Object.keys(event.payload).length > 0" class="ml-5 space-y-0">
                                                <div
                                                    v-for="row in flattenPayload(event.payload)"
                                                    :key="row.fullKey"
                                                    class="flex gap-2 py-0.5 text-[11px] font-mono"
                                                    :style="{ paddingLeft: row.depth * 12 + 'px' }"
                                                >
                                                    <span class="text-muted-foreground shrink-0">{{ row.key }}:</span>
                                                    <span class="break-all" :class="row.highlight || 'text-foreground'">{{ row.value }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ Raw Data Toggle ═══ -->
                        <div class="px-4 py-2 border-t bg-muted/20">
                            <button
                                class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                @click.stop="toggleRawData(payout.payout_id)"
                            >
                                <FileCode class="h-3.5 w-3.5" />
                                <component :is="showRawData.has(String(payout.payout_id)) ? ChevronDown : ChevronRight" class="h-3 w-3" />
                                {{ showRawData.has(String(payout.payout_id)) ? 'Hide' : 'Show' }} Raw Events
                            </button>
                            <div v-if="showRawData.has(String(payout.payout_id))" class="mt-2">
                                <pre class="rounded-md bg-gray-950 text-emerald-400 p-3 overflow-x-auto text-[11px] font-mono max-h-80 overflow-y-auto">{{ JSON.stringify(payout.events, null, 2) }}</pre>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Empty state -->
            <div v-else-if="result && filteredPayouts.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <Activity class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No payouts found</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ searchQuery ? 'No payouts match your search.' : 'No terrapay events found in the logs.' }}
                    </p>
                </div>
            </div>

            <!-- Initial state -->
            <div v-else-if="!loading" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <Activity class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">Ready to analyze</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ activeTab === 'fetch' ? 'Click "Fetch" to get the latest logs from Grafana.' : 'Paste your log output and click "Analyze".' }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
