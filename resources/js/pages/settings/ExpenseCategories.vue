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
import type { ExpenseCategory } from '@/types/expense';

defineProps<{
    expenseCategories: ExpenseCategory[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Settings',
                href: '/settings',
            },
            {
                title: 'Expense Categories',
                href: '/settings/expense-categories',
            },
        ],
    },
});

const isEditing = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const resetForm = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const edit = (category: ExpenseCategory) => {
    isEditing.value = true;
    editingId.value = category.id;
    form.name = category.name;
    form.description = category.description ?? '';
    form.sort_order = category.sort_order ?? 0;
    form.is_active = category.is_active ?? true;
};

const submit = () => {
    if (isEditing.value && editingId.value) {
        form.put(`/settings/expense-categories/${editingId.value}`, {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post('/settings/expense-categories', {
            onSuccess: () => resetForm(),
        });
    }
};

const destroy = (id: number) => {
    if (confirm('Are you sure you want to delete this category?')) {
        router.delete(`/settings/expense-categories/${id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Expense Categories" />

    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-medium">Expense Categories</h3>
            <p class="text-sm text-muted-foreground">
                Manage the categories available for expenses.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ isEditing ? 'Edit Category' : 'Add New Category' }}</CardTitle>
                <CardDescription>
                    {{ isEditing ? 'Update the details of the expense category.' : 'Create a new expense category.' }}
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
                                placeholder="e.g. Utilities"
                                required
                            />
                            <div v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description (Optional)</Label>
                            <Input
                                id="description"
                                v-model="form.description"
                                placeholder="e.g. Office electricity and water bills"
                            />
                            <div v-if="form.errors.description" class="text-sm text-destructive">
                                {{ form.errors.description }}
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
                            {{ isEditing ? 'Save Changes' : 'Create Category' }}
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
                            <th class="p-4 font-medium">Description</th>
                            <th class="p-4 font-medium text-center">Status</th>
                            <th class="p-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="expenseCategories.length === 0">
                            <td colspan="4" class="p-8 text-center text-muted-foreground">
                                No expense categories found.
                            </td>
                        </tr>
                        <tr
                            v-for="category in expenseCategories"
                            :key="category.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-4 align-middle font-medium">{{ category.name }}</td>
                            <td class="p-4 align-middle text-muted-foreground">{{ category.description }}</td>
                            <td class="p-4 align-middle text-center">
                                <Badge :variant="category.is_active ? 'default' : 'secondary'">
                                    {{ category.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </td>
                            <td class="p-4 align-middle text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Edit"
                                        @click="edit(category)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Delete"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="destroy(category.id)"
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
