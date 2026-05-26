<?php

namespace App\Http\Requests\Office;

use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\SupplyRequestItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('office.supply-request.create') ?? false;
    }

    public function rules(): array
    {
        $officeId = (int) $this->user()?->office_id;
        $ppmpId = (int) $this->input('ppmp_id');

        return [
            'ppmp_id' => [
                'required',
                'integer',
                Rule::exists('ppmp', 'ppmp_id')->where(function ($query) use ($officeId): void {
                    $query->where('office_id', $officeId);
                }),
            ],
            'needed_by_date' => ['required', 'date', 'after_or_equal:today'],
            'request_category' => ['required', 'string', 'max:50'],
            'purpose' => ['required', 'string'],
            'priority_level' => ['required', 'string', 'max:30'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', 'integer'],
            'items.*.ppmp_item_id' => [
                'required_with:items',
                'integer',
                Rule::exists('ppmp_items', 'ppmp_item_id')->where(function ($query) use ($ppmpId): void {
                    if ($ppmpId > 0) {
                        $query->where('ppmp_id', $ppmpId);
                    }
                }),
            ],
            'items.*.item_name_snapshot' => ['nullable', 'string', 'max:200'],
            'items.*.quantity_requested' => ['required_with:items', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ppmpId = (int) $this->input('ppmp_id');
            $officeId = (int) $this->user()?->office_id;
            $items = collect($this->input('items', []));

            if ($ppmpId > 0) {
                $ppmpIsApproved = Ppmp::query()
                    ->where('ppmp_id', $ppmpId)
                    ->where('office_id', $officeId)
                    ->where('status', 'APPROVED')
                    ->exists();

                if (! $ppmpIsApproved) {
                    $validator->errors()->add('ppmp_id', 'Selected PPMP is not approved and cannot be used for a supply request.');
                    return;
                }
            }

            if ($ppmpId <= 0 || $items->isEmpty()) {
                return;
            }

            $ppmpItemIds = $items
                ->pluck('ppmp_item_id')
                ->filter(fn ($value): bool => is_numeric($value))
                ->map(fn ($value): int => (int) $value)
                ->values();

            $duplicatePpmpItemIds = $ppmpItemIds
                ->duplicates()
                ->unique()
                ->values();

            if ($duplicatePpmpItemIds->isNotEmpty()) {
                foreach ($items as $index => $row) {
                    if ($duplicatePpmpItemIds->contains((int) ($row['ppmp_item_id'] ?? 0))) {
                        $validator->errors()->add("items.$index.ppmp_item_id", 'This PPMP item line is already included. Update the existing row quantity instead.');
                    }
                }
            }

            if ($ppmpItemIds->isEmpty()) {
                return;
            }

            $ppmpItems = PpmpItem::query()
                ->with('item:item_id,item_name,status')
                ->where('ppmp_id', $ppmpId)
                ->whereIn('ppmp_item_id', $ppmpItemIds->all())
                ->get(['ppmp_item_id', 'ppmp_id', 'item_id', 'quantity'])
                ->keyBy('ppmp_item_id');

            foreach ($items as $index => $row) {
                $ppmpItemId = (int) ($row['ppmp_item_id'] ?? 0);
                $payloadItemId = isset($row['item_id']) && is_numeric($row['item_id']) ? (int) $row['item_id'] : null;

                /** @var PpmpItem|null $ppmpItem */
                $ppmpItem = $ppmpItems->get($ppmpItemId);
                if (! $ppmpItem) {
                    $validator->errors()->add("items.$index.ppmp_item_id", 'Selected PPMP item does not belong to the selected PPMP.');
                    continue;
                }

                if ($payloadItemId !== null && $payloadItemId !== (int) $ppmpItem->item_id) {
                    $validator->errors()->add("items.$index.item_id", 'Selected item must match the chosen PPMP item.');
                }

                $itemStatus = $ppmpItem->item?->status;
                if ($itemStatus !== null && (string) $itemStatus !== 'active') {
                    $validator->errors()->add("items.$index.ppmp_item_id", 'This item is currently inactive and cannot be requested.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one complete supply request item row is required.',
            'items.min' => 'At least one complete supply request item row is required.',
            'purpose.required' => 'Purpose is required before saving a supply request.',
            'items.*.ppmp_item_id.required_with' => 'Select a PPMP item for each request row.',
            'items.*.quantity_requested.required_with' => 'Quantity is required for each request row.',
        ];
    }
}
