<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Paperclip, X, ImagePlus } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: File[];
    errors?: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [files: File[]];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isDragOver = ref(false);

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const addFiles = (incoming: File[]) => {
    emit('update:modelValue', [...props.modelValue, ...incoming]);
};

const removeFile = (index: number) => {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
};

const onFilesSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        addFiles(Array.from(target.files));
    }
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const extractImages = (items: DataTransferItemList | null): File[] => {
    if (!items) return [];
    const files: File[] = [];
    for (const item of Array.from(items)) {
        if (item.type.startsWith('image/')) {
            const blob = item.getAsFile();
            if (blob) {
                const ext = item.type.split('/')[1] || 'png';
                files.push(new File([blob], `screenshot-${Date.now()}.${ext}`, { type: item.type }));
            }
        }
    }
    return files;
};

const handlePaste = (event: ClipboardEvent) => {
    const files = extractImages(event.clipboardData?.items ?? null);
    if (files.length) addFiles(files);
};

const onDragOver = (event: DragEvent) => {
    event.preventDefault();
    isDragOver.value = true;
};

const onDragLeave = () => {
    isDragOver.value = false;
};

const onDrop = (event: DragEvent) => {
    event.preventDefault();
    isDragOver.value = false;
    if (event.dataTransfer?.files) {
        addFiles(Array.from(event.dataTransfer.files));
    }
};

onMounted(() => window.addEventListener('paste', handlePaste));
onUnmounted(() => window.removeEventListener('paste', handlePaste));
</script>

<template>
    <div class="space-y-2">
        <div
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-center transition-colors"
            :class="isDragOver
                ? 'border-primary bg-primary/5'
                : 'border-muted-foreground/25 hover:border-muted-foreground/50 hover:bg-muted/30'"
            @click="fileInput?.click()"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
        >
            <ImagePlus class="h-8 w-8 text-muted-foreground" />
            <div class="space-y-1">
                <p class="text-sm font-medium">
                    Drop files here, or <span class="text-primary">browse</span>
                </p>
                <p class="text-xs text-muted-foreground">
                    Paste a screenshot with Ctrl+V · Max 10MB per file
                </p>
            </div>
        </div>

        <input
            ref="fileInput"
            type="file"
            multiple
            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
            class="hidden"
            @change="onFilesSelected"
        />

        <div v-if="modelValue.length" class="space-y-2">
            <div
                v-for="(file, index) in modelValue"
                :key="index"
                class="flex items-center justify-between rounded-lg border p-2"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <Paperclip class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <span class="truncate text-sm">{{ file.name }}</span>
                    <span class="shrink-0 text-xs text-muted-foreground">{{ formatFileSize(file.size) }}</span>
                </div>
                <Button type="button" variant="ghost" size="sm" class="h-6 w-6 p-0 shrink-0 cursor-pointer" @click.stop="removeFile(index)">
                    <X class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <div v-if="errors?.length" class="space-y-1">
            <p v-for="(error, i) in errors" :key="i" class="text-sm text-destructive">{{ error }}</p>
        </div>
    </div>
</template>
