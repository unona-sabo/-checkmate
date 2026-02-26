<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import BackupController from '@/actions/App/Http/Controllers/Settings/BackupController';
import Heading from '@/components/Heading.vue';
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
import { show } from '@/routes/backup';
import { type BreadcrumbItem } from '@/types';

interface Snapshot {
    name: string;
    size: number;
    date: string;
}

defineProps<{
    snapshots: Snapshot[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Backup settings',
        href: show().url,
    },
];

const downloading = ref(false);
const creatingSnapshot = ref(false);
const restoreDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const selectedSnapshot = ref<string | null>(null);

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function submitForm(url: string, method: string = 'POST') {
    const form = document.createElement('form');
    form.method = method;
    form.action = url;
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function downloadDatabase() {
    downloading.value = true;
    submitForm(BackupController.download().url);
    setTimeout(() => (downloading.value = false), 2000);
}

function createSnapshot() {
    creatingSnapshot.value = true;
    router.post(BackupController.snapshot().url, {}, {
        preserveScroll: true,
        onFinish: () => (creatingSnapshot.value = false),
    });
}

function downloadSnapshotFile(filename: string) {
    submitForm(BackupController.downloadSnapshot({ filename }).url);
}

function confirmRestore(filename: string) {
    selectedSnapshot.value = filename;
    restoreDialogOpen.value = true;
}

function executeRestore() {
    if (!selectedSnapshot.value) return;
    router.post(BackupController.restore({ filename: selectedSnapshot.value }).url, {}, {
        preserveScroll: true,
        onFinish: () => {
            restoreDialogOpen.value = false;
            selectedSnapshot.value = null;
        },
    });
}

function confirmDelete(filename: string) {
    selectedSnapshot.value = filename;
    deleteDialogOpen.value = true;
}

function executeDelete() {
    if (!selectedSnapshot.value) return;
    router.delete(
        BackupController.destroySnapshot({ filename: selectedSnapshot.value }).url,
        {
            preserveScroll: true,
            onFinish: () => {
                deleteDialogOpen.value = false;
                selectedSnapshot.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Backup settings" />

        <h1 class="sr-only">Backup Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Backup settings"
                    description="Download your database or manage snapshots"
                />

                <div class="space-y-4">
                    <div class="flex flex-wrap gap-3">
                        <Button
                            class="cursor-pointer"
                            :disabled="downloading"
                            @click="downloadDatabase"
                        >
                            {{ downloading ? 'Downloading...' : 'Download current database' }}
                        </Button>
                        <Button
                            variant="outline"
                            class="cursor-pointer"
                            :disabled="creatingSnapshot"
                            @click="createSnapshot"
                        >
                            {{ creatingSnapshot ? 'Creating...' : 'Create snapshot' }}
                        </Button>
                    </div>
                </div>

                <div v-if="snapshots.length > 0" class="space-y-3">
                    <h3 class="text-sm font-medium">Snapshots</h3>
                    <div class="border rounded-md">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50">
                                    <th class="px-4 py-2 text-left font-medium">Name</th>
                                    <th class="px-4 py-2 text-left font-medium">Size</th>
                                    <th class="px-4 py-2 text-left font-medium">Date</th>
                                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="snap in snapshots"
                                    :key="snap.name"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-2 font-mono text-xs">{{ snap.name }}</td>
                                    <td class="px-4 py-2">{{ formatBytes(snap.size) }}</td>
                                    <td class="px-4 py-2">{{ snap.date }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="cursor-pointer"
                                                @click="downloadSnapshotFile(snap.name)"
                                            >
                                                Download
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="cursor-pointer"
                                                @click="confirmRestore(snap.name)"
                                            >
                                                Restore
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="cursor-pointer text-destructive"
                                                @click="confirmDelete(snap.name)"
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

                <p v-else class="text-muted-foreground text-sm">
                    No snapshots yet. Create one to get started.
                </p>
            </div>
        </SettingsLayout>

        <!-- Restore confirmation dialog -->
        <Dialog v-model:open="restoreDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Restore snapshot</DialogTitle>
                    <DialogDescription>
                        This will overwrite the current database with
                        <strong>{{ selectedSnapshot }}</strong>. This action cannot be undone.
                        Consider creating a snapshot of the current database first.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="cursor-pointer"
                        @click="restoreDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        class="cursor-pointer"
                        @click="executeRestore"
                    >
                        Restore
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete snapshot</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete
                        <strong>{{ selectedSnapshot }}</strong>? This cannot be undone.
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
