<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    BanknoteArrowUp,
    ReceiptText,
    WalletCards,
    Zap,
} from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <section>
            <p class="text-sm font-medium text-primary">Business overview</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                Dashboard
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Your business activity will appear here as records are added.
            </p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">
                            Total Sales
                        </p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            &#8369;0.00
                        </p>
                    </div>
                    <span
                        class="rounded-md bg-secondary p-2 text-secondary-foreground"
                        ><BanknoteArrowUp class="size-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">
                            Total Expenses
                        </p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            &#8369;0.00
                        </p>
                    </div>
                    <span class="rounded-md bg-muted p-2 text-muted-foreground"
                        ><ReceiptText class="size-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-lg border bg-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">
                            Utilities
                        </p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            &#8369;0.00
                        </p>
                    </div>
                    <span class="rounded-md bg-muted p-2 text-muted-foreground"
                        ><Zap class="size-5"
                    /></span>
                </div>
            </article>
            <article
                class="rounded-lg border border-primary/20 bg-primary p-5 text-primary-foreground"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-primary-foreground/75"
                        >
                            Net
                        </p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            &#8369;0.00
                        </p>
                    </div>
                    <span class="rounded-md bg-white/15 p-2"
                        ><WalletCards class="size-5"
                    /></span>
                </div>
            </article>
        </section>

        <section class="rounded-lg border bg-card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Sales Overview</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        A sales chart will be available when transaction records
                        are added.
                    </p>
                </div>
                <span
                    class="rounded-md bg-secondary p-2 text-secondary-foreground"
                    ><ArrowUpRight class="size-5"
                /></span>
            </div>
            <div
                class="mt-8 flex h-52 items-end gap-3 border-b border-dashed px-2 pb-1 sm:gap-5"
            >
                <div
                    v-for="height in [30, 46, 38, 62, 50, 72, 42]"
                    :key="height"
                    class="flex flex-1 flex-col items-center gap-2"
                >
                    <div
                        class="w-full max-w-10 rounded-t-sm bg-secondary"
                        :style="{ height: `${height}%` }"
                    ></div>
                    <span class="text-xs text-muted-foreground">&mdash;</span>
                </div>
            </div>
            <div
                class="mt-4 flex items-center gap-2 text-sm text-muted-foreground"
            >
                <ArrowDownRight class="size-4" /> No sales records yet
            </div>
        </section>
    </main>
</template>
