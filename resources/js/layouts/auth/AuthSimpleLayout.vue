<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ReceiptText, ShieldCheck, Wallet } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-2">
        <!-- Left Brand Panel (Desktop) -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden border-r bg-card p-10 lg:flex"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,hsl(350_77%_41%/0.09),transparent_55%),radial-gradient(circle_at_bottom_right,hsl(213_72%_45%/0.07),transparent_55%)]"
            />
            <div class="relative z-10">
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-2 font-medium"
                >
                    <AppLogo />
                </Link>
            </div>

            <div class="relative z-10 max-w-md space-y-6">
                <div
                    class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    <ShieldCheck class="size-3.5 text-primary" />
                    <span>Branch Operations &amp; Financial Ledger</span>
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-foreground">
                    DDN Travel &amp; Ticketing Services
                </h2>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Unified point-of-sale record management, real-time GCash and
                    flight portal balances, and daily drawer settlements.
                </p>

                <div class="grid gap-3 pt-2">
                    <div
                        class="flex items-start gap-3 rounded-lg border bg-background/70 p-3.5"
                    >
                        <ReceiptText
                            class="mt-0.5 size-4 shrink-0 text-primary"
                        />
                        <div class="text-xs">
                            <p class="font-semibold text-foreground">
                                Service &amp; Payment Tracking
                            </p>
                            <p class="text-muted-foreground">
                                Airline, ferry, PSA, translation, and remittance
                                records with automatic net margin &amp; splits.
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex items-start gap-3 rounded-lg border bg-background/70 p-3.5"
                    >
                        <Wallet class="mt-0.5 size-4 shrink-0 text-primary" />
                        <div class="text-xs">
                            <p class="font-semibold text-foreground">
                                Live Wallet &amp; Drawer Reconciliation
                            </p>
                            <p class="text-muted-foreground">
                                Continuous running balances across Cash Drawer,
                                GCash Ledger, and Flight Portal Deposits.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="relative z-10 text-xs text-muted-foreground">
                Internal Branch System &middot; Authorized Personnel Only
            </p>
        </div>

        <!-- Right Auth Card -->
        <div class="flex flex-col items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-sm">
                <div class="flex flex-col gap-7">
                    <div
                        class="flex flex-col items-center gap-3 lg:items-start"
                    >
                        <Link
                            :href="home()"
                            class="flex items-center gap-2 font-medium lg:hidden"
                        >
                            <AppLogo />
                        </Link>
                        <div class="space-y-1.5 text-center lg:text-left">
                            <h1 class="text-2xl font-semibold tracking-tight">
                                {{ title }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                {{ description }}
                            </p>
                        </div>
                    </div>
                    <div class="rounded-xl border bg-card p-6 shadow-xs">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
