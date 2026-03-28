<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Verify Delivery {{ delivery.delivery_code }}</h2>
    </template>

    <div class="py-12">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <form @submit.prevent="submitForm" class="p-6">
            <!-- Verification Date -->
            <div class="mb-6">
              <label for="verification_date" class="block text-sm font-semibold text-gray-700 mb-2">
                Verification Date <span class="text-red-600">*</span>
              </label>
              <input
                id="verification_date"
                v-model="form.verification_date"
                type="date"
                required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p v-if="errors.verification_date" class="mt-1 text-sm text-red-600">{{ errors.verification_date }}</p>
            </div>

            <!-- Verification Status -->
            <div class="mb-6">
              <label for="verification_status" class="block text-sm font-semibold text-gray-700 mb-2">
                Verification Status <span class="text-red-600">*</span>
              </label>
              <select
                id="verification_status"
                v-model="form.verification_status"
                required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select Status</option>
                <option value="APPROVED">Approved</option>
                <option value="REJECTED">Rejected</option>
              </select>
              <p v-if="errors.verification_status" class="mt-1 text-sm text-red-600">{{ errors.verification_status }}</p>
            </div>

            <!-- Discrepancy Notes -->
            <div class="mb-6">
              <label for="discrepancy_notes" class="block text-sm font-semibold text-gray-700 mb-2">
                Discrepancy Notes
              </label>
              <textarea
                id="discrepancy_notes"
                v-model="form.discrepancy_notes"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                rows="4"
                placeholder="Note any discrepancies in the delivery..."
              />
              <p v-if="errors.discrepancy_notes" class="mt-1 text-sm text-red-600">{{ errors.discrepancy_notes }}</p>
            </div>

            <!-- Remarks -->
            <div class="mb-6">
              <label for="remarks" class="block text-sm font-semibold text-gray-700 mb-2">Remarks</label>
              <textarea
                id="remarks"
                v-model="form.remarks"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                rows="3"
              />
              <p v-if="errors.remarks" class="mt-1 text-sm text-red-600">{{ errors.remarks }}</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4">
              <button
                type="submit"
                :disabled="loading"
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
              >
                {{ loading ? 'Verifying...' : 'Verify Delivery' }}
              </button>
              <Link :href="route('inventory.deliveries.show', delivery.delivery_id)" class="px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">
                Cancel
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

export default {
  components: { AuthenticatedLayout, Link },
  props: { delivery: Object },
  data() {
    return {
      form: useForm({
        verification_date: new Date().toISOString().split('T')[0],
        verification_status: '',
        discrepancy_notes: '',
        remarks: '',
      }),
      loading: false,
      errors: {},
    };
  },
  methods: {
    async submitForm() {
      this.loading = true;
      this.form.post(route('inventory.delivery-verifications.store', this.delivery.delivery_id), {
        onError: (errors) => {
          this.errors = errors;
          this.loading = false;
        },
      });
    },
  },
};
</script>
