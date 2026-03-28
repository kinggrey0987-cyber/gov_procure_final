<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="text-xl font-semibold text-gray-800">Distribution Records</h2>
        <Link :href="route('inventory.distribution-records.create')" class="px-4 py-2 bg-blue-600 text-white rounded">Create</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left">Code</th>
                <th class="px-4 py-3 text-left">Office</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Release Date</th>
                <th class="px-4 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in records.data" :key="row.distribution_record_id" class="border-b">
                <td class="px-4 py-3">{{ row.distribution_code }}</td>
                <td class="px-4 py-3">{{ row.office?.name || row.office_id }}</td>
                <td class="px-4 py-3">{{ row.status }}</td>
                <td class="px-4 py-3">{{ row.release_date || '-' }}</td>
                <td class="px-4 py-3 text-right"><Link :href="route('inventory.distribution-records.show', row.distribution_record_id)" class="text-blue-600">View</Link></td>
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
  props: { records: Object },
};
</script>
