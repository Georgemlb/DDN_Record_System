<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

const isOpen = defineModel<boolean>('isOpen', { default: false });

const emit = defineEmits<{
    (e: 'category-created', category: ExpenseCategory): void;
}>();

const name = ref('');
const description = ref('');
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const resetForm = () => {
    name.value = '';
    description.value = '';
    errors.value = {};
};

const handleOpenChange = (open: boolean) => {
    isOpen.value = open;

    if (!open) {
        resetForm();
    }
};

const submit = () => {
    errors.value = {};
    processing.value = true;

    router.post(
        '/expense-categories',
        {
            name: name.value,
            description: description.value,
        },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const newCategory = (page.props as Record<string, any>)?.flash
                    ?.new_category as ExpenseCategory | undefined;

                if (newCategory) {
                    emit('category-created', newCategory);
                }

                isOpen.value = false;
                resetForm();
            },
            onError: (errs) => {
                errors.value = errs;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add Expense Category</DialogTitle>
                <DialogDescription>
                    Create a new category for classifying business expenditures.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="category-name">
                        Category name <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="category-name"
                        v-model="name"
                        type="text"
                        placeholder="e.g., Software Subscriptions"
                        :disabled="processing"
                        autofocus
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="category-description"
                        >Description (optional)</Label
                    >
                    <Input
                        id="category-description"
                        v-model="description"
                        type="text"
                        placeholder="Brief note on what this category covers"
                        :disabled="processing"
                    />
                    <InputError :message="errors.description" />
                </div>

                <DialogFooter class="gap-2 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isOpen = false"
                        :disabled="processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing || !name.trim()"
                    >
                        {{ processing ? 'Saving...' : 'Add Category' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
