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
            class="flex items-center justify-center w-7 h-7 rounded hover:bg-accent cursor-pointer"
            :title="'Text color'"
            @mousedown.prevent="open = !open"
        >
            <span class="text-xs font-bold" :style="{ color: currentColor || 'currentColor' }">A</span>
            <span
                class="absolute bottom-1 left-1/2 -translate-x-1/2 h-0.5 w-3 rounded"
                :style="{ backgroundColor: currentColor || 'currentColor' }"
            />
        </button>
        <div
            v-if="open"
            class="absolute top-full left-0 mt-1 bg-popover border rounded-lg shadow-md p-2 z-50"
            style="width: 160px;"
            @mousedown.prevent
        >
            <div class="flex flex-wrap justify-start gap-2 mb-2">
                <button
                    v-for="color in colors"
                    :key="color.value"
                    type="button"
                    class="shrink-0 rounded-full border border-border cursor-pointer hover:scale-110 transition-transform"
                    style="width: 24px; height: 24px;"
                    :style="{ backgroundColor: color.value }"
                    :title="color.label"
                    @mousedown.prevent="selectColor(color.value)"
                />
            </div>
            <button
                type="button"
                class="w-full text-xs text-muted-foreground hover:text-foreground cursor-pointer py-0.5"
                @mousedown.prevent="selectColor(null)"
            >
                Reset
            </button>
        </div>
    </div>
</template>
