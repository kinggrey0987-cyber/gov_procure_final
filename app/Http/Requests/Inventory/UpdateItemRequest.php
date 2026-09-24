<?php

namespace App\Http\Requests\Inventory;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('default_unit')) {
            $this->merge([
                'default_unit' => strtolower(trim((string) $this->input('default_unit'))),
            ]);
        }

        if ($this->has('specification') && ! $this->has('specifications')) {
            $spec = trim((string) $this->input('specification'));
            $this->merge([
                'specifications' => $spec !== '' ? [$spec] : [],
            ]);
        }
    }

    public function authorize(): bool
    {
        return auth()->user()?->can('inventory.catalog.manage') ?? false;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:200'],
            'item_category_id' => ['required', 'integer', 'exists:item_categories,item_category_id'],
            'description' => ['nullable', 'string'],
            'default_unit' => ['required', 'string', 'max:50', Rule::exists('item_units', 'name')->where('status', 'active')],
            'pieces_per_box' => ['nullable', 'integer', 'min:1'],
            'sub_unit' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'specification' => ['nullable', 'string', 'max:255'],
            'specifications' => ['nullable', 'array'],
            'specifications.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Item|null $item */
            $item = $this->route('item');
            $currentItemId = $item?->item_id;

            $itemName = trim((string) $this->input('item_name'));
            if ($itemName === '') {
                return;
            }

            $rawSpec = $this->input('specification') ?? ($this->input('specifications.0') ?? '');
            $specText = trim((string) $rawSpec);

            $matchingItems = Item::query()
                ->whereRaw('LOWER(TRIM(item_name)) = ?', [strtolower($itemName)])
                ->when($currentItemId, fn ($q) => $q->where('item_id', '!=', $currentItemId))
                ->with('specifications')
                ->get();

            if ($matchingItems->isNotEmpty()) {
                if ($specText === '') {
                    $validator->errors()->add(
                        'specification',
                        "Another catalog item named '{$itemName}' already exists. Please provide a distinct technical specification."
                    );
                    return;
                }

                foreach ($matchingItems as $existing) {
                    $existingSpecs = $existing->specifications
                        ->pluck('specification')
                        ->map(fn ($s) => strtolower(trim((string) $s)))
                        ->all();

                    if (in_array(strtolower($specText), $existingSpecs, true)) {
                        $validator->errors()->add(
                            'specification',
                            "Another variant of '{$itemName}' with the specification '{$specText}' already exists in the catalog."
                        );
                        return;
                    }
                }
            }
        });
    }
}
