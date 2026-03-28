<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">Claim {{ claim.claim_code }}</h2>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm rounded p-6 text-sm space-y-2">
          <p><strong>Status:</strong> {{ claim.status }}</p>
          <p><strong>Type:</strong> {{ claim.claim_type }}</p>
          <p><strong>Claim Date:</strong> {{ claim.claim_date }}</p>
          <p><strong>Location:</strong> {{ claim.claim_location }}</p>
          <p><strong>Remarks:</strong> {{ claim.remarks || '-' }}</p>
        </div>

        <div v-if="$page.props.auth.user.role === 'GSO_OFFICER' && claim.status !== 'RESOLVED' && claim.status !== 'REJECTED'" class="bg-white shadow-sm rounded p-6">
          <h3 class="font-semibold mb-3">Update Claim Status</h3>
          <form @submit.prevent="submitStatus" class="flex gap-3 items-end">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Status</label>
              <select v-model="statusForm.status" class="border rounded px-3 py-2">
                <option value="IN_REVIEW">IN_REVIEW</option>
                <option value="RESOLVED">RESOLVED</option>
                <option value="REJECTED">REJECTED</option>
              </select>
            </div>
            <div class="flex-1">
              <label class="block text-xs text-gray-500 mb-1">Remarks</label>
              <input v-model="statusForm.remarks" type="text" class="w-full border rounded px-3 py-2" />
            </div>
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Update</button>
          </form>
        </div>

        <Link :href="route(`${base}.claims.index`)" class="inline-block px-4 py-2 bg-gray-200 rounded">Back to Claims</Link>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

export default {
  components: { AuthenticatedLayout, Link },
  props: { claim: Object },
  data() {
    return {
      statusForm: useForm({
        status: 'IN_REVIEW',
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
    submitStatus() {
      this.statusForm.put(route('inventory.claims.update-status', this.claim.claims_id));
    },
  },
};
</script>
