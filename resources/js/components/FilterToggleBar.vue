<script setup lang="ts">
import { SlidersHorizontal, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        activeCount?: number;
        defaultOpen?: boolean;
    }>(),
    {
        activeCount: 0,
        defaultOpen: false,
    },
);

const emit = defineEmits<{
    clear: [];
}>();

const isOpen = ref(props.defaultOpen || props.activeCount > 0);

watch(
    () => props.activeCount,
    (count) => {
        if (count > 0 && !isOpen.value) {
            isOpen.value = true;
        }
    },
);
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-[220px] flex-1">
                <slot name="search" />
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-9 gap-1.5"
                @click="isOpen = !isOpen"
            >
                <SlidersHorizontal class="size-3.5" />
                <span>Filters</span>
                <Badge
                    v-if="activeCount > 0"
                    variant="secondary"
                    class="ml-0.5 h-4 px-1.5 text-[10px]"
                >
                    {{ activeCount }}
                </Badge>
            </Button>

            <Button
                v-if="activeCount > 0"
                type="button"
                variant="ghost"
                size="sm"
                class="h-9 gap-1 text-xs text-muted-foreground"
                @click="emit('clear')"
            >
                <X class="size-3.5" />
                Clear
            </Button>
        </div>

        <div
            v-if="isOpen"
            class="grid gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4"
        >
            <slot name="filters" />
        </div>
    </div>
</template>
