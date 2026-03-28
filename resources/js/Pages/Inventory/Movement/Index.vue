<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Inventory Movements</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Inventory</th>
                <th class="px-4 py-3 text-right">Quantity</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in movements.data" :key="row.movement_id" class="border-b">
                <td class="px-4 py-3">{{ row.movement_type }}</td>
                <td class="px-4 py-3">#{{ row.inventory_id }}</td>
                <td class="px-4 py-3 text-right">{{ row.quantity }}</td>
                <td class="px-4 py-3">{{ row.movement_date }}</td>
                <td class="px-4 py-3 text-right"><Link :href="route('inventory.inventory-movements.show', row.movement_id)" class="text-blue-600">View</Link></td>
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
  props: { movements: Object },
};
</script>
