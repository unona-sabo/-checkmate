import type { BadgeVariants } from '@/components/ui/badge';

type Variant = NonNullable<BadgeVariants['variant']>;

export const priorityVariant = (priority: string): Variant => {
    switch (priority) {
        case 'critical': return 'red';
        case 'high': return 'orange';
        case 'medium': return 'amber';
        case 'low': return 'blue';
        default: return 'gray';
    }
};

export const severityVariant = (severity: string): Variant => {
    switch (severity) {
        case 'blocker': return 'red';
        case 'critical': return 'red';
        case 'major': return 'orange';
        case 'minor': return 'amber';
        case 'trivial': return 'gray';
        default: return 'gray';
    }
};

export const bugStatusVariant = (status: string): Variant => {
    switch (status) {
        case 'new': return 'blue';
        case 'open': return 'purple';
        case 'in_progress': return 'amber';
        case 'resolved': return 'emerald';
        case 'closed': return 'gray';
        case 'reopened': return 'red';
        default: return 'gray';
    }
};

export const testResultVariant = (status: string): Variant => {
    switch (status) {
        case 'passed': return 'emerald';
        case 'failed': return 'red';
        case 'blocked': return 'orange';
        case 'skipped': return 'purple';
        case 'retest': return 'blue';
        case 'untested': return 'gray';
        default: return 'gray';
    }
};

export const testRunStatusVariant = (status: string): Variant => {
    switch (status) {
        case 'active': return 'emerald';
        case 'completed': return 'blue';
        case 'archived': return 'gray';
        default: return 'gray';
    }
};

export const testTypeVariant = (type: string): Variant => {
    switch (type) {
        case 'functional': return 'blue';
        case 'smoke': return 'orange';
        case 'regression': return 'red';
        case 'integration': return 'purple';
        case 'acceptance': return 'emerald';
        case 'performance': return 'cyan';
        case 'security': return 'rose';
        case 'usability': return 'pink';
        default: return 'gray';
    }
};

export const automationVariant = (status: string): Variant => {
    switch (status) {
        case 'automated': return 'emerald';
        case 'to_be_automated': return 'blue';
        case 'not_automated': return 'gray';
        default: return 'gray';
    }
};

export const releaseStatusVariant = (status: string): Variant => {
    switch (status) {
        case 'released': return 'default';
        case 'ready': return 'emerald';
        case 'cancelled': return 'destructive';
        case 'completed': return 'default';
        default: return 'secondary';
    }
};

export const releaseDecisionVariant = (decision: string): Variant => {
    switch (decision) {
        case 'go': return 'emerald';
        case 'no_go': return 'destructive';
        case 'conditional': return 'amber';
        default: return 'secondary';
    }
};

export const automationResultVariant = (status: string): Variant => {
    switch (status) {
        case 'passed': return 'emerald';
        case 'failed': return 'destructive';
        default: return 'secondary';
    }
};
