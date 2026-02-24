<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Loader2, RotateCcw } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    projectId: number;
    text: string;
}>();

const emit = defineEmits<{
    translated: [text: string];
}>();

const translating = ref<'en' | 'uk' | null>(null);
const originalText = ref<string | null>(null);

const translate = async (targetLanguage: 'en' | 'uk') => {
    if (!props.text.trim() || translating.value) return;

    if (originalText.value === null) {
        originalText.value = props.text;
    }

    translating.value = targetLanguage;

    try {
        const provider = localStorage.getItem('ai_provider');
        const response = await axios.post(`/projects/${props.projectId}/translate`, {
            text: props.text,
            target_language: targetLanguage,
            ...(provider ? { provider } : {}),
        });

        if (response.data.translated_text) {
            emit('translated', response.data.translated_text);
        }
    } catch {
        // Silently fail — user can retry
    } finally {
        translating.value = null;
    }
};

const restore = () => {
    if (originalText.value !== null) {
        emit('translated', originalText.value);
        originalText.value = null;
    }
};
</script>

<template>
    <div class="flex items-center gap-1">
        <Button
            v-if="originalText !== null"
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 px-1.5 text-xs cursor-pointer"
            :disabled="translating !== null"
            @click="restore"
        >
            <RotateCcw class="h-3 w-3" />
        </Button>
        <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 px-1.5 text-xs cursor-pointer"
            :disabled="!text.trim() || translating !== null"
            @click="translate('en')"
        >
            <Loader2 v-if="translating === 'en'" class="h-3 w-3 animate-spin" />
            <span v-else>EN</span>
        </Button>
        <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 px-1.5 text-xs cursor-pointer"
            :disabled="!text.trim() || translating !== null"
            @click="translate('uk')"
        >
            <Loader2 v-if="translating === 'uk'" class="h-3 w-3 animate-spin" />
            <span v-else>UK</span>
        </Button>
    </div>
</template>
