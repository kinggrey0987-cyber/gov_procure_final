<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Inventory #{{ inventory.inventory_id }}</h2>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
          <div><p class="text-xs text-gray-500">Item ID</p><p class="font-semibold">{{ inventory.item_id }}</p></div>
          <div><p class="text-xs text-gray-500">Available</p><p class="font-semibold">{{ inventory.available_stock }}</p></div>
          <div><p class="text-xs text-gray-500">On Hand</p><p class="font-semibold">{{ inventory.stock_on_hand }}</p></div>
          <div><p class="text-xs text-gray-500">Threshold</p><p class="font-semibold">{{ inventory.low_stock_threshold }}</p></div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <h3 class="font-semibold mb-3">Update Low Stock Threshold</h3>
          <form @submit.prevent="submit" class="flex gap-3 items-end">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Threshold</label>
              <input v-model="form.low_stock_threshold" type="number" step="0.01" min="0" class="border rounded px-3 py-2" />
            </div>
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            <Link :href="route('inventory.inventory.index')" class="px-4 py-2 bg-gray-200 rounded">Back</Link>
          </form>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <h3 class="font-semibold mb-3">Recent Movements</h3>
          <ul class="space-y-2 text-sm">
            <li v-for="mv in inventory.movements" :key="mv.movement_id" class="border rounded px-3 py-2">
              {{ mv.movement_type }} • Qty {{ mv.quantity }} • {{ mv.movement_date }}
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
  props: { inventory: Object },
  data() {
    return {
      form: useForm({
        low_stock_threshold: this.inventory.low_stock_threshold,
      }),
    };
  },
  methods: {
    submit() {
      this.form.put(route('inventory.inventory.update', this.inventory.inventory_id));
    },
  },
};
</script>
