<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="text-xl font-semibold text-gray-800">Claims</h2>
        <Link :href="route(`${base}.claims.create`)" class="px-4 py-2 bg-blue-600 text-white rounded">File Claim</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 bg-white shadow-sm rounded overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left">Code</th>
              <th class="px-4 py-3 text-left">Type</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Office</th>
              <th class="px-4 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in claims.data" :key="row.claims_id" class="border-b">
              <td class="px-4 py-3">{{ row.claim_code }}</td>
              <td class="px-4 py-3">{{ row.claim_type }}</td>
              <td class="px-4 py-3">{{ row.status }}</td>
              <td class="px-4 py-3">{{ row.office?.name || row.office_id }}</td>
              <td class="px-4 py-3 text-right"><Link :href="route(`${base}.claims.show`, row.claims_id)" class="text-blue-600">View</Link></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

export default {
  components: { AuthenticatedLayout, Link },
  props: { claims: Object },
  computed: {
    base() {
      return this.$page.url.startsWith('/office') ? 'office' : 'inventory';
    },
  },
};
</script>
