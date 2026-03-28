<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">Inventory</h2>
        <Link :href="route('inventory.inventory.index')" class="px-4 py-2 rounded bg-gray-100 text-gray-700 text-sm">Refresh</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left">Item ID</th>
                <th class="px-4 py-3 text-right">Available</th>
                <th class="px-4 py-3 text-right">On Hand</th>
                <th class="px-4 py-3 text-right">Reserved</th>
                <th class="px-4 py-3 text-right">Released</th>
                <th class="px-4 py-3 text-right">Threshold</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in inventories.data" :key="row.inventory_id" class="border-b">
                <td class="px-4 py-3">{{ row.item_id }}</td>
                <td class="px-4 py-3 text-right">{{ row.available_stock }}</td>
                <td class="px-4 py-3 text-right">{{ row.stock_on_hand }}</td>
                <td class="px-4 py-3 text-right">{{ row.reserved_quantity }}</td>
                <td class="px-4 py-3 text-right">{{ row.released_quantity }}</td>
                <td class="px-4 py-3 text-right">{{ row.low_stock_threshold }}</td>
                <td class="px-4 py-3 text-right">
                  <Link :href="route('inventory.inventory.show', row.inventory_id)" class="text-blue-600 hover:underline">View</Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

export default {
  components: { AuthenticatedLayout, Link },
  props: { inventories: Object },
};
</script>
