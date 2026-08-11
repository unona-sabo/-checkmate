<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="alert-description"
    :class="cn('text-muted-foreground col-start-2 grid w-full min-w-0 justify-items-start gap-1 text-sm break-words [&_p]:leading-relaxed', props.class)"
  >
    <slot />
  </div>
</template>
