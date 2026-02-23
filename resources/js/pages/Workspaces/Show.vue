<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ArrowRightLeft, Trash2, UserPlus } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
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
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, Workspace, WorkspaceMember } from '@/types';

type Props = {
    workspace: Workspace;
    members: WorkspaceMember[];
    roles: string[];
};

const props = defineProps<Props>();
const page = usePage<AppPageProps>();

const currentRole = computed(() => page.props.currentWorkspace?.role);
const canManage = computed(() => currentRole.value === 'owner' || currentRole.value === 'admin');
const isOwner = computed(() => currentRole.value === 'owner');

const breadcrumbs = [{ title: 'Workspace Settings', href: '/workspaces/settings' }];

// Rename form
const renameForm = useForm({ name: props.workspace.name });

function updateWorkspace() {
    renameForm.put('/workspaces/settings', {
        preserveScroll: true,
    });
}

// Add member form
const addMemberForm = useForm({
    email: '',
    role: 'member',
});

function addMember() {
    addMemberForm.post('/workspaces/members', {
        preserveScroll: true,
        onSuccess: () => {
            addMemberForm.reset();
        },
    });
}

// Role update
function updateRole(memberId: number, role: string) {
    router.put(`/workspaces/members/${memberId}`, { role }, {
        preserveScroll: true,
    });
}

// Remove member
const removingMemberId = ref<number | null>(null);

function confirmRemoveMember(memberId: number) {
    removingMemberId.value = memberId;
}

function removeMember() {
    if (removingMemberId.value === null) return;
    router.delete(`/workspaces/members/${removingMemberId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            removingMemberId.value = null;
        },
    });
}

// Transfer ownership
const transferableMembers = computed(() =>
    props.members.filter((m) => m.id !== page.props.auth.user.id),
);
const transferOwnerId = ref<number | null>(null);
const showTransferDialog = ref(false);

function confirmTransfer() {
    if (!transferOwnerId.value) return;
    showTransferDialog.value = true;
}

function transferOwnership() {
    if (!transferOwnerId.value) return;
    router.put('/workspaces/transfer', { new_owner_id: transferOwnerId.value }, {
        preserveScroll: true,
        onSuccess: () => {
            showTransferDialog.value = false;
            transferOwnerId.value = null;
        },
    });
}

// Delete workspace
const showDeleteDialog = ref(false);

function deleteWorkspace() {
    router.delete('/workspaces/settings', {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
}

const roleColors: Record<string, string> = {
    owner: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    admin: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    member: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    viewer: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Workspace Settings" />

        <div class="mx-auto max-w-3xl space-y-8 p-6">
            <!-- General Settings -->
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="General"
                    description="Update your workspace name"
                />

                <form @submit.prevent="updateWorkspace" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="workspace-name">Workspace Name</Label>
                        <Input
                            id="workspace-name"
                            v-model="renameForm.name"
                            :disabled="!canManage"
                        />
                        <InputError :message="renameForm.errors.name" />
                    </div>
                    <Button v-if="canManage" :disabled="renameForm.processing" type="submit">
                        Save
                    </Button>
                </form>
            </div>

            <!-- Members -->
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Members"
                    description="Manage who has access to this workspace"
                />

                <!-- Add Member Form -->
                <form v-if="canManage" @submit.prevent="addMember" class="flex items-end gap-3">
                    <div class="flex-1 space-y-1">
                        <Label for="member-email">Email</Label>
                        <Input
                            id="member-email"
                            v-model="addMemberForm.email"
                            type="email"
                            placeholder="user@example.com"
                            :class="{ 'border-destructive': addMemberForm.errors.email }"
                        />
                        <InputError :message="addMemberForm.errors.email" />
                    </div>
                    <div class="w-32 space-y-1">
                        <Label for="member-role">Role</Label>
                        <Select v-model="addMemberForm.role">
                            <SelectTrigger id="member-role">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="admin">Admin</SelectItem>
                                <SelectItem value="member">Member</SelectItem>
                                <SelectItem value="viewer">Viewer</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button :disabled="addMemberForm.processing" type="submit">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Add
                    </Button>
                </form>

                <!-- Members List -->
                <div class="divide-y rounded-md border">
                    <div
                        v-for="member in members"
                        :key="member.id"
                        class="flex items-center justify-between px-4 py-3"
                    >
                        <div class="flex flex-col">
                            <span class="font-medium">{{ member.name }}</span>
                            <span class="text-sm text-muted-foreground">{{ member.email }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <template v-if="canManage && member.role !== 'owner'">
                                <Select
                                    :model-value="member.role"
                                    @update:model-value="(val: string) => updateRole(member.id, val)"
                                >
                                    <SelectTrigger class="w-28">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="member">Member</SelectItem>
                                        <SelectItem value="viewer">Viewer</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive cursor-pointer"
                                    @click="confirmRemoveMember(member.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </template>
                            <Badge v-else :class="roleColors[member.role]" variant="secondary">
                                {{ member.role }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Ownership -->
            <div v-if="isOwner && transferableMembers.length > 0" class="space-y-6">
                <Heading
                    variant="small"
                    title="Transfer Ownership"
                    description="Transfer this workspace to another member"
                />

                <div class="flex items-end gap-3">
                    <div class="flex-1 space-y-1">
                        <Label for="transfer-owner">New Owner</Label>
                        <Select
                            :model-value="transferOwnerId?.toString() ?? ''"
                            @update:model-value="(val: string) => transferOwnerId = Number(val)"
                        >
                            <SelectTrigger id="transfer-owner">
                                <SelectValue placeholder="Select a member" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="member in transferableMembers"
                                    :key="member.id"
                                    :value="member.id.toString()"
                                >
                                    {{ member.name }} ({{ member.email }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        variant="outline"
                        :disabled="!transferOwnerId"
                        class="cursor-pointer"
                        @click="confirmTransfer"
                    >
                        <ArrowRightLeft class="mr-2 h-4 w-4" />
                        Transfer
                    </Button>
                </div>
            </div>

            <!-- Danger Zone -->
            <div v-if="isOwner" class="space-y-6">
                <Heading
                    variant="small"
                    title="Danger Zone"
                    description="Permanently delete this workspace and all its data"
                />
                <Button variant="destructive" @click="showDeleteDialog = true" class="cursor-pointer">
                    Delete Workspace
                </Button>
            </div>
        </div>

        <!-- Remove Member Confirmation -->
        <Dialog :open="removingMemberId !== null" @update:open="removingMemberId = null">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove Member</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove this member from the workspace? They will lose access to all projects.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="removingMemberId = null">Cancel</Button>
                    <Button variant="destructive" @click="removeMember">Remove</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Transfer Ownership Confirmation -->
        <Dialog v-model:open="showTransferDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Transfer Ownership</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to transfer ownership of this workspace? You will be demoted to admin and will no longer be able to delete the workspace or transfer ownership.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showTransferDialog = false">Cancel</Button>
                    <Button @click="transferOwnership">Transfer Ownership</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Workspace Confirmation -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Workspace</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to permanently delete this workspace? All projects and data within it will be deleted. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteWorkspace">Delete Workspace</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
