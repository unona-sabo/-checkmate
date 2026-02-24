<script setup lang="ts">
import { ref, computed } from 'vue';
import { type ProjectFeature } from '@/types/checkmate';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { Search, X, Plus, Tag } from 'lucide-vue-next';
import axios from 'axios';

type FeatureSummary = Pick<ProjectFeature, 'id' | 'name' | 'module' | 'priority'>;

const props = defineProps<{
    features: FeatureSummary[];
    modelValue: number[];
    projectId: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const search = ref('');
const showCreateDialog = ref(false);
const createError = ref('');
const creating = ref(false);

const newFeature = ref({
    name: '',
    module: '',
    priority: 'medium' as string,
});

const localFeatures = ref<FeatureSummary[]>([...props.features]);

const filteredFeatures = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return localFeatures.value;
    return localFeatures.value.filter(
        f => f.name.toLowerCase().includes(q) || f.module?.some(m => m.toLowerCase().includes(q)),
    );
});

const selectedFeatures = computed(() => {
    return localFeatures.value.filter(f => props.modelValue.includes(f.id));
});

const allSelected = computed(() => {
    return localFeatures.value.length > 0 && localFeatures.value.every(f => props.modelValue.includes(f.id));
});

const toggleAll = () => {
    if (allSelected.value) {
        emit('update:modelValue', []);
    } else {
        emit('update:modelValue', localFeatures.value.map(f => f.id));
    }
};

const toggle = (id: number) => {
    const current = [...props.modelValue];
    const index = current.indexOf(id);
    if (index === -1) {
        current.push(id);
    } else {
        current.splice(index, 1);
    }
    emit('update:modelValue', current);
};

const remove = (id: number) => {
    emit('update:modelValue', props.modelValue.filter(v => v !== id));
};

const priorityColor = (priority: string) => {
    switch (priority) {
        case 'critical': return 'text-red-700 bg-red-100';
        case 'high': return 'text-orange-700 bg-orange-100';
        case 'medium': return 'text-blue-700 bg-blue-100';
        case 'low': return 'text-gray-700 bg-gray-100';
        default: return '';
    }
};

const createFeature = async () => {
    createError.value = '';
    if (!newFeature.value.name.trim()) {
        createError.value = 'Name is required.';
        return;
    }

    creating.value = true;
    try {
        const response = await axios.post(`/projects/${props.projectId}/features`, {
            name: newFeature.value.name,
            module: newFeature.value.module ? [newFeature.value.module] : null,
            priority: newFeature.value.priority,
        });

        const created = response.data as FeatureSummary;
        localFeatures.value.push(created);
        emit('update:modelValue', [...props.modelValue, created.id]);

        newFeature.value = { name: '', module: '', priority: 'medium' };
        showCreateDialog.value = false;
    } catch (err: unknown) {
        if (axios.isAxiosError(err) && err.response?.data?.errors?.name) {
            createError.value = err.response.data.errors.name[0];
        } else {
            createError.value = 'Failed to create feature.';
        }
    } finally {
        creating.value = false;
    }
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <Label>
                <Tag class="mr-1 inline h-4 w-4" />
                Features
            </Label>
            <Dialog v-model:open="showCreateDialog">
                <DialogTrigger as-child>
                    <Button type="button" variant="outline" size="sm" class="gap-1">
                        <Plus class="h-3 w-3" />
                        Create Feature
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Create Feature</DialogTitle>
                        <DialogDescription>
                            Quickly add a new project feature.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-3">
                        <div class="space-y-1">
                            <Label for="new-feature-name">Name *</Label>
                            <Input id="new-feature-name" v-model="newFeature.name" placeholder="e.g., User Authentication" />
                            <InputError :message="createError" />
                        </div>
                        <div class="space-y-1">
                            <Label for="new-feature-module">Module</Label>
                            <Input id="new-feature-module" v-model="newFeature.module" placeholder="e.g., Auth, Dashboard" />
                        </div>
                        <div class="space-y-1">
                            <Label>Priority</Label>
                            <Select v-model="newFeature.priority">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="critical">Critical</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="low">Low</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="showCreateDialog = false">Cancel</Button>
                        <Button type="button" @click="createFeature" :disabled="creating">Create</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <!-- Selected features as chips -->
        <div v-if="selectedFeatures.length" class="flex flex-wrap gap-1">
            <Badge
                v-for="feature in selectedFeatures"
                :key="feature.id"
                variant="secondary"
                class="gap-1 pr-1"
            >
                {{ feature.name }}
                <button
                    type="button"
                    class="ml-0.5 rounded-full p-0.5 hover:bg-muted cursor-pointer"
                    @click="remove(feature.id)"
                >
                    <X class="h-3 w-3" />
                </button>
            </Badge>
        </div>

        <!-- Search + feature list -->
        <div v-if="localFeatures.length" class="rounded-lg border">
            <div class="relative p-2">
                <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="search"
                    placeholder="Search features..."
                    class="h-8 pl-8 text-sm"
                />
            </div>
            <label class="flex items-center gap-2 border-t px-3 py-1.5 cursor-pointer hover:bg-muted/50">
                <Checkbox :model-value="allSelected" @update:model-value="toggleAll" />
                <span class="text-sm text-muted-foreground">{{ allSelected ? 'Deselect All' : 'Select All' }}</span>
            </label>
            <div class="max-h-48 overflow-y-auto border-t px-2 py-1">
                <template v-if="filteredFeatures.length">
                    <label
                        v-for="feature in filteredFeatures"
                        :key="feature.id"
                        class="flex items-center gap-2 rounded px-1 py-1.5 cursor-pointer hover:bg-muted/50"
                    >
                        <Checkbox
                            :model-value="modelValue.includes(feature.id)"
                            @update:model-value="toggle(feature.id)"
                        />
                        <span class="flex-1 text-sm">{{ feature.name }}</span>
                        <Badge v-for="mod in (feature.module || [])" :key="mod" variant="outline" class="text-xs">
                            {{ mod }}
                        </Badge>
                        <span
                            class="rounded px-1.5 py-0.5 text-xs font-medium"
                            :class="priorityColor(feature.priority)"
                        >
                            {{ feature.priority }}
                        </span>
                    </label>
                </template>
                <div v-else class="py-3 text-center text-sm text-muted-foreground">
                    No features matching "{{ search }}"
                </div>
            </div>
        </div>

        <p v-else class="text-xs text-muted-foreground">
            No features defined yet. Create one to start linking.
        </p>
    </div>
</template>
