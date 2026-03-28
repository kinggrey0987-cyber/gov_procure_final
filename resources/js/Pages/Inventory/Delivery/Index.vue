<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">Deliveries</h2>
        <Link
          v-if="$page.props.auth.user.role === 'GSO_OFFICER'"
          :href="route('inventory.deliveries.create')"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Create Delivery
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="px-6 py-3 text-left font-semibold text-gray-700">Code</th>
                  <th class="px-6 py-3 text-left font-semibold text-gray-700">PO</th>
                  <th class="px-6 py-3 text-left font-semibold text-gray-700">Supplier</th>
                  <th class="px-6 py-3 text-left font-semibold text-gray-700">Receipt Status</th>
                  <th class="px-6 py-3 text-left font-semibold text-gray-700">Received By</th>
                  <th class="px-6 py-3 text-left font-semibold text-gray-700">Date</th>
                  <th class="px-6 py-3 text-right font-semibold text-gray-700">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="delivery in deliveries.data" :key="delivery.delivery_id" class="border-b hover:bg-gray-50">
                  <td class="px-6 py-4 font-mono text-sm">{{ delivery.delivery_code }}</td>
                  <td class="px-6 py-4">{{ delivery.purchase_order.po_number }}</td>
                  <td class="px-6 py-4">{{ delivery.supplier.supplier_name }}</td>
                  <td class="px-6 py-4">
                    <span :class="getStatusBadgeClass(delivery.receipt_status)">
                      {{ delivery.receipt_status }}
                    </span>
                  </td>
                  <td class="px-6 py-4">{{ delivery.receiver.full_name }}</td>
                  <td class="px-6 py-4 text-xs text-gray-600">
                    {{ new Date(delivery.created_at).toLocaleDateString() }}
                  </td>
                  <td class="px-6 py-4 text-right">
                    <Link
                      :href="route('inventory.deliveries.show', delivery.delivery_id)"
                      class="text-blue-600 hover:text-blue-900"
                    >
                      View
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Pagination -->
            <div class="mt-6 flex justify-center">
              <div class="flex gap-2">
                <Link
                  v-for="link in deliveries.links"
                  :key="link.url"
                  :href="link.url"
                  :class="[
                    'px-3 py-2 rounded text-sm',
                    link.active
                      ? 'bg-blue-600 text-white'
                      : 'bg-gray-100 text-gray-800 hover:bg-gray-200',
                    !link.url ? 'opacity-50 cursor-not-allowed' : '',
                  ]"
                  v-html="link.label"
                />
              </div>
            </div>
          </div>
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
  props: { deliveries: Object },
  methods: {
    getStatusBadgeClass(status) {
      const classes = 'px-2 py-1 rounded text-xs font-semibold ';
      const statusClasses = {
        PENDING: classes + 'bg-yellow-100 text-yellow-800',
        RECEIVED: classes + 'bg-blue-100 text-blue-800',
        VERIFIED: classes + 'bg-green-100 text-green-800',
        PARTIALLY_RECEIVED: classes + 'bg-orange-100 text-orange-800',
      };
      return statusClasses[status] || classes + 'bg-gray-100 text-gray-800';
    },
  },
};
</script>
