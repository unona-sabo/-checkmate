<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AppPageProps } from '@/types';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';

const page = usePage<AppPageProps>();

const currentWorkspace = computed(() => page.props.currentWorkspace);
const workspaces = computed(() => page.props.workspaces);

const showCreateDialog = ref(false);
const createForm = useForm({ name: '' });
useClearErrorsOnInput(createForm);

watch(showCreateDialog, (open) => {
    if (!open) {
        createForm.reset();
        createForm.clearErrors();
    }
});

function switchWorkspace(workspaceId: number) {
    router.post('/workspaces/switch', { workspace_id: workspaceId }, {
        preserveState: false,
    });
}

function createWorkspace() {
    createForm.post('/workspaces', {
        preserveState: false,
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-sidebar-accent cursor-pointer">
                <Building2 class="h-4 w-4 shrink-0" />
                <span class="truncate font-medium">{{ currentWorkspace?.name ?? 'No Workspace' }}</span>
                <ChevronsUpDown class="ml-auto h-4 w-4 shrink-0 opacity-50" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-64" align="start">
            <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="ws in workspaces"
                :key="ws.id"
                class="cursor-pointer"
                @click="switchWorkspace(ws.id)"
            >
                <Check
                    class="mr-2 h-4 w-4"
                    :class="ws.id === currentWorkspace?.id ? 'opacity-100' : 'opacity-0'"
                />
                <span class="truncate">{{ ws.name }}</span>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem class="cursor-pointer" @click="createForm.reset(); createForm.clearErrors(); showCreateDialog = true">
                <Plus class="mr-2 h-4 w-4" />
                Create Workspace
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <Dialog v-model:open="showCreateDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Create Workspace</DialogTitle>
                <DialogDescription>Create a new workspace to organize projects and collaborate with your team.</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="createWorkspace">
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="workspace-name">Workspace Name</Label>
                        <Input
                            id="workspace-name"
                            v-model="createForm.name"
                            placeholder="My Workspace"
                            :class="{ 'border-destructive': createForm.errors.name }"
                        />
                        <p v-if="createForm.errors.name" class="text-sm text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showCreateDialog = false">Cancel</Button>
                    <Button type="submit" :disabled="createForm.processing">Create</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
