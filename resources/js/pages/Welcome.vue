<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    FileText,
    ReceiptText,
    ShieldCheck,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Welcome to DDN Record System" />

    <div
        class="flex min-h-screen flex-col bg-background text-foreground selection:bg-primary selection:text-primary-foreground"
    >
        <!-- Top Navigation -->
        <header
            class="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur-sm"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-2">
                    <AppLogo />
                </div>

                <nav class="flex items-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Button as-child>
                            <Link :href="dashboard()" class="gap-1.5">
                                Go to Dashboard
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" as-child>
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button as-child>
                            <Link :href="register()">Register</Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1">
            <!-- Hero Section -->
            <section
                class="relative overflow-hidden border-b border-border/50 py-16 sm:py-24"
            >
                <div class="mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8">
                    <Badge variant="secondary" class="mb-4 px-3 py-1 text-xs">
                        <ShieldCheck class="mr-1.5 size-3.5 text-primary" />
                        Internal Operations & Bookkeeping
                    </Badge>

                    <h1
                        class="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl"
                    >
                        DDN Travel & Ticketing Services
                    </h1>

                    <p
                        class="mx-auto mt-5 max-w-2xl text-base text-muted-foreground sm:text-lg"
                    >
                        Centralized record management, live financial tracking,
                        and operational expense control designed for daily
                        branch workflow.
                    </p>

                    <div
                        class="mt-8 flex flex-wrap items-center justify-center gap-4"
                    >
                        <template v-if="$page.props.auth.user">
                            <Button size="lg" as-child>
                                <Link :href="dashboard()" class="gap-2">
                                    Open Dashboard
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                        </template>
                        <template v-else>
                            <Button size="lg" as-child>
                                <Link :href="login()" class="gap-2">
                                    Sign In to System
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                            <Button size="lg" variant="outline" as-child>
                                <Link :href="register()">Create Account</Link>
                            </Button>
                        </template>
                    </div>
                </div>
            </section>

            <!-- Features Grid -->
            <section class="py-12 sm:py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Card class="border-border/60 transition-colors">
                            <CardHeader>
                                <div
                                    class="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <ReceiptText class="size-5" />
                                </div>
                                <CardTitle class="text-lg">
                                    Transaction Management
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="text-sm text-muted-foreground">
                                Record customer bookings across Airline Tickets,
                                PSA Document Assistance, Translations, Online
                                Appointments, and Printing with automated share
                                computations.
                            </CardContent>
                        </Card>

                        <Card class="border-border/60 transition-colors">
                            <CardHeader>
                                <div
                                    class="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <FileText class="size-5" />
                                </div>
                                <CardTitle class="text-lg">
                                    Expense Monitoring
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="text-sm text-muted-foreground">
                                Track office supplies, utility disbursements,
                                recurring bills, and operational expenses
                                segmented across Cash, GCash, and Bank
                                Transfers.
                            </CardContent>
                        </Card>

                        <Card class="border-border/60 transition-colors">
                            <CardHeader>
                                <div
                                    class="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <BarChart3 class="size-5" />
                                </div>
                                <CardTitle class="text-lg">
                                    Live Financial Analytics
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="text-sm text-muted-foreground">
                                Monitor net revenue, sales trends, and expense
                                breakdowns in real time with interactive period
                                selectors and visual charts.
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-border/50 py-6 text-center text-xs text-muted-foreground"
        >
            <div class="mx-auto max-w-7xl px-4">
                &copy; {{ new Date().getFullYear() }} DDN Travel & Ticketing
                Services. All rights reserved.
            </div>
        </footer>
    </div>
</template>
