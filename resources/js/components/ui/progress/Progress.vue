<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{
    modelValue?: number;
    max?: number;
    class?: string;
    gradient?: boolean;
}>(), {
    modelValue: 0,
    max: 100,
    gradient: true,
});

const percentage = computed(() => {
    return Math.min(100, Math.max(0, (props.modelValue / props.max) * 100));
});
</script>

<template>
    <div
        :class="cn(
            'relative h-2 w-full overflow-hidden rounded-full bg-secondary',
            props.class
        )"
    >
        <div
            :class="[
                'h-full transition-all duration-300 ease-in-out',
                gradient ? 'progress-gradient' : 'bg-primary'
            ]"
            :style="{ width: `${percentage}%` }"
        />
    </div>
</template>
