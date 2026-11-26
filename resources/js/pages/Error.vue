<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const props = defineProps<{
    status: number;
}>();

const title = computed(() => {
    switch (props.status) {
        case 503:
            return 'Service Unavailable';
        case 500:
            return 'Server Error';
        case 419:
            return 'Page Expired';
        case 404:
            return 'Page Not Found';
        case 403:
            return 'Access Denied';
        default:
            return 'Something Went Wrong';
    }
});

const description = computed(() => {
    switch (props.status) {
        case 503:
            return 'The system is temporarily down for maintenance. Please try again in a moment.';
        case 500:
            return 'An unexpected server error occurred. Please try again or contact your administrator.';
        case 419:
            return 'Your session expired. Please refresh and try again.';
        case 404:
            return 'The page you are looking for could not be found.';
        case 403:
            return 'You do not have permission to access this resource.';
        default:
            return 'An unexpected error occurred.';
    }
});
</script>

<template>
    <Head :title="`${status} - ${title}`" />

    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background p-6 text-center"
    >
        <div class="mb-6 flex items-center gap-2">
            <AppLogo />
        </div>

        <div class="w-full max-w-md rounded-xl border bg-card p-8 shadow-xs">
            <div
                class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
            >
                <ShieldAlert class="size-6" />
            </div>
            <p class="font-mono text-sm font-semibold text-primary">
                Error {{ status }}
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-foreground">
                {{ title }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ description }}
            </p>
            <div class="mt-6 flex justify-center">
                <Button as-child class="gap-1.5">
                    <Link :href="home()">
                        <ArrowLeft class="size-4" />
                        <span>Back to Home</span>
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
