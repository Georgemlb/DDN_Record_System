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
import type { BaggageOption } from '@/types/transaction';

defineProps<{
    baggageOptions: BaggageOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Settings',
                href: '/settings',
            },
            {
                title: 'Baggage Options',
                href: '/settings/baggage-options',
            },
        ],
    },
});

const isEditing = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    airline: '',
    label: '',
    weight_kg: 20,
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

const edit = (option: BaggageOption) => {
    isEditing.value = true;
    editingId.value = option.id;
    form.airline = option.airline ?? '';
    form.label = option.label;
    form.weight_kg = option.weight_kg;
    form.price = Number(option.price);
    form.sort_order = option.sort_order ?? 0;
    form.is_active = option.is_active ?? true;
};

const submit = () => {
    if (isEditing.value && editingId.value) {
        form.put(`/settings/baggage-options/${editingId.value}`, {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post('/settings/baggage-options', {
            onSuccess: () => resetForm(),
        });
    }
};

const destroy = (id: number) => {
    if (confirm('Are you sure you want to delete this option?')) {
        router.delete(`/settings/baggage-options/${id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Baggage Options" />

    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-medium">Baggage Options</h3>
            <p class="text-sm text-muted-foreground">
                Manage the baggage options available for transactions.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ isEditing ? 'Edit Option' : 'Add New Option' }}</CardTitle>
                <CardDescription>
                    {{ isEditing ? 'Update the details of the baggage option.' : 'Create a new baggage option for transactions.' }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="airline">Airline (Optional)</Label>
                            <Input
                                id="airline"
                                v-model="form.airline"
                                placeholder="e.g. Cebu Pacific (Leave empty for all)"
                            />
                            <div v-if="form.errors.airline" class="text-sm text-destructive">
                                {{ form.errors.airline }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="label">Label</Label>
                            <Input
                                id="label"
                                v-model="form.label"
                                placeholder="e.g. +20 KG"
                                required
                            />
                            <div v-if="form.errors.label" class="text-sm text-destructive">
                                {{ form.errors.label }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="weight_kg">Weight (KG)</Label>
                            <Input
                                id="weight_kg"
                                type="number"
                                v-model="form.weight_kg"
                                required
                            />
                            <div v-if="form.errors.weight_kg" class="text-sm text-destructive">
                                {{ form.errors.weight_kg }}
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
                            <th class="p-4 font-medium">Airline</th>
                            <th class="p-4 font-medium">Label</th>
                            <th class="p-4 font-medium text-right">Weight</th>
                            <th class="p-4 font-medium text-right">Price</th>
                            <th class="p-4 font-medium text-center">Status</th>
                            <th class="p-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="baggageOptions.length === 0">
                            <td colspan="6" class="p-8 text-center text-muted-foreground">
                                No baggage options found.
                            </td>
                        </tr>
                        <tr
                            v-for="option in baggageOptions"
                            :key="option.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-4 align-middle">
                                <span v-if="option.airline" class="font-medium">{{ option.airline }}</span>
                                <span v-else class="text-muted-foreground italic">Universal</span>
                            </td>
                            <td class="p-4 align-middle font-medium">{{ option.label }}</td>
                            <td class="p-4 align-middle text-right">{{ option.weight_kg }} KG</td>
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
