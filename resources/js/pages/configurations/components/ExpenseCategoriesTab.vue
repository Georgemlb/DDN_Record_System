<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ExpenseCategory } from '@/types/expense';

defineProps<{
    expenseCategories: ExpenseCategory[];
}>();

const emit = defineEmits<{
    confirmDelete: [
        id: number,
        title: string,
        endpoint: string,
        description: string,
    ];
}>();

const isCategoryModalOpen = ref(false);
const editingCategoryId = ref<number | null>(null);

const categoryForm = useForm({
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const openCreateCategory = () => {
    editingCategoryId.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    isCategoryModalOpen.value = true;
};

const openEditCategory = (cat: ExpenseCategory) => {
    editingCategoryId.value = cat.id;
    categoryForm.clearErrors();
    categoryForm.name = cat.name;
    categoryForm.description = cat.description ?? '';
    categoryForm.sort_order = cat.sort_order ?? 0;
    categoryForm.is_active = cat.is_active ?? true;
    isCategoryModalOpen.value = true;
};

const submitCategory = () => {
    if (editingCategoryId.value) {
        categoryForm.put(
            `/configurations/expense-categories/${editingCategoryId.value}`,
            {
                onSuccess: () => {
                    isCategoryModalOpen.value = false;
                },
            },
        );
    } else {
        categoryForm.post('/configurations/expense-categories', {
            onSuccess: () => {
                isCategoryModalOpen.value = false;
            },
        });
    }
};
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    Expense Categories
                </h2>
                <p class="text-sm text-muted-foreground">
                    Categorize expenditures and disbursements for clear
                    financial reporting.
                </p>
            </div>
            <Button @click="openCreateCategory">
                <Plus class="mr-1.5 size-4" /> Add Category
            </Button>
        </div>

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-muted/50 text-left text-muted-foreground"
                        >
                            <th class="w-16 p-4 text-center font-medium">
                                Order
                            </th>
                            <th class="p-4 font-medium">Category Name</th>
                            <th class="p-4 font-medium">Description</th>
                            <th class="p-4 text-center font-medium">Status</th>
                            <th class="p-4 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="expenseCategories.length === 0">
                            <td
                                colspan="5"
                                class="p-8 text-center text-muted-foreground"
                            >
                                No expense categories configured yet.
                            </td>
                        </tr>
                        <tr
                            v-for="cat in expenseCategories"
                            :key="cat.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td
                                class="p-4 text-center align-middle font-mono text-xs text-muted-foreground"
                            >
                                #{{ cat.sort_order ?? 0 }}
                            </td>
                            <td
                                class="p-4 align-middle font-medium text-foreground"
                            >
                                {{ cat.name }}
                            </td>
                            <td class="p-4 align-middle text-muted-foreground">
                                {{ cat.description || '—' }}
                            </td>
                            <td class="p-4 text-center align-middle">
                                <Badge
                                    :variant="
                                        cat.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ cat.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </td>
                            <td class="p-4 text-right align-middle">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Edit"
                                        @click="openEditCategory(cat)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Delete"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="
                                            emit(
                                                'confirmDelete',
                                                cat.id,
                                                cat.name,
                                                '/configurations/expense-categories',
                                                'This expense category will be permanently removed if it is not associated with existing expenses.',
                                            )
                                        "
                                    >
                                        <Trash class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Dialog v-model:open="isCategoryModalOpen">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submitCategory">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingCategoryId
                                    ? 'Edit Expense Category'
                                    : 'Add Expense Category'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                editingCategoryId
                                    ? 'Update details for this expenditure category.'
                                    : 'Create a new category for tracking business expenses.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="space-y-1.5">
                            <Label for="category-name">Category Name</Label>
                            <Input
                                id="category-name"
                                v-model="categoryForm.name"
                                placeholder="e.g. Utilities"
                                required
                            />
                            <div
                                v-if="categoryForm.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ categoryForm.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="category-desc"
                                >Description (Optional)</Label
                            >
                            <Input
                                id="category-desc"
                                v-model="categoryForm.description"
                                placeholder="e.g. Electricity, water, and internet bills"
                            />
                            <div
                                v-if="categoryForm.errors.description"
                                class="text-xs text-destructive"
                            >
                                {{ categoryForm.errors.description }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="category-sort">Sort Order</Label>
                            <Input
                                id="category-sort"
                                type="number"
                                v-model="categoryForm.sort_order"
                            />
                            <div
                                v-if="categoryForm.errors.sort_order"
                                class="text-xs text-destructive"
                            >
                                {{ categoryForm.errors.sort_order }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="category-active"
                                :checked="categoryForm.is_active"
                                @update:checked="
                                    categoryForm.is_active = $event
                                "
                            />
                            <Label
                                for="category-active"
                                class="cursor-pointer font-normal"
                            >
                                Active (Visible in expense forms)
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCategoryModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="categoryForm.processing"
                        >
                            {{
                                editingCategoryId
                                    ? 'Save Changes'
                                    : 'Create Category'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
