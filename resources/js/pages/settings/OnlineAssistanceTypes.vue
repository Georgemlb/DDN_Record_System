<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Trash } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPhp } from '@/lib/transactionFormat';
import type { OnlineAssistanceType } from '@/types/transaction';

defineProps<{
    onlineAssistanceTypes: OnlineAssistanceType[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Settings',
                href: '/settings',
            },
            {
                title: 'Online Assistance Types',
                href: '/settings/online-assistance-types',
            },
        ],
    },
});

const isEditing = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    price: 0,
    sort_order: 0,
    is_active: true,
});

const resetForm = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const edit = (option: OnlineAssistanceType) => {
    isEditing.value = true;
    editingId.value = option.id;
    form.name = option.name;
    form.price = Number(option.price);
    form.sort_order = option.sort_order ?? 0;
    form.is_active = option.is_active ?? true;
};

const submit = () => {
    if (isEditing.value && editingId.value) {
        form.put(`/settings/online-assistance-types/${editingId.value}`, {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post('/settings/online-assistance-types', {
            onSuccess: () => resetForm(),
        });
    }
};

const destroy = (id: number) => {
    if (confirm('Are you sure you want to delete this option?')) {
        router.delete(`/settings/online-assistance-types/${id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Online Assistance Types" />

    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-medium">Online Assistance Types</h3>
            <p class="text-sm text-muted-foreground">
                Manage the online assistance options available for transactions.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ isEditing ? 'Edit Option' : 'Add New Option' }}</CardTitle>
                <CardDescription>
                    {{ isEditing ? 'Update the details of the online assistance type.' : 'Create a new online assistance type for transactions.' }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="e.g. Visa Processing"
                                required
                            />
                            <div v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="price">Price (₱)</Label>
                            <Input
                                id="price"
                                type="number"
                                step="0.01"
                                v-model="form.price"
                                required
                            />
                            <div v-if="form.errors.price" class="text-sm text-destructive">
                                {{ form.errors.price }}
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="sort_order">Sort Order</Label>
                            <Input
                                id="sort_order"
                                type="number"
                                v-model="form.sort_order"
                                required
                            />
                            <div v-if="form.errors.sort_order" class="text-sm text-destructive">
                                {{ form.errors.sort_order }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <Checkbox
                            id="is_active"
                            :checked="form.is_active"
                            @update:checked="form.is_active = $event"
                        />
                        <Label for="is_active" class="font-normal">
                            Active (Visible in dropdowns)
                        </Label>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ isEditing ? 'Save Changes' : 'Create Option' }}
                        </Button>
                        <Button
                            v-if="isEditing"
                            type="button"
                            variant="outline"
                            @click="resetForm"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left text-muted-foreground">
                            <th class="p-4 font-medium">Name</th>
                            <th class="p-4 font-medium text-right">Price</th>
                            <th class="p-4 font-medium text-center">Status</th>
                            <th class="p-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="onlineAssistanceTypes.length === 0">
                            <td colspan="4" class="p-8 text-center text-muted-foreground">
                                No online assistance types found.
                            </td>
                        </tr>
                        <tr
                            v-for="option in onlineAssistanceTypes"
                            :key="option.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-4 align-middle font-medium">{{ option.name }}</td>
                            <td class="p-4 align-middle text-right">{{ formatPhp(option.price) }}</td>
                            <td class="p-4 align-middle text-center">
                                <Badge :variant="option.is_active ? 'default' : 'secondary'">
                                    {{ option.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </td>
                            <td class="p-4 align-middle text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Edit"
                                        @click="edit(option)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Delete"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="destroy(option.id)"
                                    >
                                        <Trash class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>
