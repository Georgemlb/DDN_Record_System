<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Clock,
    ExternalLink,
    Languages,
    Mail,
    MapPin,
    Moon,
    Phone,
    Plane,
    Ship,
    Sun,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, login } from '@/routes';

type BranchInfo = {
    name: string;
    tagline: string;
    address: string;
    phone: string;
    telephone: string;
    email: string;
    facebook_name: string;
    facebook_url?: string;
    business_hours: string;
};

defineProps<{
    branch: BranchInfo;
}>();

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const services = [
    {
        title: 'Airline Ticketing',
        description:
            'Domestic and international flight reservations for one-way and round-trip travel with personalized fare assistance.',
        icon: Plane,
    },
    {
        title: 'Ferry & Sea Travel',
        description:
            'Convenient inter-island ferry ticket bookings and schedule assistance for smooth sea travel across routes.',
        icon: Ship,
    },
    {
        title: 'Document Translation',
        description:
            'Accurate and dependable document translation support tailored for travel, personal, and business paperwork.',
        icon: Languages,
    },
];

const steps = [
    {
        step: '01',
        title: 'Inquire or Visit',
        description:
            'Drop by our Toril branch or reach out by phone, email, or Facebook to share your travel or translation needs.',
    },
    {
        step: '02',
        title: 'Counter Processing',
        description:
            'Our team assists with your booking options, schedules, or document translation details right away.',
    },
    {
        step: '03',
        title: 'Claim & Go',
        description:
            'Receive your official acknowledgement slip and confirmed tickets or translated documents.',
    },
];
</script>

<template>
    <Head :title="`${branch.name} - Toril, Davao City`" />

    <div
        class="flex min-h-screen flex-col bg-background text-foreground selection:bg-primary selection:text-primary-foreground"
    >
        <!-- Sticky Navigation -->
        <header
            class="sticky top-0 z-40 border-b border-border/80 bg-background/85 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
            >
                <a href="#" class="flex items-center gap-2">
                    <AppLogo />
                </a>

                <nav
                    class="hidden items-center gap-6 text-sm font-medium text-muted-foreground md:flex"
                >
                    <a
                        href="#services"
                        class="transition-colors hover:text-foreground"
                    >
                        Services
                    </a>
                    <a
                        href="#how-it-works"
                        class="transition-colors hover:text-foreground"
                    >
                        How It Works
                    </a>
                    <a
                        href="#contact"
                        class="transition-colors hover:text-foreground"
                    >
                        Visit Us
                    </a>
                </nav>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                        :title="
                            resolvedAppearance === 'dark'
                                ? 'Switch to light mode'
                                : 'Switch to dark mode'
                        "
                        aria-label="Toggle theme"
                    >
                        <Sun
                            v-if="resolvedAppearance === 'dark'"
                            class="size-4"
                        />
                        <Moon v-else class="size-4" />
                    </button>

                    <Button
                        v-if="$page.props.auth?.user"
                        size="sm"
                        as-child
                        class="gap-1.5"
                    >
                        <Link :href="dashboard()">
                            Open Dashboard
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </Button>
                    <Button v-else variant="ghost" size="sm" as-child>
                        <Link :href="login()">Staff Portal</Link>
                    </Button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1">
            <!-- Hero Section -->
            <section
                class="relative overflow-hidden border-b border-border/60 py-16 sm:py-24"
            >
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_center,hsl(350_77%_41%/0.08),transparent_60%)]"
                />
                <div
                    class="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8"
                >
                    <Badge
                        variant="secondary"
                        class="mb-5 inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium"
                    >
                        <MapPin class="size-3.5 text-primary" />
                        <span
                            >Toril, Davao City &middot; Open
                            {{ branch.business_hours }}</span
                        >
                    </Badge>

                    <h1
                        class="text-4xl font-bold tracking-tight text-foreground sm:text-5xl"
                    >
                        {{ branch.name }}
                    </h1>

                    <p
                        class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                    >
                        {{ branch.tagline }}
                    </p>

                    <div
                        class="mt-8 flex flex-wrap items-center justify-center gap-3"
                    >
                        <Button size="lg" as-child class="gap-2">
                            <a href="#contact">
                                Visit or Contact Us
                                <ArrowRight class="size-4" />
                            </a>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <a href="#services">View Services</a>
                        </Button>
                    </div>
                </div>
            </section>

            <!-- Services Section -->
            <section id="services" class="py-16 sm:py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2
                            class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                        >
                            Our Core Services
                        </h2>
                        <p
                            class="mt-2 text-sm text-muted-foreground sm:text-base"
                        >
                            Reliable booking and translation assistance handled
                            directly at our Toril branch.
                        </p>
                    </div>

                    <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Card
                            v-for="service in services"
                            :key="service.title"
                            class="border-border/80 shadow-xs transition-shadow hover:shadow-sm"
                        >
                            <CardHeader>
                                <div
                                    class="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <component
                                        :is="service.icon"
                                        class="size-5"
                                    />
                                </div>
                                <CardTitle class="text-lg">
                                    {{ service.title }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ service.description }}
                            </CardContent>
                        </Card>
                    </div>

                    <p class="mt-8 text-center text-xs text-muted-foreground">
                        Visit our branch or message us on Facebook for current
                        flight/ferry schedules and service quotes.
                    </p>
                </div>
            </section>

            <!-- How It Works -->
            <section
                id="how-it-works"
                class="border-y border-border/60 bg-muted/30 py-14 sm:py-16"
            >
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2
                            class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                        >
                            How It Works
                        </h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Simple, transparent counter service from inquiry to
                            release.
                        </p>
                    </div>

                    <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div
                            v-for="item in steps"
                            :key="item.step"
                            class="rounded-xl border border-border/80 bg-card p-6 shadow-xs"
                        >
                            <span
                                class="font-mono text-xs font-bold text-primary"
                            >
                                STEP {{ item.step }}
                            </span>
                            <h3 class="mt-2 text-base font-semibold">
                                {{ item.title }}
                            </h3>
                            <p
                                class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Visit & Contact Section -->
            <section id="contact" class="py-16 sm:py-20">
                <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2
                            class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                        >
                            Visit &amp; Contact Us
                        </h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            We look forward to assisting you at our Toril, Davao
                            City office.
                        </p>
                    </div>

                    <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Branch Location & Hours -->
                        <Card class="border-border/80 shadow-xs">
                            <CardHeader>
                                <CardTitle class="text-lg">
                                    Branch Location &amp; Hours
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4 text-sm">
                                <div class="flex items-start gap-3">
                                    <MapPin
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Address
                                        </p>
                                        <p class="text-muted-foreground">
                                            {{ branch.address }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <Clock
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Business Hours
                                        </p>
                                        <p class="text-muted-foreground">
                                            {{ branch.business_hours }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Direct Contact Channels -->
                        <Card class="border-border/80 shadow-xs">
                            <CardHeader>
                                <CardTitle class="text-lg">
                                    Get in Touch
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4 text-sm">
                                <div class="flex items-start gap-3">
                                    <Phone
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Mobile &amp; Telephone
                                        </p>
                                        <p
                                            class="text-muted-foreground tabular-nums"
                                        >
                                            Mobile: {{ branch.phone }}
                                        </p>
                                        <p
                                            class="text-muted-foreground tabular-nums"
                                        >
                                            Telephone: {{ branch.telephone }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <Mail
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Email
                                        </p>
                                        <a
                                            :href="`mailto:${branch.email}`"
                                            class="text-primary hover:underline"
                                        >
                                            {{ branch.email }}
                                        </a>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <ExternalLink
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Facebook Page
                                        </p>
                                        <a
                                            v-if="branch.facebook_url"
                                            :href="branch.facebook_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-primary hover:underline"
                                        >
                                            {{ branch.facebook_name }}
                                            <ExternalLink class="size-3" />
                                        </a>
                                        <p v-else class="text-muted-foreground">
                                            {{ branch.facebook_name }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-border/60 py-6 text-xs text-muted-foreground"
        >
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
            >
                <p>
                    &copy; {{ new Date().getFullYear() }} {{ branch.name }}. All
                    rights reserved.
                </p>
                <Link
                    :href="$page.props.auth?.user ? dashboard() : login()"
                    class="transition-colors hover:text-foreground"
                >
                    {{
                        $page.props.auth?.user
                            ? 'Open Dashboard'
                            : 'Staff Portal'
                    }}
                </Link>
            </div>
        </footer>
    </div>
</template>
