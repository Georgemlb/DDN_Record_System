<script setup lang="ts">
import { PlaneTakeoff } from '@lucide/vue';
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm"
    >
        <PlaneTakeoff class="size-5" :stroke-width="2.25" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate text-sm leading-tight font-bold tracking-tight"
            >DDN</span
        >
        <span class="truncate text-[10px] leading-tight text-muted-foreground"
            >Ticketing Services</span
        >
    </div>
</template>
