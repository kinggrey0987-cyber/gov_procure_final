<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">Delivery {{ delivery.delivery_code }}</h2>
        <div class="flex gap-2">
          <Link
            v-if="$page.props.auth.user.role === 'GSO_OFFICER' && delivery.receipt_status === 'RECEIVED'"
            :href="route('inventory.delivery-verifications.create', delivery.delivery_id)"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            Verify Delivery
          </Link>
          <Link
            v-if="$page.props.auth.user.role === 'GSO_OFFICER' && delivery.receipt_status !== 'VERIFIED'"
            :href="route('inventory.deliveries.edit', delivery.delivery_id)"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            Edit
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header Info -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <p class="text-xs text-gray-600 mb-1">Delivery Code</p>
              <p class="text-lg font-semibold">{{ delivery.delivery_code }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-600 mb-1">Status</p>
              <span :class="getStatusBadgeClass(delivery.receipt_status)">
                {{ delivery.receipt_status }}
              </span>
            </div>
            <div>
              <p class="text-xs text-gray-600 mb-1">Received By</p>
              <p class="text-sm">{{ delivery.receiver.full_name }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-600 mb-1">Created</p>
              <p class="text-sm">{{ new Date(delivery.created_at).toLocaleDateString() }}</p>
            </div>
          </div>
        </div>

        <!-- Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <!-- PO Info -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 border-b border-gray-200">
              <h3 class="font-semibold text-gray-800 mb-4">Purchase Order</h3>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">PO Number:</span>
                  <Link :href="route('procurement.purchase-orders.show', delivery.purchase_order.purchase_order_id)" class="text-blue-600 hover:underline">
                    {{ delivery.purchase_order.po_number }}
                  </Link>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Status:</span>
                  <span>{{ delivery.purchase_order.status }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Supplier Info -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 border-b border-gray-200">
              <h3 class="font-semibold text-gray-800 mb-4">Supplier</h3>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">Name:</span>
                  <span>{{ delivery.supplier.supplier_name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Contact:</span>
                  <span>{{ delivery.supplier.contact_person || 'N/A' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Delivery Items -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-semibold text-gray-800">Delivery Items</h3>
              <button
                v-if="$page.props.auth.user.role === 'GSO_OFFICER' && delivery.receipt_status === 'PENDING'"
                @click="showAddItemModal = true"
                class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Add Item
              </button>
            </div>

            <table class="w-full text-sm">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="px-4 py-2 text-left">Item</th>
                  <th class="px-4 py-2 text-center">Expected</th>
                  <th class="px-4 py-2 text-center">Received</th>
                  <th class="px-4 py-2 text-center">Damaged</th>
                  <th class="px-4 py-2 text-center">Missing</th>
                  <th class="px-4 py-2 text-left">Condition</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in delivery.items" :key="item.delivery_item_id" class="border-b hover:bg-gray-50">
                  <td class="px-4 py-2">{{ item.item.item_name }}</td>
                  <td class="px-4 py-2 text-center">{{ item.expected_quantity }}</td>
                  <td class="px-4 py-2 text-center font-semibold">{{ item.received_quantity }}</td>
                  <td class="px-4 py-2 text-center text-red-600">{{ item.damaged_quantity }}</td>
                  <td class="px-4 py-2 text-center text-orange-600">{{ item.missing_quantity }}</td>
                  <td class="px-4 py-2">{{ item.item_condition || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Verifications -->
        <div v-if="delivery.verifications.length" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Verifications</h3>
            <div class="space-y-4">
              <div v-for="verification in delivery.verifications" :key="verification.delivery_verification_id" class="p-4 border border-gray-200 rounded">
                <div class="flex items-center justify-between mb-2">
                  <span class="font-semibold">{{ verification.verifier.full_name }}</span>
                  <span :class="getVerificationStatusClass(verification.verification_status)">
                    {{ verification.verification_status }}
                  </span>
                </div>
                <p class="text-xs text-gray-600 mb-2">{{ new Date(verification.verification_date).toLocaleDateString() }}</p>
                <p v-if="verification.discrepancy_notes" class="text-sm text-gray-700">{{ verification.discrepancy_notes }}</p>
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
  props: { delivery: Object },
  data() {
    return {
      showAddItemModal: false,
    };
  },
  methods: {
    getStatusBadgeClass(status) {
      const classes = 'px-2 py-1 rounded text-xs font-semibold ';
      const statusClasses = {
        PENDING: classes + 'bg-yellow-100 text-yellow-800',
        RECEIVED: classes + 'bg-blue-100 text-blue-800',
        VERIFIED: classes + 'bg-green-100 text-green-800',
      };
      return statusClasses[status] || classes + 'bg-gray-100 text-gray-800';
    },
    getVerificationStatusClass(status) {
      const classes = 'px-2 py-1 rounded text-xs font-semibold ';
      const statusClasses = {
        APPROVED: classes + 'bg-green-100 text-green-800',
        REJECTED: classes + 'bg-red-100 text-red-800',
      };
      return statusClasses[status] || classes + 'bg-gray-100 text-gray-800';
    },
  },
};
</script>
