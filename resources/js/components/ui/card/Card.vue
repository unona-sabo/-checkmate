<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  class?: HTMLAttributes["class"]
  gradient?: boolean
}>(), {
  gradient: false
})
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'text-card-foreground flex flex-col gap-6 rounded-xl border py-[15px] shadow-sm overflow-hidden max-w-full',
        gradient
          ? 'bg-gradient-to-br from-white via-slate-50 to-blue-50/30 dark:from-slate-900 dark:via-slate-800 dark:to-blue-900/20 border-blue-100/50 dark:border-blue-800/30'
          : 'bg-card',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
