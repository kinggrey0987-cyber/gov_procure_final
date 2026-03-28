<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="text-xl font-semibold text-gray-800">Distribution {{ record.distribution_code }}</h2>
        <div class="flex gap-2">
          <Link :href="route('inventory.distribution-records.edit', record.distribution_record_id)" class="px-4 py-2 bg-blue-600 text-white rounded">Edit</Link>
          <button v-if="record.status === 'RELEASED'" @click="receive" class="px-4 py-2 bg-green-600 text-white rounded">Mark Received</button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm rounded p-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div><p class="text-xs text-gray-500">Code</p><p>{{ record.distribution_code }}</p></div>
          <div><p class="text-xs text-gray-500">Status</p><p>{{ record.status }}</p></div>
          <div><p class="text-xs text-gray-500">Office</p><p>{{ record.office?.name || record.office_id }}</p></div>
          <div><p class="text-xs text-gray-500">Release Date</p><p>{{ record.release_date || '-' }}</p></div>
        </div>

        <div class="bg-white shadow-sm rounded p-6">
          <div class="flex justify-between mb-3">
            <h3 class="font-semibold">Distribution Items</h3>
            <Link :href="route('inventory.distribution-records.items.manage', record.distribution_record_id)" class="text-blue-600">Manage Items</Link>
          </div>
          <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="text-left py-2">Item</th><th class="text-right py-2">Qty</th><th class="text-left py-2">Unit</th></tr></thead>
            <tbody>
              <tr v-for="it in record.items" :key="it.distribution_item_id" class="border-b">
                <td class="py-2">{{ it.item_name_snapshot }}</td>
                <td class="py-2 text-right">{{ it.quantity_released }}</td>
                <td class="py-2">{{ it.unit }}</td>
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
import { Link, router } from '@inertiajs/vue3';

export default {
  components: { AuthenticatedLayout, Link },
  props: { record: Object },
  methods: {
    receive() {
      const base = this.$page.url.startsWith('/office') ? 'office' : 'inventory';
      router.post(route(`${base}.distribution-records.receive`, this.record.distribution_record_id));
    },
  },
};
</script>
