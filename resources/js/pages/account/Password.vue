<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Change Password', href: '/account/password' }],
    },
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/account/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Change Password" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="mx-auto w-full max-w-xl">
            <Card class="border-border/80 shadow-xs">
                <CardHeader>
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <KeyRound class="size-4" />
                        </span>
                        <div>
                            <CardTitle>Change Password</CardTitle>
                            <CardDescription>
                                Keep your account secure by using a strong,
                                unique password.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="grid gap-2">
                            <Label for="current_password"
                                >Current Password</Label
                            >
                            <PasswordInput
                                id="current_password"
                                v-model="form.current_password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter current password"
                            />
                            <InputError
                                :message="form.errors.current_password"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">New Password</Label>
                            <PasswordInput
                                id="password"
                                v-model="form.password"
                                required
                                autocomplete="new-password"
                                placeholder="Enter new password"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation"
                                >Confirm New Password</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="Confirm new password"
                            />
                            <InputError
                                :message="form.errors.password_confirmation"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">
                                <Spinner v-if="form.processing" />
                                Update Password
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </main>
</template>
