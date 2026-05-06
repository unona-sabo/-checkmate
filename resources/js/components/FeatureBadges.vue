<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Tag } from 'lucide-vue-next';

interface Feature {
    id: number;
    name?: string;
    module?: string[] | null;
}

const props = withDefaults(
    defineProps<{
        features: Feature[];
        maxVisible?: number;
    }>(),
    {
        maxVisible: 2,
    },
);

const visibleFeatures = computed(() =>
    props.features.slice(0, props.maxVisible),
);

const remainingCount = computed(() =>
    Math.max(0, props.features.length - props.maxVisible),
);

const remainingNames = computed(() =>
    props.features
        .slice(props.maxVisible)
        .map((f) => f.name ?? `#${f.id}`)
        .join(', '),
);
</script>

<template>
    <div v-if="features.length > 0" class="inline-flex items-center gap-1">
        <Badge
            v-for="feature in visibleFeatures"
            :key="feature.id"
            variant="feature"
            class="h-4 gap-0.5 px-1.5 text-[10px] font-normal"
        >
            <Tag class="h-2.5 w-2.5" />
            {{ feature.name ?? `#${feature.id}` }}
        </Badge>
        <Badge
            v-if="remainingCount > 0"
            variant="feature"
            class="h-4 px-1.5 text-[10px] font-normal"
            :title="remainingNames"
        >
            +{{ remainingCount }}
        </Badge>
    </div>
</template>
