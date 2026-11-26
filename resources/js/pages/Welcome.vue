<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
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
import { computed } from 'vue';
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

const props = defineProps<{
    branch: BranchInfo;
}>();

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const phoneLinks = computed(() =>
    props.branch.phone
        .split('/')
        .map((num) => num.trim())
        .filter((num) => num.length > 0)
        .map((num) => ({
            label: num,
            href: `tel:${num.replace(/[^+\d]/g, '')}`,
        })),
);

const telephoneHref = computed(
    () => `tel:${props.branch.telephone.replace(/[^+\d]/g, '')}`,
);

const mapsUrl = computed(
    () =>
        `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.branch.address)}`,
);

const services = [
    {
        title: 'Airline Ticketing',
        description:
            'Domestic and international flight reservations for one-way and round-trip travel with personalized fare assistance.',
        image: '/images/landing/service-airline.jpg',
        alt: 'Commercial aircraft wing above sunrise clouds',
        icon: Plane,
        chips: ['Domestic & Int’l', 'One-Way & Round-Trip'],
    },
    {
        title: 'Ferry & Sea Travel',
        description:
            'Convenient inter-island ferry ticket bookings and schedule assistance for smooth sea travel across routes.',
        image: '/images/landing/service-ferry.jpg',
        alt: 'Modern passenger ferry cruising across calm turquoise waters',
        icon: Ship,
        chips: ['Inter-Island Routes', 'Schedule Assistance'],
    },
    {
        title: 'Document Translation',
        description:
            'Accurate and dependable document translation support tailored for travel, personal, and business paperwork.',
        image: '/images/landing/service-translation.jpg',
        alt: 'Minimalist travel documents, passport cover, pen, and compass',
        icon: Languages,
        chips: ['Travel & Personal Docs', 'Accurate Turnaround'],
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
            <!-- Visual Hero Section -->
            <section
                class="relative overflow-hidden border-b border-border/60 py-14 sm:py-20 lg:py-24"
            >
                <!-- Subtle Radial Glow & Flight Arc SVG Mesh -->
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,hsl(350_77%_41%/0.09),transparent_55%),radial-gradient(circle_at_bottom_right,hsl(213_72%_45%/0.07),transparent_55%)]"
                />
                <svg
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 h-full w-full text-primary/10 dark:text-primary/15"
                    fill="none"
                    viewBox="0 0 1200 600"
                    preserveAspectRatio="xMidYMid slice"
                >
                    <path
                        d="M-60 440 C 260 180, 640 160, 1240 340"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-dasharray="6 8"
                    />
                    <path
                        d="M120 580 C 420 340, 820 260, 1260 120"
                        stroke="currentColor"
                        stroke-width="1.25"
                        stroke-dasharray="4 8"
                    />
                </svg>

                <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div
                        class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12"
                    >
                        <!-- Left Column: Copy & CTAs -->
                        <div class="lg:col-span-6">
                            <Badge
                                variant="secondary"
                                class="mb-5 inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium"
                            >
                                <MapPin class="size-3.5 text-primary" />
                                <span>
                                    Toril, Davao City &middot; Open
                                    {{ branch.business_hours }}
                                </span>
                            </Badge>

                            <h1
                                class="text-4xl font-bold tracking-tight text-foreground sm:text-5xl"
                            >
                                {{ branch.name }}
                            </h1>

                            <p
                                class="mt-4 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                            >
                                {{ branch.tagline }}
                            </p>

                            <div class="mt-7 flex flex-wrap items-center gap-3">
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

                            <div
                                class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2.5 border-t border-border/60 pt-6 text-xs font-medium text-muted-foreground"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-primary" />
                                    Walk-In Counter Support
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-primary" />
                                    Domestic &amp; Int&rsquo;l Routes
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-primary" />
                                    Official Claim Slips
                                </span>
                            </div>
                        </div>

                        <!-- Right Column: Hero Artwork + Floating Route Card -->
                        <div class="relative lg:col-span-6">
                            <div
                                class="relative overflow-hidden rounded-2xl bg-muted shadow-lg ring-1 ring-border/80"
                            >
                                <img
                                    src="/images/landing/hero-travel.jpg"
                                    alt="Commercial aircraft flying over tropical Philippine islands at sunrise"
                                    fetchpriority="high"
                                    class="aspect-[4/3] w-full object-cover"
                                />
                                <div
                                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/15 to-transparent"
                                />

                                <!-- Floating Glassmorphic Boarding Pass / Route Card -->
                                <div
                                    class="absolute inset-x-4 bottom-4 rounded-xl border border-border/80 bg-card/92 p-4 shadow-md backdrop-blur-md sm:inset-x-5 sm:bottom-5"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2 text-[11px] font-medium"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1.5 text-foreground"
                                        >
                                            <span
                                                class="size-2 rounded-full bg-emerald-500"
                                            />
                                            Counter Booking Ready
                                        </span>
                                        <span class="text-muted-foreground">
                                            Open {{ branch.business_hours }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3 flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="font-mono text-lg font-bold tracking-tight text-foreground"
                                            >
                                                DVO
                                            </p>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                Davao City
                                            </p>
                                        </div>

                                        <div
                                            class="flex flex-1 items-center gap-2 px-1"
                                        >
                                            <span
                                                class="h-px flex-1 border-t border-dashed border-primary/50"
                                            />
                                            <span
                                                class="flex size-7 items-center justify-center rounded-full bg-primary/10 text-primary"
                                            >
                                                <Plane class="size-3.5" />
                                            </span>
                                            <span
                                                class="h-px flex-1 border-t border-dashed border-primary/50"
                                            />
                                        </div>

                                        <div class="text-right">
                                            <p
                                                class="font-mono text-lg font-bold tracking-tight text-foreground"
                                            >
                                                MNL &middot; CEB
                                            </p>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                Domestic &amp; Int&rsquo;l
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Illustrated Services Section -->
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
                            class="group overflow-hidden border-border/80 pt-0 shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div
                                class="relative aspect-[16/10] overflow-hidden bg-muted"
                            >
                                <img
                                    :src="service.image"
                                    :alt="service.alt"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                />
                                <div
                                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/50 via-transparent to-transparent"
                                />
                                <div
                                    class="absolute bottom-3 left-4 flex size-10 items-center justify-center rounded-lg border border-border/60 bg-card/95 text-primary shadow-sm backdrop-blur-xs"
                                >
                                    <component
                                        :is="service.icon"
                                        class="size-5"
                                    />
                                </div>
                            </div>

                            <CardHeader class="pb-2">
                                <CardTitle class="text-lg">
                                    {{ service.title }}
                                </CardTitle>
                            </CardHeader>

                            <CardContent class="space-y-4">
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ service.description }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="chip in service.chips"
                                        :key="chip"
                                        class="rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                    >
                                        {{ chip }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <p class="mt-8 text-center text-xs text-muted-foreground">
                        Visit our branch or message us on Facebook for current
                        flight/ferry schedules and service quotes.
                    </p>
                </div>
            </section>

            <!-- Connected How It Works Section -->
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

                    <div class="relative mt-10">
                        <div
                            aria-hidden="true"
                            class="pointer-events-none absolute top-7 right-24 left-24 hidden border-t-2 border-dashed border-primary/25 md:block"
                        />

                        <div
                            class="relative grid grid-cols-1 gap-6 md:grid-cols-3"
                        >
                            <div
                                v-for="item in steps"
                                :key="item.step"
                                class="rounded-xl border border-border/80 bg-card p-6 shadow-xs"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 font-mono text-sm font-bold text-primary ring-4 ring-card"
                                    >
                                        {{ item.step }}
                                    </span>
                                    <h3 class="text-base font-semibold">
                                        {{ item.title }}
                                    </h3>
                                </div>
                                <p
                                    class="mt-3 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Unified Single-Branch Visit & Contact Section -->
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

                    <div
                        class="mt-10 grid overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm md:grid-cols-5"
                    >
                        <!-- Left Accent Panel: Branch Location & Hours -->
                        <div
                            class="relative flex flex-col justify-between overflow-hidden border-b border-border/80 bg-accent/55 p-6 sm:p-8 md:col-span-2 md:border-r md:border-b-0 dark:bg-accent/30"
                        >
                            <svg
                                aria-hidden="true"
                                class="pointer-events-none absolute inset-0 h-full w-full text-primary/10"
                                fill="none"
                            >
                                <defs>
                                    <pattern
                                        id="branch-grid"
                                        width="24"
                                        height="24"
                                        patternUnits="userSpaceOnUse"
                                    >
                                        <circle
                                            cx="2"
                                            cy="2"
                                            r="1"
                                            fill="currentColor"
                                        />
                                    </pattern>
                                </defs>
                                <rect
                                    width="100%"
                                    height="100%"
                                    fill="url(#branch-grid)"
                                />
                            </svg>

                            <div class="relative space-y-5">
                                <Badge
                                    variant="secondary"
                                    class="inline-flex items-center gap-1.5 bg-background/85 text-xs font-medium"
                                >
                                    <MapPin class="size-3.5 text-primary" />
                                    Toril Branch
                                </Badge>

                                <div class="space-y-4 text-sm">
                                    <div>
                                        <p
                                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >
                                            Office Address
                                        </p>
                                        <p
                                            class="mt-1 font-medium text-foreground"
                                        >
                                            {{ branch.address }}
                                        </p>
                                    </div>

                                    <div>
                                        <p
                                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >
                                            Business Hours
                                        </p>
                                        <p
                                            class="mt-1 inline-flex items-center gap-1.5 font-medium text-foreground"
                                        >
                                            <Clock
                                                class="size-4 text-primary"
                                            />
                                            {{ branch.business_hours }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="relative mt-6 pt-4">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    class="gap-1.5 bg-background/90"
                                >
                                    <a
                                        :href="mapsUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <MapPin class="size-3.5 text-primary" />
                                        View on Google Maps
                                        <ExternalLink class="size-3" />
                                    </a>
                                </Button>
                            </div>
                        </div>

                        <!-- Right Panel: Direct Contact Channels -->
                        <div class="p-6 sm:p-8 md:col-span-3">
                            <h3 class="text-lg font-semibold text-foreground">
                                Direct Contact Channels
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Reach our counter team directly via phone,
                                email, or Facebook.
                            </p>

                            <div class="mt-6 space-y-5 text-sm">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <Phone class="size-4" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Mobile &amp; Telephone
                                        </p>
                                        <div
                                            class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-muted-foreground tabular-nums"
                                        >
                                            <span>Mobile:</span>
                                            <template
                                                v-for="(
                                                    item, idx
                                                ) in phoneLinks"
                                                :key="item.label"
                                            >
                                                <a
                                                    :href="item.href"
                                                    class="text-primary hover:underline"
                                                >
                                                    {{ item.label }}
                                                </a>
                                                <span
                                                    v-if="
                                                        idx <
                                                        phoneLinks.length - 1
                                                    "
                                                    >/</span
                                                >
                                            </template>
                                        </div>
                                        <p
                                            class="mt-0.5 text-muted-foreground tabular-nums"
                                        >
                                            Telephone:
                                            <a
                                                :href="telephoneHref"
                                                class="text-primary hover:underline"
                                            >
                                                {{ branch.telephone }}
                                            </a>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <Mail class="size-4" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Email
                                        </p>
                                        <a
                                            :href="`mailto:${branch.email}`"
                                            class="mt-0.5 inline-block text-primary hover:underline"
                                        >
                                            {{ branch.email }}
                                        </a>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <ExternalLink class="size-4" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">
                                            Facebook Page
                                        </p>
                                        <a
                                            v-if="branch.facebook_url"
                                            :href="branch.facebook_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-0.5 inline-flex items-center gap-1 text-primary hover:underline"
                                        >
                                            {{ branch.facebook_name }}
                                            <ExternalLink class="size-3" />
                                        </a>
                                        <p
                                            v-else
                                            class="mt-0.5 text-muted-foreground"
                                        >
                                            {{ branch.facebook_name }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
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
