<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestUser, type TestPaymentMethod, type TestCommand, type TestLink } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import InputError from '@/components/InputError.vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import {
    Database,
    Plus,
    Search,
    X,
    Copy,
    Check,
    Edit,
    Trash2,
    Eye,
    EyeOff,
    Download,
    Upload,
    FileSpreadsheet,
    Users,
    CreditCard,
    GripVertical,
    GripHorizontal,
    Terminal,
    Link2,
    ExternalLink,
    Columns3,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useCanEdit } from '@/composables/useCanEdit';

const { canEdit } = useCanEdit();

const props = defineProps<{
    project: Project;
    testUsers: TestUser[];
    testPaymentMethods: TestPaymentMethod[];
    testCommands: TestCommand[];
    testLinks: TestLink[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Data', href: `/projects/${props.project.id}/test-data` },
];

// Tab state
const activeTab = ref<'users' | 'payments' | 'commands' | 'links'>('users');

// Search
const searchQuery = ref('');

// Filters
const validityFilter = ref<string>('all');
const environmentFilter = ref<string>('all');
const roleFilter = ref<string>('all');
const typeFilter = ref<string>('all');

// Password visibility
const visiblePasswords = ref<Set<number>>(new Set());

const togglePasswordVisibility = (id: number) => {
    if (visiblePasswords.value.has(id)) {
        visiblePasswords.value.delete(id);
    } else {
        visiblePasswords.value.add(id);
    }
    visiblePasswords.value = new Set(visiblePasswords.value);
};

// Copy to clipboard
const copiedField = ref<string | null>(null);

const copyToClipboard = (text: string, key: string) => {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    copiedField.value = key;
    setTimeout(() => { copiedField.value = null; }, 2000);
};

// ===== Column definitions =====
interface ColumnDef {
    key: string;
    label: string;
    width: number;
    fixed?: boolean;
}

const defaultUserColumns: ColumnDef[] = [
    { key: 'checkbox', label: '', width: 40, fixed: true },
    { key: 'name', label: 'Name', width: 150 },
    { key: 'email', label: 'Email', width: 200 },
    { key: 'password', label: 'Password', width: 160 },
    { key: 'role', label: 'Role', width: 100 },
    { key: 'environment', label: 'Env', width: 110 },
    { key: 'valid', label: 'Valid', width: 80 },
    { key: 'tags', label: 'Tags', width: 150 },
    { key: 'description', label: 'Description', width: 200 },
    { key: 'actions', label: 'Actions', width: 96, fixed: true },
];

const defaultPaymentColumns: ColumnDef[] = [
    { key: 'checkbox', label: '', width: 40, fixed: true },
    { key: 'name', label: 'Name', width: 150 },
    { key: 'type', label: 'Type', width: 100 },
    { key: 'system', label: 'System', width: 120 },
    { key: 'credentials', label: 'Credentials', width: 220 },
    { key: 'environment', label: 'Env', width: 110 },
    { key: 'valid', label: 'Valid', width: 80 },
    { key: 'tags', label: 'Tags', width: 150 },
    { key: 'description', label: 'Description', width: 200 },
    { key: 'actions', label: 'Actions', width: 96, fixed: true },
];

const defaultCommandColumns: ColumnDef[] = [
    { key: 'checkbox', label: '', width: 40, fixed: true },
    { key: 'category', label: 'Category', width: 120 },
    { key: 'description', label: 'Description', width: 200 },
    { key: 'command', label: 'Command', width: 300 },
    { key: 'comment', label: 'Comment', width: 200 },
    { key: 'actions', label: 'Actions', width: 96, fixed: true },
];

const defaultLinkColumns: ColumnDef[] = [
    { key: 'checkbox', label: '', width: 40, fixed: true },
    { key: 'category', label: 'Category', width: 120 },
    { key: 'description', label: 'Description', width: 200 },
    { key: 'url', label: 'URL', width: 300 },
    { key: 'comment', label: 'Comment', width: 200 },
    { key: 'actions', label: 'Actions', width: 96, fixed: true },
];

const userColumns = ref<ColumnDef[]>([...defaultUserColumns.map(c => ({ ...c }))]);
const paymentColumns = ref<ColumnDef[]>([...defaultPaymentColumns.map(c => ({ ...c }))]);
const commandColumns = ref<ColumnDef[]>([...defaultCommandColumns.map(c => ({ ...c }))]);
const linkColumns = ref<ColumnDef[]>([...defaultLinkColumns.map(c => ({ ...c }))]);

// localStorage persistence for column config
const userColStorageKey = computed(() => `test-data-users-columns-${props.project.id}`);
const paymentColStorageKey = computed(() => `test-data-payments-columns-${props.project.id}`);
const commandColStorageKey = computed(() => `test-data-commands-columns-${props.project.id}`);
const linkColStorageKey = computed(() => `test-data-links-columns-${props.project.id}`);

const loadColumnConfig = () => {
    try {
        const userJson = localStorage.getItem(userColStorageKey.value);
        if (userJson) {
            const saved = JSON.parse(userJson) as ColumnDef[];
            // Merge saved config with defaults to handle new columns
            const merged: ColumnDef[] = [];
            for (const s of saved) {
                const def = defaultUserColumns.find(d => d.key === s.key);
                if (def) {
                    merged.push({ ...def, width: s.width, fixed: def.fixed });
                }
            }
            // Add any new defaults not in saved
            for (const d of defaultUserColumns) {
                if (!merged.find(m => m.key === d.key)) {
                    merged.push({ ...d });
                }
            }
            userColumns.value = merged;
        }
    } catch { /* ignore */ }

    try {
        const payJson = localStorage.getItem(paymentColStorageKey.value);
        if (payJson) {
            const saved = JSON.parse(payJson) as ColumnDef[];
            const merged: ColumnDef[] = [];
            for (const s of saved) {
                const def = defaultPaymentColumns.find(d => d.key === s.key);
                if (def) {
                    merged.push({ ...def, width: s.width, fixed: def.fixed });
                }
            }
            for (const d of defaultPaymentColumns) {
                if (!merged.find(m => m.key === d.key)) {
                    merged.push({ ...d });
                }
            }
            paymentColumns.value = merged;
        }
    } catch { /* ignore */ }

    try {
        const cmdJson = localStorage.getItem(commandColStorageKey.value);
        if (cmdJson) {
            const saved = JSON.parse(cmdJson) as ColumnDef[];
            const merged: ColumnDef[] = [];
            for (const s of saved) {
                const def = defaultCommandColumns.find(d => d.key === s.key);
                if (def) {
                    merged.push({ ...def, width: s.width, fixed: def.fixed });
                }
            }
            for (const d of defaultCommandColumns) {
                if (!merged.find(m => m.key === d.key)) {
                    merged.push({ ...d });
                }
            }
            commandColumns.value = merged;
        }
    } catch { /* ignore */ }

    try {
        const lnkJson = localStorage.getItem(linkColStorageKey.value);
        if (lnkJson) {
            const saved = JSON.parse(lnkJson) as ColumnDef[];
            const merged: ColumnDef[] = [];
            for (const s of saved) {
                const def = defaultLinkColumns.find(d => d.key === s.key);
                if (def) {
                    merged.push({ ...def, width: s.width, fixed: def.fixed });
                }
            }
            for (const d of defaultLinkColumns) {
                if (!merged.find(m => m.key === d.key)) {
                    merged.push({ ...d });
                }
            }
            linkColumns.value = merged;
        }
    } catch { /* ignore */ }
};

const saveColumnConfig = () => {
    localStorage.setItem(userColStorageKey.value, JSON.stringify(userColumns.value.map(c => ({ key: c.key, width: c.width }))));
    localStorage.setItem(paymentColStorageKey.value, JSON.stringify(paymentColumns.value.map(c => ({ key: c.key, width: c.width }))));
    localStorage.setItem(commandColStorageKey.value, JSON.stringify(commandColumns.value.map(c => ({ key: c.key, width: c.width }))));
    localStorage.setItem(linkColStorageKey.value, JSON.stringify(linkColumns.value.map(c => ({ key: c.key, width: c.width }))));
};

// ===== Column visibility =====
const hiddenUserCols = ref<string[]>([]);
const hiddenPaymentCols = ref<string[]>([]);
const hiddenCommandCols = ref<string[]>([]);
const hiddenLinkCols = ref<string[]>([]);

const hiddenColsMap: Record<string, { ref: typeof hiddenUserCols; defaults: ColumnDef[]; storageKey: () => string }> = {
    users: { ref: hiddenUserCols, defaults: defaultUserColumns, storageKey: () => `test-data-users-hidden-cols-${props.project.id}` },
    payments: { ref: hiddenPaymentCols, defaults: defaultPaymentColumns, storageKey: () => `test-data-payments-hidden-cols-${props.project.id}` },
    commands: { ref: hiddenCommandCols, defaults: defaultCommandColumns, storageKey: () => `test-data-commands-hidden-cols-${props.project.id}` },
    links: { ref: hiddenLinkCols, defaults: defaultLinkColumns, storageKey: () => `test-data-links-hidden-cols-${props.project.id}` },
};

const loadHiddenColumns = () => {
    for (const [, entry] of Object.entries(hiddenColsMap)) {
        try {
            const saved = localStorage.getItem(entry.storageKey());
            if (saved) {
                const parsed = JSON.parse(saved) as string[];
                const validKeys = entry.defaults.filter(c => !c.fixed).map(c => c.key);
                entry.ref.value = parsed.filter(k => validKeys.includes(k));
            }
        } catch { /* ignore */ }
    }
};

const saveHiddenColumns = () => {
    for (const [, entry] of Object.entries(hiddenColsMap)) {
        try {
            localStorage.setItem(entry.storageKey(), JSON.stringify(entry.ref.value));
        } catch { /* ignore */ }
    }
};

const activeHiddenCols = computed(() => hiddenColsMap[activeTab.value].ref.value);

const activeAllColumns = computed(() => {
    const defaults = hiddenColsMap[activeTab.value].defaults;
    return defaults.filter(c => !c.fixed);
});

const hasHiddenColumns = computed(() => activeHiddenCols.value.length > 0);

const toggleColumnVisibility = (key: string) => {
    const arr = hiddenColsMap[activeTab.value].ref;
    const idx = arr.value.indexOf(key);
    if (idx >= 0) {
        arr.value.splice(idx, 1);
    } else {
        arr.value.push(key);
    }
    saveHiddenColumns();
};

const visibleUserColumns = computed(() => userColumns.value.filter(c => !hiddenUserCols.value.includes(c.key)));
const visiblePaymentColumns = computed(() => paymentColumns.value.filter(c => !hiddenPaymentCols.value.includes(c.key)));
const visibleCommandColumns = computed(() => commandColumns.value.filter(c => !hiddenCommandCols.value.includes(c.key)));
const visibleLinkColumns = computed(() => linkColumns.value.filter(c => !hiddenLinkCols.value.includes(c.key)));

onMounted(() => {
    loadColumnConfig();
    loadHiddenColumns();
});

// ===== Local mutable copies of row data =====
const localUsers = ref<TestUser[]>([...props.testUsers]);
const localPayments = ref<TestPaymentMethod[]>([...props.testPaymentMethods]);
const localCommands = ref<TestCommand[]>([...props.testCommands]);
const localLinks = ref<TestLink[]>([...props.testLinks]);

watch(() => props.testUsers, (newVal) => {
    localUsers.value = [...newVal];
}, { deep: true });

watch(() => props.testPaymentMethods, (newVal) => {
    localPayments.value = [...newVal];
}, { deep: true });

watch(() => props.testCommands, (newVal) => {
    localCommands.value = [...newVal];
}, { deep: true });

watch(() => props.testLinks, (newVal) => {
    localLinks.value = [...newVal];
}, { deep: true });

// Filtered users
const uniqueRoles = computed(() => {
    const roles = localUsers.value
        .map(u => u.role)
        .filter((r): r is string => r !== null && r !== '');
    return [...new Set(roles)].sort();
});

const filteredUsers = computed(() => {
    let users = localUsers.value;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        users = users.filter(u =>
            u.name.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q) ||
            (u.role && u.role.toLowerCase().includes(q)) ||
            (u.environment && u.environment.toLowerCase().includes(q)) ||
            (u.description && u.description.toLowerCase().includes(q)) ||
            (u.tags && u.tags.some(t => t.toLowerCase().includes(q)))
        );
    }

    if (validityFilter.value !== 'all') {
        const isValid = validityFilter.value === 'valid';
        users = users.filter(u => u.is_valid === isValid);
    }

    if (environmentFilter.value !== 'all') {
        users = users.filter(u => u.environment === environmentFilter.value);
    }

    if (roleFilter.value !== 'all') {
        users = users.filter(u => u.role === roleFilter.value);
    }

    return users;
});

// Filtered payments
const filteredPayments = computed(() => {
    let payments = localPayments.value;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        payments = payments.filter(p =>
            p.name.toLowerCase().includes(q) ||
            p.type.toLowerCase().includes(q) ||
            (p.system && p.system.toLowerCase().includes(q)) ||
            (p.environment && p.environment.toLowerCase().includes(q)) ||
            (p.description && p.description.toLowerCase().includes(q)) ||
            (p.tags && p.tags.some(t => t.toLowerCase().includes(q)))
        );
    }

    if (validityFilter.value !== 'all') {
        const isValid = validityFilter.value === 'valid';
        payments = payments.filter(p => p.is_valid === isValid);
    }

    if (environmentFilter.value !== 'all') {
        payments = payments.filter(p => p.environment === environmentFilter.value);
    }

    if (typeFilter.value !== 'all') {
        payments = payments.filter(p => p.type === typeFilter.value);
    }

    return payments;
});

// Category filter
const categoryFilter = ref<string>('all');

// Filtered commands
const uniqueCommandCategories = computed(() => {
    const cats = localCommands.value
        .map(c => c.category)
        .filter((c): c is string => c !== null && c !== '');
    return [...new Set(cats)].sort();
});

const filteredCommands = computed(() => {
    let commands = localCommands.value;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        commands = commands.filter(c =>
            c.description.toLowerCase().includes(q) ||
            c.command.toLowerCase().includes(q) ||
            (c.category && c.category.toLowerCase().includes(q)) ||
            (c.comment && c.comment.toLowerCase().includes(q))
        );
    }

    if (categoryFilter.value !== 'all') {
        commands = commands.filter(c => c.category === categoryFilter.value);
    }

    return commands;
});

// Filtered links
const uniqueLinkCategories = computed(() => {
    const cats = localLinks.value
        .map(l => l.category)
        .filter((c): c is string => c !== null && c !== '');
    return [...new Set(cats)].sort();
});

const filteredLinks = computed(() => {
    let links = localLinks.value;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        links = links.filter(l =>
            l.description.toLowerCase().includes(q) ||
            l.url.toLowerCase().includes(q) ||
            (l.category && l.category.toLowerCase().includes(q)) ||
            (l.comment && l.comment.toLowerCase().includes(q))
        );
    }

    if (categoryFilter.value !== 'all') {
        links = links.filter(l => l.category === categoryFilter.value);
    }

    return links;
});

// Category suggestions for dialogs
const allCommandCategories = computed(() => uniqueCommandCategories.value);
const allLinkCategories = computed(() => uniqueLinkCategories.value);

// ===== canDragRows =====
const isFiltered = computed(() =>
    searchQuery.value.trim() !== '' ||
    validityFilter.value !== 'all' ||
    environmentFilter.value !== 'all' ||
    roleFilter.value !== 'all' ||
    typeFilter.value !== 'all' ||
    categoryFilter.value !== 'all'
);

const canDragRows = computed(() => canEdit.value && !isFiltered.value);

// Bulk selection
const selectedUserIds = ref<Set<number>>(new Set());
const selectedPaymentIds = ref<Set<number>>(new Set());
const selectedCommandIds = ref<Set<number>>(new Set());
const selectedLinkIds = ref<Set<number>>(new Set());

watch(activeTab, () => {
    selectedUserIds.value = new Set();
    selectedPaymentIds.value = new Set();
    selectedCommandIds.value = new Set();
    selectedLinkIds.value = new Set();
    categoryFilter.value = 'all';
});

const toggleUserSelection = (id: number) => {
    const s = new Set(selectedUserIds.value);
    if (s.has(id)) { s.delete(id); } else { s.add(id); }
    selectedUserIds.value = s;
};

const toggleAllUsers = () => {
    if (selectedUserIds.value.size === filteredUsers.value.length) {
        selectedUserIds.value = new Set();
    } else {
        selectedUserIds.value = new Set(filteredUsers.value.map(u => u.id));
    }
};

const togglePaymentSelection = (id: number) => {
    const s = new Set(selectedPaymentIds.value);
    if (s.has(id)) { s.delete(id); } else { s.add(id); }
    selectedPaymentIds.value = s;
};

const toggleAllPayments = () => {
    if (selectedPaymentIds.value.size === filteredPayments.value.length) {
        selectedPaymentIds.value = new Set();
    } else {
        selectedPaymentIds.value = new Set(filteredPayments.value.map(p => p.id));
    }
};

const toggleCommandSelection = (id: number) => {
    const s = new Set(selectedCommandIds.value);
    if (s.has(id)) { s.delete(id); } else { s.add(id); }
    selectedCommandIds.value = s;
};

const toggleAllCommands = () => {
    if (selectedCommandIds.value.size === filteredCommands.value.length) {
        selectedCommandIds.value = new Set();
    } else {
        selectedCommandIds.value = new Set(filteredCommands.value.map(c => c.id));
    }
};

const toggleLinkSelection = (id: number) => {
    const s = new Set(selectedLinkIds.value);
    if (s.has(id)) { s.delete(id); } else { s.add(id); }
    selectedLinkIds.value = s;
};

const toggleAllLinks = () => {
    if (selectedLinkIds.value.size === filteredLinks.value.length) {
        selectedLinkIds.value = new Set();
    } else {
        selectedLinkIds.value = new Set(filteredLinks.value.map(l => l.id));
    }
};

// ===== Row Drag and Drop =====
type TableType = 'users' | 'payments' | 'commands' | 'links';
const draggedRowIndex = ref<number | null>(null);
const dragOverRowIndex = ref<number | null>(null);
const dragRowTable = ref<TableType | null>(null);

const onRowDragStart = (table: TableType, index: number, event: DragEvent) => {
    draggedRowIndex.value = index;
    dragRowTable.value = table;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', index.toString());
    }
};

const onRowDragOver = (index: number, event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverRowIndex.value = index;
};

const onRowDragLeave = () => {
    dragOverRowIndex.value = null;
};

const onRowDrop = (table: TableType, index: number, event: DragEvent) => {
    event.preventDefault();
    if (draggedRowIndex.value === null || draggedRowIndex.value === index || dragRowTable.value !== table) {
        draggedRowIndex.value = null;
        dragOverRowIndex.value = null;
        dragRowTable.value = null;
        return;
    }

    const tableConfig: Record<TableType, { local: { value: { id: number }[] }; endpoint: string }> = {
        users: { local: localUsers, endpoint: 'users-reorder' },
        payments: { local: localPayments, endpoint: 'payments-reorder' },
        commands: { local: localCommands, endpoint: 'commands-reorder' },
        links: { local: localLinks, endpoint: 'links-reorder' },
    };

    const cfg = tableConfig[table];
    const dragged = cfg.local.value[draggedRowIndex.value];
    cfg.local.value.splice(draggedRowIndex.value, 1);
    cfg.local.value.splice(index, 0, dragged);
    router.put(`/projects/${props.project.id}/test-data/${cfg.endpoint}`, {
        ids: cfg.local.value.map(item => item.id),
    }, { preserveScroll: true, preserveState: true });

    draggedRowIndex.value = null;
    dragOverRowIndex.value = null;
    dragRowTable.value = null;
};

const onRowDragEnd = () => {
    draggedRowIndex.value = null;
    dragOverRowIndex.value = null;
    dragRowTable.value = null;
};

// ===== Column Drag and Drop =====
const draggedColIndex = ref<number | null>(null);
const dragOverColIndex = ref<number | null>(null);
const dragColTable = ref<TableType | null>(null);

const onColDragStart = (table: TableType, index: number, event: DragEvent) => {
    draggedColIndex.value = index;
    dragColTable.value = table;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', index.toString());
    }
};

const onColDragOver = (index: number, event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverColIndex.value = index;
};

const onColDragLeave = () => {
    dragOverColIndex.value = null;
};

const onColDrop = (table: TableType, index: number, event: DragEvent) => {
    event.preventDefault();
    if (draggedColIndex.value === null || draggedColIndex.value === index || dragColTable.value !== table) {
        draggedColIndex.value = null;
        dragOverColIndex.value = null;
        dragColTable.value = null;
        return;
    }

    const colsMap: Record<TableType, typeof userColumns> = { users: userColumns, payments: paymentColumns, commands: commandColumns, links: linkColumns };
    const cols = colsMap[table];
    const dragged = cols.value[draggedColIndex.value];
    cols.value.splice(draggedColIndex.value, 1);
    cols.value.splice(index, 0, dragged);
    saveColumnConfig();

    draggedColIndex.value = null;
    dragOverColIndex.value = null;
    dragColTable.value = null;
};

const onColDragEnd = () => {
    draggedColIndex.value = null;
    dragOverColIndex.value = null;
    dragColTable.value = null;
};

// ===== Column Resize =====
const resizingCol = ref<{ table: TableType; index: number } | null>(null);
const resizeStartX = ref(0);
const resizeStartWidth = ref(0);

const startResize = (table: TableType, index: number, event: MouseEvent) => {
    resizingCol.value = { table, index };
    resizeStartX.value = event.clientX;
    const colsMap: Record<TableType, typeof userColumns> = { users: userColumns, payments: paymentColumns, commands: commandColumns, links: linkColumns };
    const cols = colsMap[table];
    resizeStartWidth.value = cols.value[index].width || 150;
    document.addEventListener('mousemove', onResize);
    document.addEventListener('mouseup', stopResize);
};

const onResize = (event: MouseEvent) => {
    if (!resizingCol.value) return;
    const diff = event.clientX - resizeStartX.value;
    const newWidth = Math.max(50, resizeStartWidth.value + diff);
    const colsMap: Record<TableType, typeof userColumns> = { users: userColumns, payments: paymentColumns, commands: commandColumns, links: linkColumns };
    const cols = colsMap[resizingCol.value.table];
    cols.value[resizingCol.value.index].width = newWidth;
};

const stopResize = () => {
    if (resizingCol.value) {
        saveColumnConfig();
    }
    resizingCol.value = null;
    document.removeEventListener('mousemove', onResize);
    document.removeEventListener('mouseup', stopResize);
};

onBeforeUnmount(() => {
    document.removeEventListener('mousemove', onResize);
    document.removeEventListener('mouseup', stopResize);
});

// ===== User Form =====
const showUserDialog = ref(false);
const editingUser = ref<TestUser | null>(null);

const userForm = useForm({
    name: '',
    email: '',
    password: '' as string | null,
    role: '' as string | null,
    environment: '' as string,
    description: '' as string | null,
    is_valid: true,
    tags: [] as string[],
});

const tagInput = ref('');

const addTag = () => {
    const tag = tagInput.value.trim();
    if (tag && !userForm.tags.includes(tag)) {
        userForm.tags.push(tag);
    }
    tagInput.value = '';
};

const removeTag = (index: number) => {
    userForm.tags.splice(index, 1);
};

const openAddUserDialog = () => {
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
    tagInput.value = '';
    showUserDialog.value = true;
};

const openEditUserDialog = (user: TestUser) => {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = user.password || '';
    userForm.role = user.role || '';
    userForm.environment = user.environment || '';
    userForm.description = user.description || '';
    userForm.is_valid = user.is_valid;
    userForm.tags = user.tags ? [...user.tags] : [];
    userForm.clearErrors();
    tagInput.value = '';
    showUserDialog.value = true;
};

const submitUserForm = () => {
    const data = {
        ...userForm.data(),
        role: userForm.role || null,
        environment: userForm.environment || null,
        description: userForm.description || null,
        password: userForm.password || null,
        tags: userForm.tags.length > 0 ? userForm.tags : null,
    };

    if (editingUser.value) {
        userForm.transform(() => data).put(`/projects/${props.project.id}/test-data/users/${editingUser.value!.id}`, {
            onSuccess: () => {
                showUserDialog.value = false;
                editingUser.value = null;
            },
        });
    } else {
        userForm.transform(() => data).post(`/projects/${props.project.id}/test-data/users`, {
            onSuccess: () => {
                showUserDialog.value = false;
                userForm.reset();
            },
        });
    }
};

// ===== Payment Form =====
const showPaymentDialog = ref(false);
const editingPayment = ref<TestPaymentMethod | null>(null);

const paymentForm = useForm({
    name: '',
    type: 'card' as string,
    system: '' as string | null,
    credentials: {} as Record<string, string>,
    environment: '' as string,
    is_valid: true,
    description: '' as string | null,
    tags: [] as string[],
});

const paymentTagInput = ref('');

const addPaymentTag = () => {
    const tag = paymentTagInput.value.trim();
    if (tag && !paymentForm.tags.includes(tag)) {
        paymentForm.tags.push(tag);
    }
    paymentTagInput.value = '';
};

const removePaymentTag = (index: number) => {
    paymentForm.tags.splice(index, 1);
};

const credentialFields = computed(() => {
    switch (paymentForm.type) {
        case 'card':
            return [
                { key: 'card_number', label: 'Card Number', placeholder: '4242 4242 4242 4242' },
                { key: 'expiry', label: 'Expiry', placeholder: 'MM/YY' },
                { key: 'cvv', label: 'CVV', placeholder: '123' },
                { key: 'cardholder', label: 'Cardholder', placeholder: 'John Doe' },
            ];
        case 'crypto':
            return [
                { key: 'wallet_address', label: 'Wallet Address', placeholder: '0x...' },
                { key: 'network', label: 'Network', placeholder: 'Ethereum' },
            ];
        case 'bank':
            return [
                { key: 'account_number', label: 'Account Number', placeholder: '12345678' },
                { key: 'routing_number', label: 'Routing Number', placeholder: '123456789' },
                { key: 'bank_name', label: 'Bank Name', placeholder: 'Bank of America' },
            ];
        case 'paypal':
            return [
                { key: 'email', label: 'PayPal Email', placeholder: 'user@example.com' },
            ];
        default:
            return [
                { key: 'note', label: 'Note', placeholder: 'Custom credentials...' },
            ];
    }
});

watch(() => paymentForm.type, () => {
    if (!editingPayment.value) {
        paymentForm.credentials = {};
    }
});

const openAddPaymentDialog = () => {
    editingPayment.value = null;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentTagInput.value = '';
    showPaymentDialog.value = true;
};

const openEditPaymentDialog = (payment: TestPaymentMethod) => {
    editingPayment.value = payment;
    paymentForm.name = payment.name;
    paymentForm.type = payment.type;
    paymentForm.system = payment.system || '';
    paymentForm.credentials = payment.credentials ? { ...payment.credentials } : {};
    paymentForm.environment = payment.environment || '';
    paymentForm.is_valid = payment.is_valid;
    paymentForm.description = payment.description || '';
    paymentForm.tags = payment.tags ? [...payment.tags] : [];
    paymentForm.clearErrors();
    paymentTagInput.value = '';
    showPaymentDialog.value = true;
};

const submitPaymentForm = () => {
    const data = {
        ...paymentForm.data(),
        system: paymentForm.system || null,
        environment: paymentForm.environment || null,
        description: paymentForm.description || null,
        credentials: Object.keys(paymentForm.credentials).length > 0 ? paymentForm.credentials : null,
        tags: paymentForm.tags.length > 0 ? paymentForm.tags : null,
    };

    if (editingPayment.value) {
        paymentForm.transform(() => data).put(`/projects/${props.project.id}/test-data/payments/${editingPayment.value!.id}`, {
            onSuccess: () => {
                showPaymentDialog.value = false;
                editingPayment.value = null;
            },
        });
    } else {
        paymentForm.transform(() => data).post(`/projects/${props.project.id}/test-data/payments`, {
            onSuccess: () => {
                showPaymentDialog.value = false;
                paymentForm.reset();
            },
        });
    }
};

// ===== Command Form =====
const showCommandDialog = ref(false);
const editingCommand = ref<TestCommand | null>(null);

const commandForm = useForm({
    category: '' as string | null,
    description: '',
    command: '',
    comment: '' as string | null,
});

const openAddCommandDialog = () => {
    editingCommand.value = null;
    commandForm.reset();
    commandForm.clearErrors();
    showCommandDialog.value = true;
};

const openEditCommandDialog = (cmd: TestCommand) => {
    editingCommand.value = cmd;
    commandForm.category = cmd.category || '';
    commandForm.description = cmd.description;
    commandForm.command = cmd.command;
    commandForm.comment = cmd.comment || '';
    commandForm.clearErrors();
    showCommandDialog.value = true;
};

const submitCommandForm = () => {
    const data = {
        ...commandForm.data(),
        category: commandForm.category || null,
        comment: commandForm.comment || null,
    };

    if (editingCommand.value) {
        commandForm.transform(() => data).put(`/projects/${props.project.id}/test-data/commands/${editingCommand.value!.id}`, {
            onSuccess: () => {
                showCommandDialog.value = false;
                editingCommand.value = null;
            },
        });
    } else {
        commandForm.transform(() => data).post(`/projects/${props.project.id}/test-data/commands`, {
            onSuccess: () => {
                showCommandDialog.value = false;
                commandForm.reset();
            },
        });
    }
};

// ===== Link Form =====
const showLinkDialog = ref(false);
const editingLink = ref<TestLink | null>(null);

const linkForm = useForm({
    category: '' as string | null,
    description: '',
    url: '',
    comment: '' as string | null,
});

const openAddLinkDialog = () => {
    editingLink.value = null;
    linkForm.reset();
    linkForm.clearErrors();
    showLinkDialog.value = true;
};

const openEditLinkDialog = (link: TestLink) => {
    editingLink.value = link;
    linkForm.category = link.category || '';
    linkForm.description = link.description;
    linkForm.url = link.url;
    linkForm.comment = link.comment || '';
    linkForm.clearErrors();
    showLinkDialog.value = true;
};

const submitLinkForm = () => {
    const data = {
        ...linkForm.data(),
        category: linkForm.category || null,
        comment: linkForm.comment || null,
    };

    if (editingLink.value) {
        linkForm.transform(() => data).put(`/projects/${props.project.id}/test-data/links/${editingLink.value!.id}`, {
            onSuccess: () => {
                showLinkDialog.value = false;
                editingLink.value = null;
            },
        });
    } else {
        linkForm.transform(() => data).post(`/projects/${props.project.id}/test-data/links`, {
            onSuccess: () => {
                showLinkDialog.value = false;
                linkForm.reset();
            },
        });
    }
};

// ===== Delete Dialogs =====
const showDeleteUserConfirm = ref(false);
const userToDelete = ref<TestUser | null>(null);

const confirmDeleteUser = (user: TestUser) => {
    userToDelete.value = user;
    showDeleteUserConfirm.value = true;
};

const deleteUser = () => {
    if (!userToDelete.value) return;
    router.delete(`/projects/${props.project.id}/test-data/users/${userToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteUserConfirm.value = false;
            userToDelete.value = null;
        },
    });
};

const showDeletePaymentConfirm = ref(false);
const paymentToDelete = ref<TestPaymentMethod | null>(null);

const confirmDeletePayment = (payment: TestPaymentMethod) => {
    paymentToDelete.value = payment;
    showDeletePaymentConfirm.value = true;
};

const deletePayment = () => {
    if (!paymentToDelete.value) return;
    router.delete(`/projects/${props.project.id}/test-data/payments/${paymentToDelete.value.id}`, {
        onSuccess: () => {
            showDeletePaymentConfirm.value = false;
            paymentToDelete.value = null;
        },
    });
};

const showDeleteCommandConfirm = ref(false);
const commandToDelete = ref<TestCommand | null>(null);

const confirmDeleteCommand = (cmd: TestCommand) => {
    commandToDelete.value = cmd;
    showDeleteCommandConfirm.value = true;
};

const deleteCommand = () => {
    if (!commandToDelete.value) return;
    router.delete(`/projects/${props.project.id}/test-data/commands/${commandToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteCommandConfirm.value = false;
            commandToDelete.value = null;
        },
    });
};

const showDeleteLinkConfirm = ref(false);
const linkToDelete = ref<TestLink | null>(null);

const confirmDeleteLink = (link: TestLink) => {
    linkToDelete.value = link;
    showDeleteLinkConfirm.value = true;
};

const deleteLink = () => {
    if (!linkToDelete.value) return;
    router.delete(`/projects/${props.project.id}/test-data/links/${linkToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteLinkConfirm.value = false;
            linkToDelete.value = null;
        },
    });
};

// ===== Bulk Delete =====
const showBulkDeleteConfirm = ref(false);
const bulkDeleteTarget = ref<TableType>('users');

const openBulkDelete = (target: TableType) => {
    bulkDeleteTarget.value = target;
    showBulkDeleteConfirm.value = true;
};

const bulkDeleteConfig: Record<TableType, { endpoint: string; ids: typeof selectedUserIds; label: string }> = {
    users: { endpoint: 'users-bulk', ids: selectedUserIds, label: 'Test Users' },
    payments: { endpoint: 'payments-bulk', ids: selectedPaymentIds, label: 'Payment Methods' },
    commands: { endpoint: 'commands-bulk', ids: selectedCommandIds, label: 'Commands' },
    links: { endpoint: 'links-bulk', ids: selectedLinkIds, label: 'Links' },
};

const executeBulkDelete = () => {
    const cfg = bulkDeleteConfig[bulkDeleteTarget.value];
    router.delete(`/projects/${props.project.id}/test-data/${cfg.endpoint}`, {
        data: { ids: [...cfg.ids.value] },
        onSuccess: () => {
            showBulkDeleteConfirm.value = false;
            cfg.ids.value = new Set();
        },
    });
};

// ===== CSV Export =====
const exportCsv = () => {
    let csv = '';
    if (activeTab.value === 'users') {
        const rows = selectedUserIds.value.size > 0
            ? filteredUsers.value.filter(u => selectedUserIds.value.has(u.id))
            : filteredUsers.value;
        csv = 'Name,Email,Password,Role,Environment,Valid,Tags,Description\n';
        rows.forEach(u => {
            csv += [
                `"${(u.name || '').replace(/"/g, '""')}"`,
                `"${(u.email || '').replace(/"/g, '""')}"`,
                `"${(u.password || '').replace(/"/g, '""')}"`,
                `"${(u.role || '').replace(/"/g, '""')}"`,
                `"${(u.environment || '').replace(/"/g, '""')}"`,
                u.is_valid ? 'Yes' : 'No',
                `"${(u.tags || []).join(', ').replace(/"/g, '""')}"`,
                `"${(u.description || '').replace(/"/g, '""')}"`,
            ].join(',') + '\n';
        });
    } else if (activeTab.value === 'payments') {
        const rows = selectedPaymentIds.value.size > 0
            ? filteredPayments.value.filter(p => selectedPaymentIds.value.has(p.id))
            : filteredPayments.value;
        csv = 'Name,Type,System,Credentials,Environment,Valid,Tags,Description\n';
        rows.forEach(p => {
            const creds = p.credentials
                ? Object.entries(p.credentials).map(([k, v]) => `${k}: ${v}`).join('; ')
                : '';
            csv += [
                `"${(p.name || '').replace(/"/g, '""')}"`,
                `"${(p.type || '').replace(/"/g, '""')}"`,
                `"${(p.system || '').replace(/"/g, '""')}"`,
                `"${creds.replace(/"/g, '""')}"`,
                `"${(p.environment || '').replace(/"/g, '""')}"`,
                p.is_valid ? 'Yes' : 'No',
                `"${(p.tags || []).join(', ').replace(/"/g, '""')}"`,
                `"${(p.description || '').replace(/"/g, '""')}"`,
            ].join(',') + '\n';
        });
    } else if (activeTab.value === 'commands') {
        const rows = selectedCommandIds.value.size > 0
            ? filteredCommands.value.filter(c => selectedCommandIds.value.has(c.id))
            : filteredCommands.value;
        csv = 'Category,Description,Command,Comment\n';
        rows.forEach(c => {
            csv += [
                `"${(c.category || '').replace(/"/g, '""')}"`,
                `"${(c.description || '').replace(/"/g, '""')}"`,
                `"${(c.command || '').replace(/"/g, '""')}"`,
                `"${(c.comment || '').replace(/"/g, '""')}"`,
            ].join(',') + '\n';
        });
    } else {
        const rows = selectedLinkIds.value.size > 0
            ? filteredLinks.value.filter(l => selectedLinkIds.value.has(l.id))
            : filteredLinks.value;
        csv = 'Category,Description,URL,Comment\n';
        rows.forEach(l => {
            csv += [
                `"${(l.category || '').replace(/"/g, '""')}"`,
                `"${(l.description || '').replace(/"/g, '""')}"`,
                `"${(l.url || '').replace(/"/g, '""')}"`,
                `"${(l.comment || '').replace(/"/g, '""')}"`,
            ].join(',') + '\n';
        });
    }

    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `test-${activeTab.value}-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

// ===== CSV/Excel Import =====
const showImportDialog = ref(false);
const importFile = ref<File | null>(null);
const importHeaders = ref<string[]>([]);
const importRows = ref<any[][]>([]);
const isImporting = ref(false);

const fieldAliasesPerTab: Record<string, Record<string, string[]>> = {
    users: {
        'Name': ['name', 'username', 'user name', 'full name'],
        'Email': ['email', 'e-mail', 'mail'],
        'Password': ['password', 'pass'],
        'Role': ['role', 'user role'],
        'Environment': ['environment', 'env'],
        'Valid': ['valid', 'is_valid', 'is valid', 'active'],
        'Tags': ['tags', 'labels', 'keywords'],
        'Description': ['description', 'notes', 'note', 'comment'],
    },
    payments: {
        'Name': ['name', 'payment name', 'method name'],
        'Type': ['type', 'payment type', 'method type'],
        'System': ['system', 'payment system', 'provider'],
        'Credentials': ['credentials', 'creds', 'card number', 'account'],
        'Environment': ['environment', 'env'],
        'Valid': ['valid', 'is_valid', 'is valid', 'active'],
        'Tags': ['tags', 'labels', 'keywords'],
        'Description': ['description', 'notes', 'note', 'comment'],
    },
    commands: {
        'Category': ['category', 'group', 'type'],
        'Description': ['description', 'name', 'title'],
        'Command': ['command', 'cmd', 'script'],
        'Comment': ['comment', 'note', 'notes'],
    },
    links: {
        'Category': ['category', 'group', 'type'],
        'Description': ['description', 'name', 'title'],
        'URL': ['url', 'link', 'href', 'address'],
        'Comment': ['comment', 'note', 'notes'],
    },
};

const getImportMatchedField = (header: string): string | null => {
    const aliases = fieldAliasesPerTab[activeTab.value] || {};
    const normalized = header.toLowerCase().trim();
    for (const [field, aliasList] of Object.entries(aliases)) {
        if (aliasList.includes(normalized)) return field;
    }
    return null;
};

const importFieldMapping = computed(() => {
    return importHeaders.value.map(h => ({
        header: h,
        matchedField: getImportMatchedField(h),
    }));
});

const matchedFieldCount = computed(() => importFieldMapping.value.filter(m => m.matchedField).length);

const onImportFileChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    importFile.value = file;
    importHeaders.value = [];
    importRows.value = [];

    try {
        const XLSX = await import('xlsx');
        const data = await file.arrayBuffer();
        const workbook = XLSX.read(data, { type: 'array' });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const json: any[][] = XLSX.utils.sheet_to_json(sheet, { header: 1 });

        if (json.length < 2) return;

        importHeaders.value = (json[0] || []).map(String);
        importRows.value = json.slice(1).filter(row => row.some(cell => cell !== null && cell !== undefined && String(cell).trim() !== ''));
    } catch {
        importHeaders.value = [];
        importRows.value = [];
    }
};

const openImportDialog = () => {
    importFile.value = null;
    importHeaders.value = [];
    importRows.value = [];
    showImportDialog.value = true;
};

const buildImportPayload = () => {
    const mapping = importFieldMapping.value;
    return importRows.value.map(row => {
        const obj: Record<string, any> = {};
        mapping.forEach((m, i) => {
            if (m.matchedField) {
                const key = m.matchedField.toLowerCase().replace(/ /g, '_');
                obj[key] = row[i] !== undefined && row[i] !== null ? String(row[i]) : '';
            }
        });
        return obj;
    });
};

const submitImport = () => {
    if (importRows.value.length === 0 || matchedFieldCount.value === 0) return;
    isImporting.value = true;

    const rows = buildImportPayload();
    const routeMap: Record<string, string> = {
        users: `/projects/${props.project.id}/test-data/users-import`,
        payments: `/projects/${props.project.id}/test-data/payments-import`,
        commands: `/projects/${props.project.id}/test-data/commands-import`,
        links: `/projects/${props.project.id}/test-data/links-import`,
    };

    router.post(routeMap[activeTab.value], { rows }, {
        preserveState: false,
        onSuccess: () => {
            showImportDialog.value = false;
            isImporting.value = false;
        },
        onError: () => {
            isImporting.value = false;
        },
    });
};

// Type badge colors
const typeBadgeClass = (type: string) => {
    switch (type) {
        case 'card': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'crypto': return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300';
        case 'bank': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'paypal': return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const environmentBadgeClass = (env: string) => {
    switch (env) {
        case 'develop': return 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-300';
        case 'staging': return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300';
        case 'production': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const environmentLabel = (env: string) => {
    switch (env) {
        case 'develop': return 'Develop';
        case 'staging': return 'Staging';
        case 'production': return 'Production';
        default: return env;
    }
};

const formatCredentials = (creds: Record<string, string> | null): string => {
    if (!creds) return '';
    return Object.entries(creds).map(([k, v]) => `${k.replace(/_/g, ' ')}: ${v}`).join(', ');
};

const formatCredentialsValues = (creds: Record<string, string> | null): string => {
    if (!creds) return '';
    const values = Object.values(creds);
    return values[0] ?? '';
};
</script>

<template>
    <Head title="Test Data" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                    <Database class="mt-1 h-6 w-6 shrink-0 text-primary" />
                    Test Data
                </h1>
                <RestrictedAction>
                    <Button
                        v-if="activeTab === 'users'"
                        variant="cta"
                        class="gap-2 cursor-pointer"
                        @click="openAddUserDialog"
                    >
                        <Plus class="h-4 w-4" />
                        Add User
                    </Button>
                    <Button
                        v-else-if="activeTab === 'payments'"
                        variant="cta"
                        class="gap-2 cursor-pointer"
                        @click="openAddPaymentDialog"
                    >
                        <Plus class="h-4 w-4" />
                        Add Payment
                    </Button>
                    <Button
                        v-else-if="activeTab === 'commands'"
                        variant="cta"
                        class="gap-2 cursor-pointer"
                        @click="openAddCommandDialog"
                    >
                        <Plus class="h-4 w-4" />
                        Add Command
                    </Button>
                    <Button
                        v-else
                        variant="cta"
                        class="gap-2 cursor-pointer"
                        @click="openAddLinkDialog"
                    >
                        <Plus class="h-4 w-4" />
                        Add Link
                    </Button>
                </RestrictedAction>
            </div>

            <!-- Tab Buttons -->
            <div class="flex items-center gap-2">
                <Button
                    :variant="activeTab === 'users' ? 'default' : 'outline'"
                    class="gap-2 cursor-pointer"
                    @click="activeTab = 'users'"
                >
                    <Users class="h-4 w-4" />
                    Test Users
                    <Badge variant="secondary" class="ml-1">{{ localUsers.length }}</Badge>
                </Button>
                <Button
                    :variant="activeTab === 'payments' ? 'default' : 'outline'"
                    class="gap-2 cursor-pointer"
                    @click="activeTab = 'payments'"
                >
                    <CreditCard class="h-4 w-4" />
                    Payment Methods
                    <Badge variant="secondary" class="ml-1">{{ localPayments.length }}</Badge>
                </Button>
                <Button
                    :variant="activeTab === 'commands' ? 'default' : 'outline'"
                    class="gap-2 cursor-pointer"
                    @click="activeTab = 'commands'"
                >
                    <Terminal class="h-4 w-4" />
                    Commands
                    <Badge variant="secondary" class="ml-1">{{ localCommands.length }}</Badge>
                </Button>
                <Button
                    :variant="activeTab === 'links' ? 'default' : 'outline'"
                    class="gap-2 cursor-pointer"
                    @click="activeTab = 'links'"
                >
                    <Link2 class="h-4 w-4" />
                    Links
                    <Badge variant="secondary" class="ml-1">{{ localLinks.length }}</Badge>
                </Button>
            </div>

            <!-- Toolbar -->
            <div v-if="(activeTab === 'users' && localUsers.length > 0) || (activeTab === 'payments' && localPayments.length > 0) || (activeTab === 'commands' && localCommands.length > 0) || (activeTab === 'links' && localLinks.length > 0)" class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search..."
                        class="w-48 pl-9 pr-8 bg-background/60"
                    />
                    <button
                        v-if="searchQuery"
                        class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                        @click="searchQuery = ''"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Validity filter (users/payments only) -->
                <Select v-if="activeTab === 'users' || activeTab === 'payments'" v-model="validityFilter">
                    <SelectTrigger class="w-32 cursor-pointer bg-background/60">
                        <SelectValue placeholder="Validity" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All</SelectItem>
                        <SelectItem value="valid" class="cursor-pointer">Valid</SelectItem>
                        <SelectItem value="invalid" class="cursor-pointer">Invalid</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Environment filter (users/payments only) -->
                <Select v-if="activeTab === 'users' || activeTab === 'payments'" v-model="environmentFilter">
                    <SelectTrigger class="w-36 cursor-pointer bg-background/60">
                        <SelectValue placeholder="Environment" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All Envs</SelectItem>
                        <SelectItem value="develop" class="cursor-pointer">Develop</SelectItem>
                        <SelectItem value="staging" class="cursor-pointer">Staging</SelectItem>
                        <SelectItem value="production" class="cursor-pointer">Production</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Category filter (commands/links) -->
                <Select v-if="activeTab === 'commands' && uniqueCommandCategories.length > 0" v-model="categoryFilter">
                    <SelectTrigger class="w-36 cursor-pointer bg-background/60">
                        <SelectValue placeholder="Category" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All Categories</SelectItem>
                        <SelectItem v-for="cat in uniqueCommandCategories" :key="cat" :value="cat" class="cursor-pointer">
                            {{ cat }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-if="activeTab === 'links' && uniqueLinkCategories.length > 0" v-model="categoryFilter">
                    <SelectTrigger class="w-36 cursor-pointer bg-background/60">
                        <SelectValue placeholder="Category" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All Categories</SelectItem>
                        <SelectItem v-for="cat in uniqueLinkCategories" :key="cat" :value="cat" class="cursor-pointer">
                            {{ cat }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Role filter (users tab) -->
                <Select v-if="activeTab === 'users' && uniqueRoles.length > 0" v-model="roleFilter">
                    <SelectTrigger class="w-36 cursor-pointer bg-background/60">
                        <SelectValue placeholder="Role" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All Roles</SelectItem>
                        <SelectItem v-for="role in uniqueRoles" :key="role" :value="role" class="cursor-pointer">
                            {{ role }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Type filter (payments tab) -->
                <Select v-if="activeTab === 'payments'" v-model="typeFilter">
                    <SelectTrigger class="w-36 cursor-pointer bg-background/60">
                        <SelectValue placeholder="Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All Types</SelectItem>
                        <SelectItem value="card" class="cursor-pointer">Card</SelectItem>
                        <SelectItem value="crypto" class="cursor-pointer">Crypto</SelectItem>
                        <SelectItem value="bank" class="cursor-pointer">Bank</SelectItem>
                        <SelectItem value="paypal" class="cursor-pointer">PayPal</SelectItem>
                        <SelectItem value="other" class="cursor-pointer">Other</SelectItem>
                    </SelectContent>
                </Select>

                <div class="ml-auto flex items-center gap-2">
                    <!-- Columns visibility dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button :variant="hasHiddenColumns ? 'default' : 'outline'" size="sm" class="gap-1.5 text-xs cursor-pointer">
                                <Columns3 class="h-3.5 w-3.5" />
                                Cols
                                <span v-if="hasHiddenColumns" class="ml-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-background/20 text-[10px] font-medium">
                                    {{ activeHiddenCols.length }}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Toggle Columns</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="col in activeAllColumns"
                                :key="col.key"
                                class="cursor-pointer"
                                @select.prevent="toggleColumnVisibility(col.key)"
                            >
                                <Check v-if="!activeHiddenCols.includes(col.key)" class="h-4 w-4 mr-2" />
                                <span v-else class="h-4 w-4 mr-2" />
                                {{ col.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="gap-1.5 text-xs cursor-pointer">
                                <FileSpreadsheet class="h-3.5 w-3.5" />
                                File
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem class="cursor-pointer" @click="openImportDialog">
                                <Download class="h-4 w-4 mr-2" />
                                Import
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="cursor-pointer"
                                :disabled="(activeTab === 'users' ? filteredUsers : activeTab === 'payments' ? filteredPayments : activeTab === 'commands' ? filteredCommands : filteredLinks).length === 0"
                                @click="exportCsv"
                            >
                                <Upload class="h-4 w-4 mr-2" />
                                {{ (activeTab === 'users' && selectedUserIds.size > 0) ? `Export Selected (${selectedUserIds.size})`
                                    : (activeTab === 'payments' && selectedPaymentIds.size > 0) ? `Export Selected (${selectedPaymentIds.size})`
                                    : (activeTab === 'commands' && selectedCommandIds.size > 0) ? `Export Selected (${selectedCommandIds.size})`
                                    : (activeTab === 'links' && selectedLinkIds.size > 0) ? `Export Selected (${selectedLinkIds.size})`
                                    : 'Export All' }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <RestrictedAction>
                        <Button
                            v-if="activeTab === 'users' && selectedUserIds.size > 0"
                            variant="destructive"
                            size="sm"
                            class="gap-2 cursor-pointer"
                            @click="openBulkDelete('users')"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete ({{ selectedUserIds.size }})
                        </Button>
                        <Button
                            v-if="activeTab === 'payments' && selectedPaymentIds.size > 0"
                            variant="destructive"
                            size="sm"
                            class="gap-2 cursor-pointer"
                            @click="openBulkDelete('payments')"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete ({{ selectedPaymentIds.size }})
                        </Button>
                        <Button
                            v-if="activeTab === 'commands' && selectedCommandIds.size > 0"
                            variant="destructive"
                            size="sm"
                            class="gap-2 cursor-pointer"
                            @click="openBulkDelete('commands')"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete ({{ selectedCommandIds.size }})
                        </Button>
                        <Button
                            v-if="activeTab === 'links' && selectedLinkIds.size > 0"
                            variant="destructive"
                            size="sm"
                            class="gap-2 cursor-pointer"
                            @click="openBulkDelete('links')"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete ({{ selectedLinkIds.size }})
                        </Button>
                    </RestrictedAction>

                </div>
            </div>

            <!-- Users Tab Content -->
            <template v-if="activeTab === 'users'">
                <!-- Empty state -->
                <div v-if="localUsers.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <Users class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No test users yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Add test user credentials for your QA team.
                        </p>
                        <RestrictedAction>
                            <Button variant="cta" class="mt-4 gap-2 cursor-pointer" @click="openAddUserDialog">
                                <Plus class="h-4 w-4" />
                                Add User
                            </Button>
                        </RestrictedAction>
                    </div>
                </div>

                <!-- No results -->
                <div v-else-if="filteredUsers.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <Search class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No matching users</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Try adjusting your search or filters.
                        </p>
                        <Button variant="secondary" class="mt-4 cursor-pointer" @click="searchQuery = ''; validityFilter = 'all'; environmentFilter = 'all'; roleFilter = 'all'">
                            Clear filters
                        </Button>
                    </div>
                </div>

                <!-- Users table -->
                <Card v-else>
                    <CardContent class="p-0">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="table-layout: fixed">
                        <thead>
                            <tr class="border-b bg-muted">
                                <th class="w-6 px-1 py-3"></th>
                                <th
                                    v-for="(col, colIndex) in visibleUserColumns"
                                    :key="col.key"
                                    class="px-3 py-3 text-left font-medium relative select-none"
                                    :style="{ width: col.width + 'px' }"
                                    :class="{
                                        'text-right': col.key === 'actions',
                                        'bg-primary/10': dragOverColIndex === colIndex && dragColTable === 'users',
                                        'opacity-50': draggedColIndex === colIndex && dragColTable === 'users',
                                    }"
                                    @dragover="!col.fixed && onColDragOver(colIndex, $event)"
                                    @dragleave="onColDragLeave"
                                    @drop="!col.fixed && onColDrop('users', colIndex, $event)"
                                >
                                    <div class="flex items-center gap-1">
                                        <div
                                            v-if="!col.fixed"
                                            draggable="true"
                                            class="shrink-0 cursor-grab active:cursor-grabbing p-0.5 rounded hover:bg-muted"
                                            @dragstart="onColDragStart('users', colIndex, $event)"
                                            @dragend="onColDragEnd"
                                        >
                                            <GripHorizontal class="h-3 w-3 text-muted-foreground/50" />
                                        </div>
                                        <template v-if="col.key === 'checkbox'">
                                            <Checkbox
                                                :model-value="selectedUserIds.size === filteredUsers.length && filteredUsers.length > 0"
                                                class="cursor-pointer"
                                                @update:model-value="toggleAllUsers"
                                            />
                                        </template>
                                        <span v-else-if="col.key !== 'actions'" class="truncate">{{ col.label }}</span>
                                        <span v-else class="ml-auto">{{ col.label }}</span>
                                    </div>
                                    <!-- Resize handle -->
                                    <div
                                        v-if="!col.fixed"
                                        class="absolute -right-1 top-0 bottom-0 w-3 cursor-col-resize group"
                                        @mousedown.stop="startResize('users', colIndex, $event)"
                                    >
                                        <div class="absolute right-1 top-0 bottom-0 w-0.5 group-hover:bg-primary/50 group-active:bg-primary" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(user, index) in filteredUsers"
                                :key="user.id"
                                class="border-b transition-colors hover:bg-muted/50"
                                :class="{
                                    'border-t-2 border-t-primary': dragOverRowIndex === index && dragRowTable === 'users' && canDragRows,
                                    'opacity-50': draggedRowIndex === index && dragRowTable === 'users' && canDragRows,
                                }"
                                @dragover="canDragRows && onRowDragOver(index, $event)"
                                @dragleave="onRowDragLeave"
                                @drop="canDragRows && onRowDrop('users', index, $event)"
                            >
                                <td class="px-1 py-2 align-top">
                                    <div
                                        :draggable="canDragRows"
                                        @dragstart="canDragRows && onRowDragStart('users', index, $event)"
                                        @dragend="onRowDragEnd"
                                        :class="canDragRows ? 'cursor-grab active:cursor-grabbing' : 'cursor-default opacity-30'"
                                    >
                                        <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                    </div>
                                </td>
                                <td
                                    v-for="col in visibleUserColumns"
                                    :key="col.key"
                                    class="px-3 py-2"
                                    :class="{
                                        'max-w-[200px]': col.key === 'description',
                                    }"
                                >
                                    <template v-if="col.key === 'checkbox'">
                                        <Checkbox
                                            :model-value="selectedUserIds.has(user.id)"
                                            class="cursor-pointer"
                                            @update:model-value="toggleUserSelection(user.id)"
                                        />
                                    </template>
                                    <template v-else-if="col.key === 'name'">
                                        <span class="font-medium">{{ user.name }}</span>
                                    </template>
                                    <template v-else-if="col.key === 'email'">
                                        <div class="flex items-center gap-1">
                                            <span class="truncate max-w-[180px]">{{ user.email }}</span>
                                            <button
                                                class="shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                title="Copy email"
                                                @click="copyToClipboard(user.email, `email-${user.id}`)"
                                            >
                                                <Check v-if="copiedField === `email-${user.id}`" class="h-3.5 w-3.5 text-green-500" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else-if="col.key === 'password'">
                                        <div v-if="user.password" class="flex items-center gap-1">
                                            <span class="truncate max-w-[120px] font-mono text-xs">
                                                {{ visiblePasswords.has(user.id) ? user.password : '••••••••' }}
                                            </span>
                                            <button
                                                class="shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                @click="togglePasswordVisibility(user.id)"
                                            >
                                                <EyeOff v-if="visiblePasswords.has(user.id)" class="h-3.5 w-3.5" />
                                                <Eye v-else class="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                class="shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                title="Copy password"
                                                @click="copyToClipboard(user.password, `pass-${user.id}`)"
                                            >
                                                <Check v-if="copiedField === `pass-${user.id}`" class="h-3.5 w-3.5 text-green-500" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'role'">
                                        <Badge v-if="user.role" variant="secondary">{{ user.role }}</Badge>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'environment'">
                                        <span v-if="user.environment" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="environmentBadgeClass(user.environment)">
                                            {{ environmentLabel(user.environment) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'valid'">
                                        <Badge :variant="user.is_valid ? 'default' : 'destructive'">
                                            {{ user.is_valid ? 'Valid' : 'Invalid' }}
                                        </Badge>
                                    </template>
                                    <template v-else-if="col.key === 'tags'">
                                        <div v-if="user.tags && user.tags.length > 0" class="flex flex-wrap gap-1">
                                            <Badge v-for="tag in user.tags" :key="tag" variant="outline" class="text-xs">{{ tag }}</Badge>
                                        </div>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'description'">
                                        <span v-if="user.description" class="line-clamp-1 text-muted-foreground" :title="user.description">{{ user.description }}</span>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'actions'">
                                        <div class="flex justify-end gap-1">
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer" @click="openEditUserDialog(user)">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer text-destructive hover:text-destructive" @click="confirmDeleteUser(user)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Payments Tab Content -->
            <template v-if="activeTab === 'payments'">
                <!-- Empty state -->
                <div v-if="localPayments.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <CreditCard class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No payment methods yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Add test payment methods for your QA team.
                        </p>
                        <RestrictedAction>
                            <Button variant="cta" class="mt-4 gap-2 cursor-pointer" @click="openAddPaymentDialog">
                                <Plus class="h-4 w-4" />
                                Add Payment
                            </Button>
                        </RestrictedAction>
                    </div>
                </div>

                <!-- No results -->
                <div v-else-if="filteredPayments.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <Search class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No matching payment methods</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Try adjusting your search or filters.
                        </p>
                        <Button variant="secondary" class="mt-4 cursor-pointer" @click="searchQuery = ''; validityFilter = 'all'; environmentFilter = 'all'; typeFilter = 'all'">
                            Clear filters
                        </Button>
                    </div>
                </div>

                <!-- Payments table -->
                <Card v-else>
                    <CardContent class="p-0">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="table-layout: fixed">
                        <thead>
                            <tr class="border-b bg-muted">
                                <th class="w-6 px-1 py-3"></th>
                                <th
                                    v-for="(col, colIndex) in visiblePaymentColumns"
                                    :key="col.key"
                                    class="px-3 py-3 text-left font-medium relative select-none"
                                    :style="{ width: col.width + 'px' }"
                                    :class="{
                                        'text-right': col.key === 'actions',
                                        'bg-primary/10': dragOverColIndex === colIndex && dragColTable === 'payments',
                                        'opacity-50': draggedColIndex === colIndex && dragColTable === 'payments',
                                    }"
                                    @dragover="!col.fixed && onColDragOver(colIndex, $event)"
                                    @dragleave="onColDragLeave"
                                    @drop="!col.fixed && onColDrop('payments', colIndex, $event)"
                                >
                                    <div class="flex items-center gap-1">
                                        <div
                                            v-if="!col.fixed"
                                            draggable="true"
                                            class="shrink-0 cursor-grab active:cursor-grabbing p-0.5 rounded hover:bg-muted"
                                            @dragstart="onColDragStart('payments', colIndex, $event)"
                                            @dragend="onColDragEnd"
                                        >
                                            <GripHorizontal class="h-3 w-3 text-muted-foreground/50" />
                                        </div>
                                        <template v-if="col.key === 'checkbox'">
                                            <Checkbox
                                                :model-value="selectedPaymentIds.size === filteredPayments.length && filteredPayments.length > 0"
                                                class="cursor-pointer"
                                                @update:model-value="toggleAllPayments"
                                            />
                                        </template>
                                        <span v-else-if="col.key !== 'actions'" class="truncate">{{ col.label }}</span>
                                        <span v-else class="ml-auto">{{ col.label }}</span>
                                    </div>
                                    <!-- Resize handle -->
                                    <div
                                        v-if="!col.fixed"
                                        class="absolute -right-1 top-0 bottom-0 w-3 cursor-col-resize group"
                                        @mousedown.stop="startResize('payments', colIndex, $event)"
                                    >
                                        <div class="absolute right-1 top-0 bottom-0 w-0.5 group-hover:bg-primary/50 group-active:bg-primary" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(payment, index) in filteredPayments"
                                :key="payment.id"
                                class="border-b transition-colors hover:bg-muted/50"
                                :class="{
                                    'border-t-2 border-t-primary': dragOverRowIndex === index && dragRowTable === 'payments' && canDragRows,
                                    'opacity-50': draggedRowIndex === index && dragRowTable === 'payments' && canDragRows,
                                }"
                                @dragover="canDragRows && onRowDragOver(index, $event)"
                                @dragleave="onRowDragLeave"
                                @drop="canDragRows && onRowDrop('payments', index, $event)"
                            >
                                <td class="px-1 py-2 align-top">
                                    <div
                                        :draggable="canDragRows"
                                        @dragstart="canDragRows && onRowDragStart('payments', index, $event)"
                                        @dragend="onRowDragEnd"
                                        :class="canDragRows ? 'cursor-grab active:cursor-grabbing' : 'cursor-default opacity-30'"
                                    >
                                        <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                    </div>
                                </td>
                                <td
                                    v-for="col in visiblePaymentColumns"
                                    :key="col.key"
                                    class="px-3 py-2"
                                    :class="{
                                        'max-w-[200px]': col.key === 'description',
                                    }"
                                >
                                    <template v-if="col.key === 'checkbox'">
                                        <Checkbox
                                            :model-value="selectedPaymentIds.has(payment.id)"
                                            class="cursor-pointer"
                                            @update:model-value="togglePaymentSelection(payment.id)"
                                        />
                                    </template>
                                    <template v-else-if="col.key === 'name'">
                                        <span class="font-medium">{{ payment.name }}</span>
                                    </template>
                                    <template v-else-if="col.key === 'type'">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="typeBadgeClass(payment.type)">
                                            {{ payment.type }}
                                        </span>
                                    </template>
                                    <template v-else-if="col.key === 'system'">
                                        <span v-if="payment.system">{{ payment.system }}</span>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'credentials'">
                                        <div v-if="payment.credentials" class="flex items-center gap-1">
                                            <span class="truncate max-w-[200px] font-mono text-xs">
                                                {{ formatCredentials(payment.credentials) }}
                                            </span>
                                            <button
                                                class="shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                title="Copy credentials"
                                                @click="copyToClipboard(formatCredentialsValues(payment.credentials), `cred-${payment.id}`)"
                                            >
                                                <Check v-if="copiedField === `cred-${payment.id}`" class="h-3.5 w-3.5 text-green-500" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'environment'">
                                        <span v-if="payment.environment" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="environmentBadgeClass(payment.environment)">
                                            {{ environmentLabel(payment.environment) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'valid'">
                                        <Badge :variant="payment.is_valid ? 'default' : 'destructive'">
                                            {{ payment.is_valid ? 'Valid' : 'Invalid' }}
                                        </Badge>
                                    </template>
                                    <template v-else-if="col.key === 'tags'">
                                        <div v-if="payment.tags && payment.tags.length > 0" class="flex flex-wrap gap-1">
                                            <Badge v-for="tag in payment.tags" :key="tag" variant="outline" class="text-xs">{{ tag }}</Badge>
                                        </div>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'description'">
                                        <span v-if="payment.description" class="line-clamp-1 text-muted-foreground" :title="payment.description">{{ payment.description }}</span>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'actions'">
                                        <div class="flex justify-end gap-1">
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer" @click="openEditPaymentDialog(payment)">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer text-destructive hover:text-destructive" @click="confirmDeletePayment(payment)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Commands Tab Content -->
            <template v-if="activeTab === 'commands'">
                <div v-if="localCommands.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <Terminal class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No commands yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Store useful commands for deploy, database, testing, etc.
                        </p>
                        <RestrictedAction>
                            <Button variant="cta" class="mt-4 gap-2 cursor-pointer" @click="openAddCommandDialog">
                                <Plus class="h-4 w-4" />
                                Add Command
                            </Button>
                        </RestrictedAction>
                    </div>
                </div>

                <div v-else-if="filteredCommands.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <Search class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No matching commands</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Try adjusting your search or filters.
                        </p>
                        <Button variant="secondary" class="mt-4 cursor-pointer" @click="searchQuery = ''; categoryFilter = 'all'">
                            Clear filters
                        </Button>
                    </div>
                </div>

                <Card v-else>
                    <CardContent class="p-0">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="table-layout: fixed">
                        <thead>
                            <tr class="border-b bg-muted">
                                <th class="w-6 px-1 py-3"></th>
                                <th
                                    v-for="(col, colIndex) in visibleCommandColumns"
                                    :key="col.key"
                                    class="px-3 py-3 text-left font-medium relative select-none"
                                    :style="{ width: col.width + 'px' }"
                                    :class="{
                                        'text-right': col.key === 'actions',
                                        'bg-primary/10': dragOverColIndex === colIndex && dragColTable === 'commands',
                                        'opacity-50': draggedColIndex === colIndex && dragColTable === 'commands',
                                    }"
                                    @dragover="!col.fixed && onColDragOver(colIndex, $event)"
                                    @dragleave="onColDragLeave"
                                    @drop="!col.fixed && onColDrop('commands', colIndex, $event)"
                                >
                                    <div class="flex items-center gap-1">
                                        <div
                                            v-if="!col.fixed"
                                            draggable="true"
                                            class="shrink-0 cursor-grab active:cursor-grabbing p-0.5 rounded hover:bg-muted"
                                            @dragstart="onColDragStart('commands', colIndex, $event)"
                                            @dragend="onColDragEnd"
                                        >
                                            <GripHorizontal class="h-3 w-3 text-muted-foreground/50" />
                                        </div>
                                        <template v-if="col.key === 'checkbox'">
                                            <Checkbox
                                                :model-value="selectedCommandIds.size === filteredCommands.length && filteredCommands.length > 0"
                                                class="cursor-pointer"
                                                @update:model-value="toggleAllCommands"
                                            />
                                        </template>
                                        <span v-else-if="col.key !== 'actions'" class="truncate">{{ col.label }}</span>
                                        <span v-else class="ml-auto">{{ col.label }}</span>
                                    </div>
                                    <div
                                        v-if="!col.fixed"
                                        class="absolute -right-1 top-0 bottom-0 w-3 cursor-col-resize group"
                                        @mousedown.stop="startResize('commands', colIndex, $event)"
                                    >
                                        <div class="absolute right-1 top-0 bottom-0 w-0.5 group-hover:bg-primary/50 group-active:bg-primary" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(cmd, index) in filteredCommands"
                                :key="cmd.id"
                                class="border-b transition-colors hover:bg-muted/50"
                                :class="{
                                    'border-t-2 border-t-primary': dragOverRowIndex === index && dragRowTable === 'commands' && canDragRows,
                                    'opacity-50': draggedRowIndex === index && dragRowTable === 'commands' && canDragRows,
                                }"
                                @dragover="canDragRows && onRowDragOver(index, $event)"
                                @dragleave="onRowDragLeave"
                                @drop="canDragRows && onRowDrop('commands', index, $event)"
                            >
                                <td class="px-1 py-2 align-top">
                                    <div
                                        :draggable="canDragRows"
                                        @dragstart="canDragRows && onRowDragStart('commands', index, $event)"
                                        @dragend="onRowDragEnd"
                                        :class="canDragRows ? 'cursor-grab active:cursor-grabbing' : 'cursor-default opacity-30'"
                                    >
                                        <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                    </div>
                                </td>
                                <td
                                    v-for="col in visibleCommandColumns"
                                    :key="col.key"
                                    class="px-3 py-2"
                                >
                                    <template v-if="col.key === 'checkbox'">
                                        <Checkbox
                                            :model-value="selectedCommandIds.has(cmd.id)"
                                            class="cursor-pointer"
                                            @update:model-value="toggleCommandSelection(cmd.id)"
                                        />
                                    </template>
                                    <template v-else-if="col.key === 'category'">
                                        <Badge v-if="cmd.category" variant="secondary">{{ cmd.category }}</Badge>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'description'">
                                        <span class="font-medium">{{ cmd.description }}</span>
                                    </template>
                                    <template v-else-if="col.key === 'command'">
                                        <div class="flex items-center gap-1">
                                            <code class="truncate max-w-[280px] rounded bg-muted px-1.5 py-0.5 text-xs font-mono">{{ cmd.command }}</code>
                                            <button
                                                class="shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                title="Copy command"
                                                @click="copyToClipboard(cmd.command, `cmd-${cmd.id}`)"
                                            >
                                                <Check v-if="copiedField === `cmd-${cmd.id}`" class="h-3.5 w-3.5 text-green-500" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else-if="col.key === 'comment'">
                                        <span v-if="cmd.comment" class="line-clamp-1 text-muted-foreground" :title="cmd.comment">{{ cmd.comment }}</span>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'actions'">
                                        <div class="flex justify-end gap-1">
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer" @click="openEditCommandDialog(cmd)">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer text-destructive hover:text-destructive" @click="confirmDeleteCommand(cmd)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Links Tab Content -->
            <template v-if="activeTab === 'links'">
                <div v-if="localLinks.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <Link2 class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No links yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Store reference URLs with descriptions and comments.
                        </p>
                        <RestrictedAction>
                            <Button variant="cta" class="mt-4 gap-2 cursor-pointer" @click="openAddLinkDialog">
                                <Plus class="h-4 w-4" />
                                Add Link
                            </Button>
                        </RestrictedAction>
                    </div>
                </div>

                <div v-else-if="filteredLinks.length === 0" class="flex flex-1 items-center justify-center">
                    <div class="text-center">
                        <Search class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No matching links</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Try adjusting your search or filters.
                        </p>
                        <Button variant="secondary" class="mt-4 cursor-pointer" @click="searchQuery = ''; categoryFilter = 'all'">
                            Clear filters
                        </Button>
                    </div>
                </div>

                <Card v-else>
                    <CardContent class="p-0">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="table-layout: fixed">
                        <thead>
                            <tr class="border-b bg-muted">
                                <th class="w-6 px-1 py-3"></th>
                                <th
                                    v-for="(col, colIndex) in visibleLinkColumns"
                                    :key="col.key"
                                    class="px-3 py-3 text-left font-medium relative select-none"
                                    :style="{ width: col.width + 'px' }"
                                    :class="{
                                        'text-right': col.key === 'actions',
                                        'bg-primary/10': dragOverColIndex === colIndex && dragColTable === 'links',
                                        'opacity-50': draggedColIndex === colIndex && dragColTable === 'links',
                                    }"
                                    @dragover="!col.fixed && onColDragOver(colIndex, $event)"
                                    @dragleave="onColDragLeave"
                                    @drop="!col.fixed && onColDrop('links', colIndex, $event)"
                                >
                                    <div class="flex items-center gap-1">
                                        <div
                                            v-if="!col.fixed"
                                            draggable="true"
                                            class="shrink-0 cursor-grab active:cursor-grabbing p-0.5 rounded hover:bg-muted"
                                            @dragstart="onColDragStart('links', colIndex, $event)"
                                            @dragend="onColDragEnd"
                                        >
                                            <GripHorizontal class="h-3 w-3 text-muted-foreground/50" />
                                        </div>
                                        <template v-if="col.key === 'checkbox'">
                                            <Checkbox
                                                :model-value="selectedLinkIds.size === filteredLinks.length && filteredLinks.length > 0"
                                                class="cursor-pointer"
                                                @update:model-value="toggleAllLinks"
                                            />
                                        </template>
                                        <span v-else-if="col.key !== 'actions'" class="truncate">{{ col.label }}</span>
                                        <span v-else class="ml-auto">{{ col.label }}</span>
                                    </div>
                                    <div
                                        v-if="!col.fixed"
                                        class="absolute -right-1 top-0 bottom-0 w-3 cursor-col-resize group"
                                        @mousedown.stop="startResize('links', colIndex, $event)"
                                    >
                                        <div class="absolute right-1 top-0 bottom-0 w-0.5 group-hover:bg-primary/50 group-active:bg-primary" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(link, index) in filteredLinks"
                                :key="link.id"
                                class="border-b transition-colors hover:bg-muted/50"
                                :class="{
                                    'border-t-2 border-t-primary': dragOverRowIndex === index && dragRowTable === 'links' && canDragRows,
                                    'opacity-50': draggedRowIndex === index && dragRowTable === 'links' && canDragRows,
                                }"
                                @dragover="canDragRows && onRowDragOver(index, $event)"
                                @dragleave="onRowDragLeave"
                                @drop="canDragRows && onRowDrop('links', index, $event)"
                            >
                                <td class="px-1 py-2 align-top">
                                    <div
                                        :draggable="canDragRows"
                                        @dragstart="canDragRows && onRowDragStart('links', index, $event)"
                                        @dragend="onRowDragEnd"
                                        :class="canDragRows ? 'cursor-grab active:cursor-grabbing' : 'cursor-default opacity-30'"
                                    >
                                        <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                    </div>
                                </td>
                                <td
                                    v-for="col in visibleLinkColumns"
                                    :key="col.key"
                                    class="px-3 py-2"
                                >
                                    <template v-if="col.key === 'checkbox'">
                                        <Checkbox
                                            :model-value="selectedLinkIds.has(link.id)"
                                            class="cursor-pointer"
                                            @update:model-value="toggleLinkSelection(link.id)"
                                        />
                                    </template>
                                    <template v-else-if="col.key === 'category'">
                                        <Badge v-if="link.category" variant="secondary">{{ link.category }}</Badge>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'description'">
                                        <span class="font-medium">{{ link.description }}</span>
                                    </template>
                                    <template v-else-if="col.key === 'url'">
                                        <div class="flex items-center gap-1">
                                            <a
                                                :href="link.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="truncate max-w-[240px] text-primary underline-offset-4 hover:underline"
                                                :title="link.url"
                                            >
                                                {{ link.url }}
                                            </a>
                                            <a
                                                :href="link.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                title="Open in new tab"
                                            >
                                                <ExternalLink class="h-3.5 w-3.5" />
                                            </a>
                                            <button
                                                class="shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                title="Copy URL"
                                                @click="copyToClipboard(link.url, `link-${link.id}`)"
                                            >
                                                <Check v-if="copiedField === `link-${link.id}`" class="h-3.5 w-3.5 text-green-500" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else-if="col.key === 'comment'">
                                        <span v-if="link.comment" class="line-clamp-1 text-muted-foreground" :title="link.comment">{{ link.comment }}</span>
                                        <span v-else class="text-muted-foreground">&mdash;</span>
                                    </template>
                                    <template v-else-if="col.key === 'actions'">
                                        <div class="flex justify-end gap-1">
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer" @click="openEditLinkDialog(link)">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                            <RestrictedAction>
                                                <Button variant="ghost" size="icon-sm" class="cursor-pointer text-destructive hover:text-destructive" @click="confirmDeleteLink(link)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </RestrictedAction>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Add/Edit User Dialog -->
            <Dialog v-model:open="showUserDialog">
                <DialogContent class="max-w-md max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ editingUser ? 'Edit Test User' : 'Add Test User' }}</DialogTitle>
                        <DialogDescription>
                            {{ editingUser ? 'Update test user credentials.' : 'Add a new test user account.' }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitUserForm">
                        <div class="space-y-2">
                            <Label for="user-name">Name</Label>
                            <Input id="user-name" v-model="userForm.name" placeholder="John Doe" />
                            <InputError :message="userForm.errors.name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="user-email">Email</Label>
                            <Input id="user-email" v-model="userForm.email" type="email" placeholder="john@example.com" />
                            <InputError :message="userForm.errors.email" />
                        </div>
                        <div class="space-y-2">
                            <Label for="user-password">Password</Label>
                            <Input id="user-password" v-model="userForm.password" placeholder="Optional password" />
                            <InputError :message="userForm.errors.password" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="user-role">Role</Label>
                                <Input id="user-role" v-model="userForm.role" placeholder="admin, user..." />
                                <InputError :message="userForm.errors.role" />
                            </div>
                            <div class="space-y-2">
                                <Label>Environment</Label>
                                <Select v-model="userForm.environment">
                                    <SelectTrigger class="cursor-pointer bg-background/60">
                                        <SelectValue placeholder="Select environment" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="develop" class="cursor-pointer">Develop</SelectItem>
                                        <SelectItem value="staging" class="cursor-pointer">Staging</SelectItem>
                                        <SelectItem value="production" class="cursor-pointer">Production</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="userForm.errors.environment" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="user-valid"
                                    :model-value="userForm.is_valid"
                                    class="cursor-pointer"
                                    @update:model-value="(v: boolean) => userForm.is_valid = v"
                                />
                                <Label for="user-valid" class="cursor-pointer text-sm">Active account</Label>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Tags</Label>
                            <div class="flex gap-2">
                                <Input
                                    v-model="tagInput"
                                    placeholder="Add tag..."
                                    class="flex-1"
                                    @keydown.enter.prevent="addTag"
                                />
                                <Button type="button" variant="outline" size="sm" class="cursor-pointer" @click="addTag">Add</Button>
                            </div>
                            <div v-if="userForm.tags.length > 0" class="flex flex-wrap gap-1">
                                <Badge v-for="(tag, i) in userForm.tags" :key="tag" variant="secondary" class="gap-1">
                                    {{ tag }}
                                    <button type="button" class="cursor-pointer hover:text-destructive" @click="removeTag(i)">
                                        <X class="h-3 w-3" />
                                    </button>
                                </Badge>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="user-description">Description</Label>
                            <Textarea id="user-description" v-model="userForm.description" placeholder="Optional notes..." rows="2" />
                            <InputError :message="userForm.errors.description" />
                        </div>
                        <DialogFooter class="flex gap-4 sm:justify-end">
                            <Button type="button" variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showUserDialog = false">
                                Cancel
                            </Button>
                            <Button type="submit" variant="cta" class="flex-1 cursor-pointer sm:flex-none" :disabled="userForm.processing">
                                {{ editingUser ? 'Update' : 'Add User' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Add/Edit Payment Dialog -->
            <Dialog v-model:open="showPaymentDialog">
                <DialogContent class="max-w-md max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ editingPayment ? 'Edit Payment Method' : 'Add Payment Method' }}</DialogTitle>
                        <DialogDescription>
                            {{ editingPayment ? 'Update payment method details.' : 'Add a new test payment method.' }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitPaymentForm">
                        <div class="space-y-2">
                            <Label for="payment-name">Name</Label>
                            <Input id="payment-name" v-model="paymentForm.name" placeholder="Test Visa Card" />
                            <InputError :message="paymentForm.errors.name" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Type</Label>
                                <Select v-model="paymentForm.type">
                                    <SelectTrigger class="cursor-pointer bg-background/60">
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="card" class="cursor-pointer">Card</SelectItem>
                                        <SelectItem value="crypto" class="cursor-pointer">Crypto</SelectItem>
                                        <SelectItem value="bank" class="cursor-pointer">Bank</SelectItem>
                                        <SelectItem value="paypal" class="cursor-pointer">PayPal</SelectItem>
                                        <SelectItem value="other" class="cursor-pointer">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="paymentForm.errors.type" />
                            </div>
                            <div class="space-y-2">
                                <Label for="payment-system">System</Label>
                                <Input id="payment-system" v-model="paymentForm.system" placeholder="Stripe, PayPal..." />
                                <InputError :message="paymentForm.errors.system" />
                            </div>
                        </div>

                        <!-- Dynamic credential fields -->
                        <div class="space-y-3">
                            <Label>Credentials</Label>
                            <div v-for="field in credentialFields" :key="field.key" class="space-y-1">
                                <Label :for="`cred-${field.key}`" class="text-xs text-muted-foreground">{{ field.label }}</Label>
                                <Input
                                    :id="`cred-${field.key}`"
                                    :model-value="paymentForm.credentials[field.key] || ''"
                                    :placeholder="field.placeholder"
                                    @update:model-value="(v: string) => paymentForm.credentials[field.key] = v"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Environment</Label>
                            <Select v-model="paymentForm.environment">
                                <SelectTrigger class="cursor-pointer bg-background/60">
                                    <SelectValue placeholder="Select environment" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="develop" class="cursor-pointer">Develop</SelectItem>
                                    <SelectItem value="staging" class="cursor-pointer">Staging</SelectItem>
                                    <SelectItem value="production" class="cursor-pointer">Production</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="paymentForm.errors.environment" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="payment-valid"
                                    :model-value="paymentForm.is_valid"
                                    class="cursor-pointer"
                                    @update:model-value="(v: boolean) => paymentForm.is_valid = v"
                                />
                                <Label for="payment-valid" class="cursor-pointer text-sm">Valid payment method</Label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Tags</Label>
                            <div class="flex gap-2">
                                <Input
                                    v-model="paymentTagInput"
                                    placeholder="Add tag..."
                                    class="flex-1"
                                    @keydown.enter.prevent="addPaymentTag"
                                />
                                <Button type="button" variant="outline" size="sm" class="cursor-pointer" @click="addPaymentTag">Add</Button>
                            </div>
                            <div v-if="paymentForm.tags.length > 0" class="flex flex-wrap gap-1">
                                <Badge v-for="(tag, i) in paymentForm.tags" :key="tag" variant="secondary" class="gap-1">
                                    {{ tag }}
                                    <button type="button" class="cursor-pointer hover:text-destructive" @click="removePaymentTag(i)">
                                        <X class="h-3 w-3" />
                                    </button>
                                </Badge>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="payment-description">Description</Label>
                            <Textarea id="payment-description" v-model="paymentForm.description" placeholder="Optional notes..." rows="2" />
                            <InputError :message="paymentForm.errors.description" />
                        </div>
                        <DialogFooter class="flex gap-4 sm:justify-end">
                            <Button type="button" variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showPaymentDialog = false">
                                Cancel
                            </Button>
                            <Button type="submit" variant="cta" class="flex-1 cursor-pointer sm:flex-none" :disabled="paymentForm.processing">
                                {{ editingPayment ? 'Update' : 'Add Payment' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Delete User Confirmation -->
            <Dialog v-model:open="showDeleteUserConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Test User?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ userToDelete?.name }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showDeleteUserConfirm = false">No</Button>
                        <Button variant="destructive" class="flex-1 cursor-pointer sm:flex-none" @click="deleteUser">Yes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Payment Confirmation -->
            <Dialog v-model:open="showDeletePaymentConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Payment Method?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ paymentToDelete?.name }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showDeletePaymentConfirm = false">No</Button>
                        <Button variant="destructive" class="flex-1 cursor-pointer sm:flex-none" @click="deletePayment">Yes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Add/Edit Command Dialog -->
            <Dialog v-model:open="showCommandDialog">
                <DialogContent class="max-w-md max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ editingCommand ? 'Edit Command' : 'Add Command' }}</DialogTitle>
                        <DialogDescription>
                            {{ editingCommand ? 'Update command details.' : 'Add a useful command for your team.' }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitCommandForm">
                        <div class="space-y-2">
                            <Label for="cmd-category">Category</Label>
                            <Input id="cmd-category" v-model="commandForm.category" placeholder="deploy, database, testing..." list="cmd-category-suggestions" />
                            <datalist id="cmd-category-suggestions">
                                <option v-for="cat in allCommandCategories" :key="cat" :value="cat" />
                            </datalist>
                            <InputError :message="commandForm.errors.category" />
                        </div>
                        <div class="space-y-2">
                            <Label for="cmd-description">Description</Label>
                            <Input id="cmd-description" v-model="commandForm.description" placeholder="Run database migrations" />
                            <InputError :message="commandForm.errors.description" />
                        </div>
                        <div class="space-y-2">
                            <Label for="cmd-command">Command</Label>
                            <Textarea id="cmd-command" v-model="commandForm.command" placeholder="php artisan migrate" rows="3" class="font-mono text-sm" />
                            <InputError :message="commandForm.errors.command" />
                        </div>
                        <div class="space-y-2">
                            <Label for="cmd-comment">Comment</Label>
                            <Textarea id="cmd-comment" v-model="commandForm.comment" placeholder="Optional notes..." rows="2" />
                            <InputError :message="commandForm.errors.comment" />
                        </div>
                        <DialogFooter class="flex gap-4 sm:justify-end">
                            <Button type="button" variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showCommandDialog = false">
                                Cancel
                            </Button>
                            <Button type="submit" variant="cta" class="flex-1 cursor-pointer sm:flex-none" :disabled="commandForm.processing">
                                {{ editingCommand ? 'Update' : 'Add Command' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Add/Edit Link Dialog -->
            <Dialog v-model:open="showLinkDialog">
                <DialogContent class="max-w-md max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ editingLink ? 'Edit Link' : 'Add Link' }}</DialogTitle>
                        <DialogDescription>
                            {{ editingLink ? 'Update link details.' : 'Add a reference URL for your team.' }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitLinkForm">
                        <div class="space-y-2">
                            <Label for="link-category">Category</Label>
                            <Input id="link-category" v-model="linkForm.category" placeholder="documentation, monitoring, admin..." list="link-category-suggestions" />
                            <datalist id="link-category-suggestions">
                                <option v-for="cat in allLinkCategories" :key="cat" :value="cat" />
                            </datalist>
                            <InputError :message="linkForm.errors.category" />
                        </div>
                        <div class="space-y-2">
                            <Label for="link-description">Description</Label>
                            <Input id="link-description" v-model="linkForm.description" placeholder="API Documentation" />
                            <InputError :message="linkForm.errors.description" />
                        </div>
                        <div class="space-y-2">
                            <Label for="link-url">URL</Label>
                            <Input id="link-url" v-model="linkForm.url" type="url" placeholder="https://example.com/docs" />
                            <InputError :message="linkForm.errors.url" />
                        </div>
                        <div class="space-y-2">
                            <Label for="link-comment">Comment</Label>
                            <Textarea id="link-comment" v-model="linkForm.comment" placeholder="Optional notes..." rows="2" />
                            <InputError :message="linkForm.errors.comment" />
                        </div>
                        <DialogFooter class="flex gap-4 sm:justify-end">
                            <Button type="button" variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showLinkDialog = false">
                                Cancel
                            </Button>
                            <Button type="submit" variant="cta" class="flex-1 cursor-pointer sm:flex-none" :disabled="linkForm.processing">
                                {{ editingLink ? 'Update' : 'Add Link' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Delete Command Confirmation -->
            <Dialog v-model:open="showDeleteCommandConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Command?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ commandToDelete?.description }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showDeleteCommandConfirm = false">No</Button>
                        <Button variant="destructive" class="flex-1 cursor-pointer sm:flex-none" @click="deleteCommand">Yes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Link Confirmation -->
            <Dialog v-model:open="showDeleteLinkConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Link?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ linkToDelete?.description }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showDeleteLinkConfirm = false">No</Button>
                        <Button variant="destructive" class="flex-1 cursor-pointer sm:flex-none" @click="deleteLink">Yes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Bulk Delete Confirmation -->
            <Dialog v-model:open="showBulkDeleteConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete {{ bulkDeleteConfig[bulkDeleteTarget].label }}?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete
                            {{ bulkDeleteConfig[bulkDeleteTarget].ids.value.size }}
                            item(s)?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showBulkDeleteConfirm = false">No</Button>
                        <Button variant="destructive" class="flex-1 cursor-pointer sm:flex-none" @click="executeBulkDelete">Yes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <!-- Import Dialog -->
            <Dialog v-model:open="showImportDialog">
                <DialogContent class="max-w-2xl max-h-[80vh] flex flex-col" style="overflow: hidden !important; max-width: min(42rem, calc(100vw - 2rem)) !important;">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5 text-primary" />
                            Import {{ activeTab === 'users' ? 'Users' : activeTab === 'payments' ? 'Payments' : activeTab === 'commands' ? 'Commands' : 'Links' }}
                        </DialogTitle>
                        <DialogDescription>
                            Upload a CSV or Excel file. Columns will be automatically mapped to fields.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4 overflow-y-auto min-h-0 flex-1">
                        <div class="space-y-2">
                            <Label>File</Label>
                            <div class="flex items-center gap-3">
                                <input
                                    ref="importFileInput"
                                    type="file"
                                    accept=".csv,.xlsx,.xls"
                                    class="hidden"
                                    @change="onImportFileChange"
                                />
                                <Button variant="outline" size="sm" class="gap-2 cursor-pointer" @click="($refs.importFileInput as HTMLInputElement).click()">
                                    <Upload class="h-4 w-4" />
                                    Choose File
                                </Button>
                                <span class="text-sm text-muted-foreground truncate">{{ importFile?.name || 'No file selected' }}</span>
                            </div>
                        </div>

                        <div v-if="importHeaders.length > 0" class="space-y-4">
                            <div class="rounded-lg border p-4 bg-muted/30 space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>Column Mapping</Label>
                                    <span class="text-xs text-muted-foreground">
                                        {{ matchedFieldCount }} of {{ importHeaders.length }} columns matched
                                    </span>
                                </div>
                                <div class="grid gap-1.5">
                                    <div
                                        v-for="mapping in importFieldMapping"
                                        :key="mapping.header"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                            :class="mapping.matchedField
                                                ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20'
                                                : 'bg-muted text-muted-foreground ring-border'"
                                        >
                                            {{ mapping.header }}
                                        </span>
                                        <span v-if="mapping.matchedField" class="text-muted-foreground">&rarr;</span>
                                        <span v-if="mapping.matchedField" class="text-sm font-medium">{{ mapping.matchedField }}</span>
                                        <span v-else class="text-xs text-muted-foreground italic">ignored</span>
                                    </div>
                                </div>
                            </div>

                            <p class="text-sm text-muted-foreground">
                                Found <strong>{{ importRows.length }}</strong> row(s) to import
                            </p>
                        </div>
                    </div>
                    <DialogFooter class="flex gap-2 sm:justify-end">
                        <Button variant="outline" @click="showImportDialog = false">Cancel</Button>
                        <Button
                            @click="submitImport"
                            :disabled="importRows.length === 0 || isImporting || matchedFieldCount === 0"
                            class="gap-2"
                        >
                            <Download class="h-4 w-4" />
                            {{ isImporting ? 'Importing...' : `Import ${importRows.length} row(s)` }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
