<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestUser, type TestPaymentMethod } from '@/types';
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
    Users,
    CreditCard,
    GripVertical,
    GripHorizontal,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useCanEdit } from '@/composables/useCanEdit';

const { canEdit } = useCanEdit();

const props = defineProps<{
    project: Project;
    testUsers: TestUser[];
    testPaymentMethods: TestPaymentMethod[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Data', href: `/projects/${props.project.id}/test-data` },
];

// Tab state
const activeTab = ref<'users' | 'payments'>('users');

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

const userColumns = ref<ColumnDef[]>([...defaultUserColumns.map(c => ({ ...c }))]);
const paymentColumns = ref<ColumnDef[]>([...defaultPaymentColumns.map(c => ({ ...c }))]);

// localStorage persistence for column config
const userColStorageKey = computed(() => `test-data-users-columns-${props.project.id}`);
const paymentColStorageKey = computed(() => `test-data-payments-columns-${props.project.id}`);

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
};

const saveColumnConfig = () => {
    localStorage.setItem(userColStorageKey.value, JSON.stringify(userColumns.value.map(c => ({ key: c.key, width: c.width }))));
    localStorage.setItem(paymentColStorageKey.value, JSON.stringify(paymentColumns.value.map(c => ({ key: c.key, width: c.width }))));
};

onMounted(() => {
    loadColumnConfig();
});

// ===== Local mutable copies of row data =====
const localUsers = ref<TestUser[]>([...props.testUsers]);
const localPayments = ref<TestPaymentMethod[]>([...props.testPaymentMethods]);

watch(() => props.testUsers, (newVal) => {
    localUsers.value = [...newVal];
}, { deep: true });

watch(() => props.testPaymentMethods, (newVal) => {
    localPayments.value = [...newVal];
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

// ===== canDragRows =====
const isFiltered = computed(() =>
    searchQuery.value.trim() !== '' ||
    validityFilter.value !== 'all' ||
    environmentFilter.value !== 'all' ||
    roleFilter.value !== 'all' ||
    typeFilter.value !== 'all'
);

const canDragRows = computed(() => canEdit.value && !isFiltered.value);

// Bulk selection
const selectedUserIds = ref<Set<number>>(new Set());
const selectedPaymentIds = ref<Set<number>>(new Set());

watch(activeTab, () => {
    selectedUserIds.value = new Set();
    selectedPaymentIds.value = new Set();
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

// ===== Row Drag and Drop =====
const draggedRowIndex = ref<number | null>(null);
const dragOverRowIndex = ref<number | null>(null);
const dragRowTable = ref<'users' | 'payments' | null>(null);

const onRowDragStart = (table: 'users' | 'payments', index: number, event: DragEvent) => {
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

const onRowDrop = (table: 'users' | 'payments', index: number, event: DragEvent) => {
    event.preventDefault();
    if (draggedRowIndex.value === null || draggedRowIndex.value === index || dragRowTable.value !== table) {
        draggedRowIndex.value = null;
        dragOverRowIndex.value = null;
        dragRowTable.value = null;
        return;
    }

    if (table === 'users') {
        const dragged = localUsers.value[draggedRowIndex.value];
        localUsers.value.splice(draggedRowIndex.value, 1);
        localUsers.value.splice(index, 0, dragged);
        router.put(`/projects/${props.project.id}/test-data/users-reorder`, {
            ids: localUsers.value.map(u => u.id),
        }, { preserveScroll: true, preserveState: true });
    } else {
        const dragged = localPayments.value[draggedRowIndex.value];
        localPayments.value.splice(draggedRowIndex.value, 1);
        localPayments.value.splice(index, 0, dragged);
        router.put(`/projects/${props.project.id}/test-data/payments-reorder`, {
            ids: localPayments.value.map(p => p.id),
        }, { preserveScroll: true, preserveState: true });
    }

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
const dragColTable = ref<'users' | 'payments' | null>(null);

const onColDragStart = (table: 'users' | 'payments', index: number, event: DragEvent) => {
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

const onColDrop = (table: 'users' | 'payments', index: number, event: DragEvent) => {
    event.preventDefault();
    if (draggedColIndex.value === null || draggedColIndex.value === index || dragColTable.value !== table) {
        draggedColIndex.value = null;
        dragOverColIndex.value = null;
        dragColTable.value = null;
        return;
    }

    const cols = table === 'users' ? userColumns : paymentColumns;
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
const resizingCol = ref<{ table: 'users' | 'payments'; index: number } | null>(null);
const resizeStartX = ref(0);
const resizeStartWidth = ref(0);

const startResize = (table: 'users' | 'payments', index: number, event: MouseEvent) => {
    resizingCol.value = { table, index };
    resizeStartX.value = event.clientX;
    const cols = table === 'users' ? userColumns : paymentColumns;
    resizeStartWidth.value = cols.value[index].width || 150;
    document.addEventListener('mousemove', onResize);
    document.addEventListener('mouseup', stopResize);
};

const onResize = (event: MouseEvent) => {
    if (!resizingCol.value) return;
    const diff = event.clientX - resizeStartX.value;
    const newWidth = Math.max(50, resizeStartWidth.value + diff);
    const cols = resizingCol.value.table === 'users' ? userColumns : paymentColumns;
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

// ===== Bulk Delete =====
const showBulkDeleteConfirm = ref(false);
const bulkDeleteTarget = ref<'users' | 'payments'>('users');

const openBulkDelete = (target: 'users' | 'payments') => {
    bulkDeleteTarget.value = target;
    showBulkDeleteConfirm.value = true;
};

const executeBulkDelete = () => {
    if (bulkDeleteTarget.value === 'users') {
        router.delete(`/projects/${props.project.id}/test-data/users-bulk`, {
            data: { ids: [...selectedUserIds.value] },
            onSuccess: () => {
                showBulkDeleteConfirm.value = false;
                selectedUserIds.value = new Set();
            },
        });
    } else {
        router.delete(`/projects/${props.project.id}/test-data/payments-bulk`, {
            data: { ids: [...selectedPaymentIds.value] },
            onSuccess: () => {
                showBulkDeleteConfirm.value = false;
                selectedPaymentIds.value = new Set();
            },
        });
    }
};

// ===== CSV Export =====
const exportCsv = () => {
    let csv = '';
    if (activeTab.value === 'users') {
        csv = 'Name,Email,Password,Role,Environment,Valid,Tags,Description\n';
        filteredUsers.value.forEach(u => {
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
    } else {
        csv = 'Name,Type,System,Credentials,Environment,Valid,Tags,Description\n';
        filteredPayments.value.forEach(p => {
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
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                    <Database class="mt-1 h-6 w-6 shrink-0 text-primary" />
                    Test Data
                </h1>
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
            </div>

            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-3">
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

                <!-- Validity filter -->
                <Select v-model="validityFilter">
                    <SelectTrigger class="w-32 cursor-pointer">
                        <SelectValue placeholder="Validity" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All</SelectItem>
                        <SelectItem value="valid" class="cursor-pointer">Valid</SelectItem>
                        <SelectItem value="invalid" class="cursor-pointer">Invalid</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Environment filter -->
                <Select v-model="environmentFilter">
                    <SelectTrigger class="w-36 cursor-pointer">
                        <SelectValue placeholder="Environment" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" class="cursor-pointer">All Envs</SelectItem>
                        <SelectItem value="develop" class="cursor-pointer">Develop</SelectItem>
                        <SelectItem value="staging" class="cursor-pointer">Staging</SelectItem>
                        <SelectItem value="production" class="cursor-pointer">Production</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Role filter (users tab) -->
                <Select v-if="activeTab === 'users' && uniqueRoles.length > 0" v-model="roleFilter">
                    <SelectTrigger class="w-36 cursor-pointer">
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
                    <SelectTrigger class="w-36 cursor-pointer">
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
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-2 cursor-pointer"
                        :disabled="(activeTab === 'users' ? filteredUsers : filteredPayments).length === 0"
                        @click="exportCsv"
                    >
                        <Download class="h-4 w-4" />
                        Export CSV
                    </Button>

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
                    </RestrictedAction>

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
                            v-else
                            variant="cta"
                            class="gap-2 cursor-pointer"
                            @click="openAddPaymentDialog"
                        >
                            <Plus class="h-4 w-4" />
                            Add Payment
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
                                    v-for="(col, colIndex) in userColumns"
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
                                    v-for="col in userColumns"
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
                                    v-for="(col, colIndex) in paymentColumns"
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
                                    v-for="col in paymentColumns"
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
                                    <SelectTrigger class="cursor-pointer">
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
                                    <SelectTrigger class="cursor-pointer">
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
                                <SelectTrigger class="cursor-pointer">
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

            <!-- Bulk Delete Confirmation -->
            <Dialog v-model:open="showBulkDeleteConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete {{ bulkDeleteTarget === 'users' ? 'Test Users' : 'Payment Methods' }}?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete
                            {{ bulkDeleteTarget === 'users' ? selectedUserIds.size : selectedPaymentIds.size }}
                            {{ bulkDeleteTarget === 'users' ? 'test user(s)' : 'payment method(s)' }}?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" class="flex-1 cursor-pointer sm:flex-none" @click="showBulkDeleteConfirm = false">No</Button>
                        <Button variant="destructive" class="flex-1 cursor-pointer sm:flex-none" @click="executeBulkDelete">Yes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
