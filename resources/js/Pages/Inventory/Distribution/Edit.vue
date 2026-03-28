<template>
  <AuthenticatedLayout>
    <template #header><h2 class="text-xl font-semibold text-gray-800">Edit Distribution Record</h2></template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 bg-white shadow-sm rounded p-6">
        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-gray-500">Received By</label>
            <input v-model="form.received_by" type="number" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="text-xs text-gray-500">Release Date</label>
            <input v-model="form.release_date" type="date" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs text-gray-500">Storage Location</label>
            <input v-model="form.storage_location" type="text" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs text-gray-500">Notes</label>
            <textarea v-model="form.notes" class="w-full border rounded px-3 py-2" rows="3" />
          </div>
          <div class="md:col-span-2 flex gap-2">
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            <Link :href="route('inventory.distribution-records.show', record.distribution_record_id)" class="px-4 py-2 bg-gray-200 rounded">Back</Link>
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
  props: { record: Object },
  data() {
    return {
      form: useForm({
        received_by: this.record.received_by || '',
        release_date: this.record.release_date || '',
        storage_location: this.record.storage_location || '',
        notes: this.record.notes || '',
      }),
    };
  },
  methods: {
    submit() {
      this.form.put(route('inventory.distribution-records.update', this.record.distribution_record_id));
    },
  },
};
</script>
