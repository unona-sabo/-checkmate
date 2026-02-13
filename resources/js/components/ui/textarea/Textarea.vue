<script setup lang="ts">
import { cn } from '@/lib/utils';
import { ref, onMounted, watch, nextTick } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    class?: string;
    modelValue?: string;
    autoResize?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const textareaRef = ref<HTMLTextAreaElement | null>(null);

const resize = () => {
    if (!props.autoResize || !textareaRef.value) return;
    textareaRef.value.style.height = 'auto';
    textareaRef.value.style.height = textareaRef.value.scrollHeight + 'px';
};

const onInput = (e: Event) => {
    emit('update:modelValue', (e.target as HTMLTextAreaElement).value);
    if (props.autoResize) {
        resize();
    }
};

onMounted(() => {
    if (props.autoResize) {
        nextTick(resize);
    }
});

watch(() => props.modelValue, () => {
    if (props.autoResize) {
        nextTick(resize);
    }
});
</script>

<template>
    <textarea
        ref="textareaRef"
        :class="cn(
            'block min-h-[60px] w-full max-w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 box-border whitespace-pre-wrap break-words',
            autoResize && 'resize-none overflow-hidden',
            props.class
        )"
        :value="modelValue"
        @input="onInput"
        v-bind="$attrs"
    />
</template>
