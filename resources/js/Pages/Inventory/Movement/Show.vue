<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Movement #{{ movement.movement_id }}</h2>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm">
          <p><strong>Type:</strong> {{ movement.movement_type }}</p>
          <p><strong>Inventory ID:</strong> {{ movement.inventory_id }}</p>
          <p><strong>Reference:</strong> {{ movement.reference_type }} #{{ movement.reference_id }}</p>
          <p><strong>Quantity:</strong> {{ movement.quantity }}</p>
          <p><strong>Date:</strong> {{ movement.movement_date }}</p>
          <p><strong>Remarks:</strong> {{ movement.remarks || '-' }}</p>
          <Link :href="route('inventory.inventory-movements.index')" class="inline-block mt-3 px-4 py-2 bg-gray-200 rounded">Back</Link>
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
  props: { movement: Object },
};
</script>
