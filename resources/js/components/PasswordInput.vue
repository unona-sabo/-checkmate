<script setup lang="ts">
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

defineProps<{
    id?: string;
    name?: string;
    placeholder?: string;
    autocomplete?: string;
    required?: boolean;
    tabindex?: number;
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
            class="pr-10"
        />
        <button
            type="button"
            :tabindex="-1"
            class="absolute right-0 top-0 flex h-9 w-9 items-center justify-center text-muted-foreground hover:text-foreground cursor-pointer"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
        </button>
    </div>
</template>
