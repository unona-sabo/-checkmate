<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Bug } from 'lucide-vue-next';
import {
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    FileText,
    Flag,
    Gauge,
    Rocket,
    Target,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { releaseStatusVariant, severityVariant } from '@/lib/badge-variants';
import {
    type DashboardEvent,
    type DashboardEventType,
} from '@/types/checkmate';

defineProps<{
    event: DashboardEvent;
    showProject?: boolean;
}>();

const EVENT_STYLES: Record<
    DashboardEventType,
    { icon: typeof Bug; wrap: string; iconClass: string }
> = {
    bug: { icon: Flag, wrap: 'bg-red-500/10', iconClass: 'text-red-500' },
    test_run: {
        icon: CheckCircle2,
        wrap: 'bg-emerald-500/10',
        iconClass: 'text-emerald-500',
    },
    coverage: {
        icon: Gauge,
        wrap: 'bg-purple-500/10',
        iconClass: 'text-purple-500',
    },
    checklist_created: {
        icon: ClipboardList,
        wrap: 'bg-blue-500/10',
        iconClass: 'text-blue-500',
    },
    checklist: {
        icon: ClipboardCheck,
        wrap: 'bg-emerald-500/10',
        iconClass: 'text-emerald-500',
    },
    release: {
        icon: Rocket,
        wrap: 'bg-amber-500/10',
        iconClass: 'text-amber-500',
    },
    feature: {
        icon: Target,
        wrap: 'bg-amber-500/10',
        iconClass: 'text-amber-500',
    },
    test_case: {
        icon: FileText,
        wrap: 'bg-cyan-500/10',
        iconClass: 'text-cyan-500',
    },
};

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ');
}

function eventBadge(
    event: DashboardEvent,
): { label: string; variant: ReturnType<typeof severityVariant> } | null {
    if (event.type === 'bug' && event.tag) {
        return {
            label: capitalize(event.tag),
            variant: severityVariant(event.tag),
        };
    }
    if (event.type === 'coverage') {
        return { label: 'Insight', variant: 'purple' };
    }
    if (event.type === 'release' && event.tag) {
        return {
            label: capitalize(event.tag),
            variant: releaseStatusVariant(event.tag),
        };
    }
    return null;
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Link
        :href="event.url"
        class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-2.5 transition-colors hover:bg-muted/60"
    >
        <div
            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
            :class="EVENT_STYLES[event.type].wrap"
        >
            <component
                :is="EVENT_STYLES[event.type].icon"
                class="h-4 w-4"
                :class="EVENT_STYLES[event.type].iconClass"
            />
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm leading-snug font-medium">
                    {{ event.title }}
                </p>
                <span
                    class="shrink-0 text-[11px] text-muted-foreground tabular-nums"
                >
                    {{ formatTime(event.timestamp) }}
                </span>
            </div>
            <div
                class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground"
            >
                <span
                    v-if="showProject"
                    class="font-medium text-foreground/70"
                    >{{ event.project_name }}</span
                >
                <span v-if="showProject && event.meta.length" aria-hidden="true"
                    >·</span
                >
                <template v-for="(m, mi) in event.meta" :key="mi">
                    <span v-if="mi > 0" aria-hidden="true">·</span>
                    <span>{{ m }}</span>
                </template>
                <Badge
                    v-if="eventBadge(event)"
                    :variant="eventBadge(event)!.variant"
                    class="ml-1 text-[10px]"
                >
                    {{ eventBadge(event)!.label }}
                </Badge>
            </div>
        </div>
    </Link>
</template>
