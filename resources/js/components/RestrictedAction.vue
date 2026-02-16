<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCanEdit } from '@/composables/useCanEdit';

const { canEdit } = useCanEdit();
</script>

<template>
    <TooltipProvider v-if="!canEdit" :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <span class="inline-flex">
                    <span class="pointer-events-none opacity-50">
                        <slot />
                    </span>
                </span>
            </TooltipTrigger>
            <TooltipContent>
                You don't have permission to edit
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <slot v-else />
</template>
