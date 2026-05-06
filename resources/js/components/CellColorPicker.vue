<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
    currentColor: string | null;
}>();

const emit = defineEmits<{
    select: [color: string | null];
}>();

const open = ref(false);

const colors = [
    { label: 'Red', value: '#ef4444' },
    { label: 'Orange', value: '#f97316' },
    { label: 'Yellow', value: '#eab308' },
    { label: 'Green', value: '#22c55e' },
    { label: 'Blue', value: '#3b82f6' },
    { label: 'Purple', value: '#a855f7' },
    { label: 'Pink', value: '#ec4899' },
    { label: 'Gray', value: '#6b7280' },
];

const selectColor = (color: string | null) => {
    emit('select', color);
    open.value = false;
};
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded hover:bg-accent"
            :title="'Text color'"
            @mousedown.prevent="open = !open"
        >
            <span
                class="text-xs font-bold"
                :style="{ color: currentColor || 'currentColor' }"
                >A</span
            >
            <span
                class="absolute bottom-1 left-1/2 h-0.5 w-3 -translate-x-1/2 rounded"
                :style="{ backgroundColor: currentColor || 'currentColor' }"
            />
        </button>
        <div
            v-if="open"
            class="absolute top-full left-0 z-50 mt-1 rounded-lg border bg-popover p-2 shadow-md"
            style="width: 160px"
            @mousedown.prevent
        >
            <div class="mb-2 flex flex-wrap justify-start gap-2">
                <button
                    v-for="color in colors"
                    :key="color.value"
                    type="button"
                    class="shrink-0 cursor-pointer rounded-full border border-border transition-transform hover:scale-110"
                    style="width: 24px; height: 24px"
                    :style="{ backgroundColor: color.value }"
                    :title="color.label"
                    @mousedown.prevent="selectColor(color.value)"
                />
            </div>
            <button
                type="button"
                class="w-full cursor-pointer py-0.5 text-xs text-muted-foreground hover:text-foreground"
                @mousedown.prevent="selectColor(null)"
            >
                Reset
            </button>
        </div>
    </div>
</template>
