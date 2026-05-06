<script setup lang="ts">
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    id?: string;
    name?: string;
    placeholder?: string;
    autocomplete?: string;
    required?: boolean;
    tabindex?: number;
    class?: string;
}>();

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :type="visible ? 'text' : 'password'"
            :name="name"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            :tabindex="tabindex"
            :class="['pr-10', props.class]"
        />
        <button
            type="button"
            :tabindex="-1"
            class="absolute top-0 right-0 flex h-9 w-9 cursor-pointer items-center justify-center text-muted-foreground hover:text-foreground"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
        </button>
    </div>
</template>
