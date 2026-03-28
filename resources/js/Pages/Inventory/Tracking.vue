<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Inventory and Distribution Tracking</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white shadow-sm rounded p-4">
          <p class="text-xs text-gray-500">Inventory Records</p>
          <p class="text-2xl font-semibold">{{ summary.inventory_count }}</p>
        </div>
        <div class="bg-white shadow-sm rounded p-4">
          <p class="text-xs text-gray-500">Low Stock</p>
          <p class="text-2xl font-semibold text-orange-600">{{ summary.low_stock_count }}</p>
        </div>
        <div class="bg-white shadow-sm rounded p-4">
          <p class="text-xs text-gray-500">Distribution Released</p>
          <p class="text-2xl font-semibold text-blue-600">{{ summary.released_distribution_count }}</p>
        </div>
        <div class="bg-white shadow-sm rounded p-4">
          <p class="text-xs text-gray-500">Open Claims</p>
          <p class="text-2xl font-semibold text-red-600">{{ summary.open_claim_count }}</p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

export default {
  components: { AuthenticatedLayout },
  props: { summary: Object },
};
</script>
