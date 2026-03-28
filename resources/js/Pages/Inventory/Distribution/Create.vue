<template>
  <AuthenticatedLayout>
    <template #header><h2 class="text-xl font-semibold text-gray-800">Create Distribution Record</h2></template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 bg-white shadow-sm rounded p-6">
        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-gray-500">Supply Request</label>
            <select v-model="form.supply_request_id" class="w-full border rounded px-3 py-2">
              <option value="">Select</option>
              <option v-for="sr in supplyRequests" :key="sr.supply_request_id" :value="sr.supply_request_id">{{ sr.request_code }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-gray-500">Delivery</label>
            <select v-model="form.delivery_id" class="w-full border rounded px-3 py-2">
              <option value="">Select</option>
              <option v-for="d in deliveries" :key="d.delivery_id" :value="d.delivery_id">{{ d.delivery_code }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-gray-500">Office ID</label>
            <input v-model="form.office_id" type="number" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="text-xs text-gray-500">Storage Location</label>
            <input v-model="form.storage_location" type="text" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs text-gray-500">Notes</label>
            <textarea v-model="form.notes" class="w-full border rounded px-3 py-2" rows="3" />
          </div>
          <div class="md:col-span-2 flex gap-2">
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            <Link :href="route('inventory.distribution-records.index')" class="px-4 py-2 bg-gray-200 rounded">Cancel</Link>
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
  props: { supplyRequests: Array, deliveries: Array },
  data() {
    return {
      form: useForm({
        supply_request_id: '',
        delivery_id: '',
        office_id: '',
        storage_location: '',
        notes: '',
      }),
    };
  },
  methods: {
    submit() {
      this.form.post(route('inventory.distribution-records.store'));
    },
  },
};
</script>
