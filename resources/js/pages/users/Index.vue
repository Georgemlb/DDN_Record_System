<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Key,
    Pencil,
    Shield,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { User } from '@/types/auth';

defineProps<{
    users: User[];
    canManageTechAdmin: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'User Management',
                href: '/users',
            },
        ],
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);

const deletingUser = ref<User | null>(null);
const deleteDialogOpen = ref(false);
const isDeleting = ref(false);

const confirmDelete = (user: User) => {
    deletingUser.value = user;
    deleteDialogOpen.value = true;
};

const handleDelete = () => {
    if (!deletingUser.value) {
        return;
    }

    isDeleting.value = true;
    router.delete(`/users/${deletingUser.value.id}`, {
        onSuccess: () => {
            deleteDialogOpen.value = false;
            deletingUser.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const canEditUser = (targetUser: User): boolean => {
    if (!currentUser.value) {
        return false;
    }

    if (currentUser.value.role === 'tech_admin') {
        return true;
    }

    if (currentUser.value.role === 'admin') {
        return targetUser.role !== 'tech_admin';
    }

    return false;
};

const canDeleteUser = (targetUser: User): boolean => {
    if (!currentUser.value) {
        return false;
    }

    if (currentUser.value.id === targetUser.id) {
        return false;
    }

    if (currentUser.value.role === 'tech_admin') {
        return true;
    }

    if (currentUser.value.role === 'admin') {
        return targetUser.role !== 'tech_admin';
    }

    return false;
};

const getRoleBadgeVariant = (role: string) => {
    switch (role) {
        case 'tech_admin':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'admin':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        default:
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
    }
};

const getRoleLabel = (role: string) => {
    switch (role) {
        case 'tech_admin':
            return 'Tech Admin';
        case 'admin':
            return 'Admin';
        case 'staff':
            return 'Staff';
        default:
            return role;
    }
};
</script>

<template>
    <Head title="User Management" />

    <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    <Users class="h-7 w-7 text-primary" />
                    User Management
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manage team members, roles, credentials, and access
                    permissions.
                </p>
            </div>
            <div>
                <Button as-child class="gap-2 shadow-sm">
                    <Link href="/users/create">
                        <UserPlus class="h-4 w-4" />
                        Add New User
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Role Overview Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card
                class="border-purple-200/60 bg-purple-50/30 dark:border-purple-900/30 dark:bg-purple-950/10"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium text-purple-900 dark:text-purple-300"
                    >
                        <Shield
                            class="h-4 w-4 text-purple-600 dark:text-purple-400"
                        />
                        Tech Admin
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold text-purple-950 dark:text-purple-100"
                    >
                        {{
                            users.filter((u) => u.role === 'tech_admin').length
                        }}
                    </div>
                    <p class="text-xs text-purple-700/80 dark:text-purple-400">
                        Full access, backups, & tech management
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-blue-200/60 bg-blue-50/30 dark:border-blue-900/30 dark:bg-blue-950/10"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium text-blue-900 dark:text-blue-300"
                    >
                        <UserCheck
                            class="h-4 w-4 text-blue-600 dark:text-blue-400"
                        />
                        Business Admins
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold text-blue-950 dark:text-blue-100"
                    >
                        {{ users.filter((u) => u.role === 'admin').length }}
                    </div>
                    <p class="text-xs text-blue-700/80 dark:text-blue-400">
                        Reports, portal deposits, & configurations
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-emerald-200/60 bg-emerald-50/30 dark:border-emerald-900/30 dark:bg-emerald-950/10"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium text-emerald-900 dark:text-emerald-300"
                    >
                        <Key
                            class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                        />
                        Counter Staff
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold text-emerald-950 dark:text-emerald-100"
                    >
                        {{ users.filter((u) => u.role === 'staff').length }}
                    </div>
                    <p
                        class="text-xs text-emerald-700/80 dark:text-emerald-400"
                    >
                        Daily counter transactions & expenses
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Users Table -->
        <Card class="overflow-hidden border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b bg-muted/50 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-6 py-3.5">User</th>
                            <th class="px-6 py-3.5">Username</th>
                            <th class="px-6 py-3.5">Role</th>
                            <th class="px-6 py-3.5">Email</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <td class="px-6 py-4 font-medium text-foreground">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary"
                                    >
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div
                                            class="font-semibold text-foreground"
                                        >
                                            {{ user.name }}
                                            <span
                                                v-if="
                                                    user.id === currentUser?.id
                                                "
                                                class="ml-2 text-xs font-normal text-muted-foreground"
                                            >
                                                (You)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-6 py-4 font-mono text-sm text-muted-foreground"
                            >
                                @{{ user.username || '—' }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold shadow-xs',
                                        getRoleBadgeVariant(user.role),
                                    ]"
                                >
                                    {{ getRoleLabel(user.role) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ user.email || '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        v-if="canEditUser(user)"
                                        as-child
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1"
                                    >
                                        <Link :href="`/users/${user.id}/edit`">
                                            <Pencil class="h-3.5 w-3.5" />
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="canDeleteUser(user)"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="confirmDelete(user)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-destructive">
                    <AlertTriangle class="h-5 w-5" />
                    Delete User
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete
                    <span class="font-semibold text-foreground">{{
                        deletingUser?.name
                    }}</span>
                    (@{{ deletingUser?.username }})? This will revoke their
                    access to the system immediately.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button
                    variant="outline"
                    :disabled="isDeleting"
                    @click="deleteDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    :disabled="isDeleting"
                    @click="handleDelete"
                >
                    {{ isDeleting ? 'Deleting...' : 'Delete User' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
