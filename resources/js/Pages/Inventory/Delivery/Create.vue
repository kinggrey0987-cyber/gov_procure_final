<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Create Delivery</h2>
    </template>

    <div class="py-12">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <form @submit.prevent="submitForm" class="p-6">
            <!-- Purchase Order Selection -->
            <div class="mb-6">
              <label for="purchase_order_id" class="block text-sm font-semibold text-gray-700 mb-2">
                Purchase Order <span class="text-red-600">*</span>
              </label>
              <select
                id="purchase_order_id"
                v-model="form.purchase_order_id"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select a Purchase Order</option>
                <option v-for="po in purchaseOrders" :key="po.purchase_order_id" :value="po.purchase_order_id">
                  {{ po.po_number }} - {{ po.creator?.full_name || 'Creator' }}
                </option>
              </select>
              <p v-if="errors.purchase_order_id" class="mt-1 text-sm text-red-600">{{ errors.purchase_order_id }}</p>
            </div>

            <!-- Supplier Selection -->
            <div class="mb-6">
              <label for="supplier_id" class="block text-sm font-semibold text-gray-700 mb-2">
                Supplier <span class="text-red-600">*</span>
              </label>
              <select
                id="supplier_id"
                v-model="form.supplier_id"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select a Supplier</option>
                <option v-for="supplier in purchaseOrders[selectedPoIndex]?.suppliers || []" :key="supplier.supplier_id" :value="supplier.supplier_id">
                  {{ supplier.supplier_name }}
                </option>
              </select>
              <p v-if="errors.supplier_id" class="mt-1 text-sm text-red-600">{{ errors.supplier_id }}</p>
            </div>

            <!-- Delivery Code -->
            <div class="mb-6">
              <label for="delivery_code" class="block text-sm font-semibold text-gray-700 mb-2">
                Delivery Code
              </label>
              <input
                id="delivery_code"
                v-model="form.delivery_code"
                type="text"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Leave empty for auto-generated code"
              />
              <p v-if="errors.delivery_code" class="mt-1 text-sm text-red-600">{{ errors.delivery_code }}</p>
            </div>

            <!-- Actual Delivery Date -->
            <div class="mb-6">
              <label for="actual_delivery_date" class="block text-sm font-semibold text-gray-700 mb-2">
                Actual Delivery Date
              </label>
              <input
                id="actual_delivery_date"
                v-model="form.actual_delivery_date"
                type="date"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p v-if="errors.actual_delivery_date" class="mt-1 text-sm text-red-600">{{ errors.actual_delivery_date }}</p>
            </div>

            <!-- Expected Arrival Date -->
            <div class="mb-6">
              <label for="expected_arrival_date" class="block text-sm font-semibold text-gray-700 mb-2">
                Expected Arrival Date
              </label>
              <input
                id="expected_arrival_date"
                v-model="form.expected_arrival_date"
                type="date"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p v-if="errors.expected_arrival_date" class="mt-1 text-sm text-red-600">{{ errors.expected_arrival_date }}</p>
            </div>

            <!-- Remarks -->
            <div class="mb-6">
              <label for="remarks" class="block text-sm font-semibold text-gray-700 mb-2">Remarks</label>
              <textarea
                id="remarks"
                v-model="form.remarks"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                rows="4"
              />
              <p v-if="errors.remarks" class="mt-1 text-sm text-red-600">{{ errors.remarks }}</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4">
              <button
                type="submit"
                :disabled="loading"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {{ loading ? 'Creating...' : 'Create Delivery' }}
              </button>
              <Link :href="route('inventory.deliveries.index')" class="px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">
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
  props: { purchaseOrders: Array },
  data() {
    return {
      form: useForm({
        purchase_order_id: '',
        supplier_id: '',
        delivery_code: '',
        actual_delivery_date: '',
        expected_arrival_date: '',
        remarks: '',
      }),
      loading: false,
      errors: {},
    };
  },
  computed: {
    selectedPoIndex() {
      return this.purchaseOrders.findIndex(po => po.purchase_order_id === parseInt(this.form.purchase_order_id));
    },
  },
  methods: {
    async submitForm() {
      this.loading = true;
      this.form.post(route('inventory.deliveries.store'), {
        onError: (errors) => {
          this.errors = errors;
        },
      });
    },
  },
};
</script>
