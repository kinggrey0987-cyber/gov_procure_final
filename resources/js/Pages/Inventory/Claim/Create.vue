<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">File Claim</h2>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 bg-white shadow-sm rounded p-6">
        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-gray-500">Distribution Record</label>
            <select v-model="form.distribution_record_id" class="w-full border rounded px-3 py-2">
              <option value="">Select</option>
              <option v-for="d in distributionRecords" :key="d.distribution_record_id" :value="d.distribution_record_id">{{ d.distribution_code }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-gray-500">Office ID</label>
            <input v-model="form.office_id" type="number" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="text-xs text-gray-500">Claim Type</label>
            <input v-model="form.claim_type" type="text" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="text-xs text-gray-500">Claim Date</label>
            <input v-model="form.claim_date" type="date" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs text-gray-500">Claim Location</label>
            <input v-model="form.claim_location" type="text" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs text-gray-500">Remarks</label>
            <textarea v-model="form.remarks" rows="3" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="md:col-span-2 flex gap-2">
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Submit</button>
            <Link :href="route(`${base}.claims.index`)" class="px-4 py-2 bg-gray-200 rounded">Cancel</Link>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

export default {
  components: { AuthenticatedLayout, Link },
  props: { distributionRecords: Array },
  data() {
    return {
      form: useForm({
        distribution_record_id: '',
        office_id: '',
        claim_type: '',
        claim_date: '',
        claim_location: '',
        remarks: '',
      }),
    };
  },
  computed: {
    base() {
      return this.$page.url.startsWith('/office') ? 'office' : 'inventory';
    },
  },
  methods: {
    submit() {
      this.form.post(route(`${this.base}.claims.store`));
    },
  },
};
</script>
