<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Outer circular badge -->
        <circle cx="12" cy="12" r="10" />
        <!-- Stylized GCash 'G' emblem -->
        <path d="M15.5 8.8a4.8 4.8 0 1 0 1 4.7h-4.5" />
    </svg>
</template>
