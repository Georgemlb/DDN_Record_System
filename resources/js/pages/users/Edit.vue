<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, UserCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { User } from '@/types/auth';

const props = defineProps<{
    user: User;
    roles: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'User Management',
                href: '/users',
            },
            {
                title: 'Edit User',
                href: '#',
            },
        ],
    },
});

const form = useForm({
    name: props.user.name,
    username: props.user.username || '',
    email: props.user.email || '',
    role: props.user.role,
    password: '',
});

const submit = () => {
    form.put(`/users/${props.user.id}`, {
        preserveScroll: true,
    });
};

const getRoleDescription = (role: string) => {
    switch (role) {
        case 'tech_admin':
            return 'Full administrative access, database backup downloads, and system maintenance.';
        case 'admin':
            return 'Manage daily business records, reports, flight portal balances, and configuration settings.';
        case 'staff':
            return 'Restricted to creating and editing their own daily transactions and expenses at the store counter.';
        default:
            return '';
    }
};
</script>

<template>
    <Head :title="`Edit User — ${user.name}`" />

    <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex items-center gap-4">
            <Button as-child variant="ghost" size="icon" class="h-9 w-9">
                <Link href="/users">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Edit User
                </h1>
                <p class="text-sm text-muted-foreground">
                    Update profile, role, or credentials for
                    <span class="font-semibold text-foreground">{{
                        user.name
                    }}</span
                    >.
                </p>
            </div>
        </div>

        <Card class="border shadow-sm">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <Pencil class="h-5 w-5 text-primary" />
                    Account Details
                </CardTitle>
                <CardDescription>
                    Modify username, role, or assign a new password.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Full Name -->
                    <div class="space-y-2">
                        <Label for="name"
                            >Full Name
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Username -->
                    <div class="space-y-2">
                        <Label for="username"
                            >Username
                            <span class="text-destructive">*</span></Label
                        >
                        <Input id="username" v-model="form.username" required />
                        <p class="text-xs text-muted-foreground">
                            Used for signing in. Letters, numbers, and
                            underscores only.
                        </p>
                        <InputError :message="form.errors.username" />
                    </div>

                    <!-- Email (Optional) -->
                    <div class="space-y-2">
                        <Label for="email"
                            >Email Address
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >(Optional)</span
                            ></Label
                        >
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            placeholder="e.g. staff@ddn.com"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Role Selection -->
                    <div class="space-y-2">
                        <Label for="role"
                            >Assigned Role
                            <span class="text-destructive">*</span></Label
                        >
                        <div class="grid grid-cols-1 gap-3">
                            <label
                                v-for="r in roles"
                                :key="r"
                                :class="[
                                    'flex cursor-pointer items-start gap-3 rounded-lg border p-3.5 transition-colors',
                                    form.role === r
                                        ? 'border-primary bg-primary/5 text-foreground'
                                        : 'border-border text-muted-foreground hover:bg-muted/40',
                                ]"
                            >
                                <input
                                    type="radio"
                                    name="role"
                                    :value="r"
                                    v-model="form.role"
                                    class="mt-1 h-4 w-4 text-primary"
                                />
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-semibold text-foreground capitalize"
                                        >
                                            {{ r.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {{ getRoleDescription(r) }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <InputError :message="form.errors.role" />
                    </div>

                    <!-- New Password (Optional) -->
                    <div class="space-y-2">
                        <Label for="password"
                            >New Password
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >(Leave blank to keep existing)</span
                            ></Label
                        >
                        <Input
                            id="password"
                            type="password"
                            v-model="form.password"
                            placeholder="Enter new password if changing"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Actions -->
                    <div
                        class="flex items-center justify-end gap-3 border-t pt-4"
                    >
                        <Button
                            as-child
                            variant="outline"
                            :disabled="form.processing"
                        >
                            <Link href="/users">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="gap-2"
                        >
                            <UserCheck class="h-4 w-4" />
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
