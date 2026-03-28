<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Manage Distribution Items</h2>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm rounded p-6">
          <h3 class="font-semibold mb-3">Add Item</h3>
          <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input v-model="form.item_id" type="number" placeholder="Item ID" class="border rounded px-3 py-2" />
            <input v-model="form.item_name_snapshot" type="text" placeholder="Item Name Snapshot" class="border rounded px-3 py-2" />
            <input v-model="form.quantity_released" type="number" step="0.01" min="0.01" placeholder="Quantity Released" class="border rounded px-3 py-2" />
            <input v-model="form.unit" type="text" placeholder="Unit" class="border rounded px-3 py-2" />
            <textarea v-model="form.remarks" rows="2" placeholder="Remarks" class="md:col-span-2 border rounded px-3 py-2" />
            <div class="md:col-span-2 flex gap-2">
              <button class="px-4 py-2 bg-blue-600 text-white rounded">Add</button>
              <Link :href="route('inventory.distribution-records.show', record.distribution_record_id)" class="px-4 py-2 bg-gray-200 rounded">Back</Link>
            </div>
          </form>
        </div>

        <div class="bg-white shadow-sm rounded p-6">
          <h3 class="font-semibold mb-3">Current Items</h3>
          <ul class="space-y-2 text-sm">
            <li v-for="it in record.items" :key="it.distribution_item_id" class="border rounded px-3 py-2">
              {{ it.item_name_snapshot }} • {{ it.quantity_released }} {{ it.unit }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

export default {
  components: { AuthenticatedLayout, Link },
  props: { record: Object },
  data() {
    return {
      form: useForm({
        item_id: '',
        item_name_snapshot: '',
        quantity_released: '',
        unit: '',
        remarks: '',
      }),
    };
  },
  methods: {
    submit() {
      this.form.post(route('inventory.distribution-records.items.store', this.record.distribution_record_id));
    },
  },
};
</script>
