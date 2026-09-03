<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}>();

const showPagination = computed(() => props.links && props.links.length > 3);
</script>

<template>
    <div v-if="showPagination" class="flex flex-wrap items-center justify-center gap-1 py-4">
        <template v-for="(link, key) in links" :key="key">
            <div
                v-if="link.url === null"
                class="px-3 py-2 text-sm text-muted-foreground bg-background border rounded-md opacity-50"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                :class="[
                    'px-3 py-2 text-sm border rounded-md transition-colors',
                    link.active
                        ? 'bg-primary text-primary-foreground border-primary hover:bg-primary/90'
                        : 'bg-background text-foreground hover:bg-muted',
                ]"
                preserve-scroll
            >
                <span v-html="link.label"></span>
            </Link>
        </template>
    </div>
</template>

