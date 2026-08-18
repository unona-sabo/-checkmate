<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as usersIndex } from '@/routes/admin/users';
import { type BreadcrumbItem } from '@/types';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    blocked_at: string | null;
    created_at: string;
}

defineProps<{
    users: AdminUser[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: usersIndex().url,
    },
];

const deleteDialogOpen = ref(false);
const selectedUser = ref<AdminUser | null>(null);

function toggleBlock(user: AdminUser) {
    const action = user.blocked_at
        ? UserController.unblock(user.id)
        : UserController.block(user.id);

    router.post(action.url, {}, { preserveScroll: true });
}

function confirmDelete(user: AdminUser) {
    selectedUser.value = user;
    deleteDialogOpen.value = true;
}

function executeDelete() {
    if (!selectedUser.value) return;
    router.delete(UserController.destroy(selectedUser.value.id).url, {
        preserveScroll: true,
        onFinish: () => {
            deleteDialogOpen.value = false;
            selectedUser.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <h1 class="sr-only">Users</h1>

        <SettingsLayout wide>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Users"
                    description="View, block, or delete registered accounts"
                />

                <div class="rounded-md border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-2 text-left font-medium">
                                    Name
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Email
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Status
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Joined
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-2">
                                    {{ user.name }}
                                    <Badge
                                        v-if="user.is_admin"
                                        variant="secondary"
                                        class="ml-2"
                                    >
                                        Admin
                                    </Badge>
                                </td>
                                <td class="px-4 py-2">{{ user.email }}</td>
                                <td class="px-4 py-2">
                                    <Badge
                                        :variant="
                                            user.blocked_at
                                                ? 'destructive'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            user.blocked_at
                                                ? 'Blocked'
                                                : 'Active'
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-2">
                                    {{ user.created_at }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="cursor-pointer"
                                            @click="toggleBlock(user)"
                                        >
                                            {{
                                                user.blocked_at
                                                    ? 'Unblock'
                                                    : 'Block'
                                            }}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="cursor-pointer text-destructive"
                                            @click="confirmDelete(user)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </SettingsLayout>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete user</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete
                        <strong>{{ selectedUser?.name }}</strong
                        >? This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="cursor-pointer"
                        @click="deleteDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        class="cursor-pointer"
                        @click="executeDelete"
                    >
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
