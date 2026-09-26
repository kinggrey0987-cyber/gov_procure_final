export type SpecificationOption = {
    item_specification_id: number;
    specification: string;
};

export type ItemOption = {
    item_id: number;
    item_name: string;
    item_category_id: number;
    item_category_name: string;
    default_unit?: string | null;
    pieces_per_box?: number | null;
    sub_unit?: string | null;
    specifications?: SpecificationOption[];
};

export type CatalogVariant = {
    item_id: number;
    specification: string;
    item_category_id: number;
    item_category_name: string;
    default_unit: string;
    pieces_per_box: number;
    sub_unit: string;
};

export type CatalogGroup = {
    group_key: string;
    base_name: string;
    item_category_id: number;
    item_category_name: string;
    default_unit: string;
    pieces_per_box: number;
    sub_unit: string;
    variants: CatalogVariant[];
};

/**
 * Extracts a clean, short, brandless base item name from full catalog item titles.
 * Examples:
 *   "Bond Paper, A4 (80 GSM)" -> "Bond Paper"
 *   "Ballpen, Black" -> "Ballpen"
 *   "Flash Drive, 32GB" -> "Flash Drive"
 *   "Folder, Tagboard (A4 size)" -> "Folder, Tagboard"
 *   "Heavy Duty Stapler" -> "Heavy Duty Stapler"
 */
export function extractBaseName(rawName: string): string {
    const cleaned = rawName.replace(/[\s-]+$/, '').trim();

    const specialPrefixes = [
        'folder, tagboard',
        'printer ink',
        'mouse, usb optical',
    ];

    const lower = cleaned.toLowerCase();
    for (const prefix of specialPrefixes) {
        if (lower.startsWith(prefix)) {
            return cleaned.substring(0, prefix.length).trim();
        }
    }

    const commaIndex = cleaned.indexOf(',');
    if (commaIndex > 0) {
        return cleaned.substring(0, commaIndex).trim();
    }

    return cleaned;
}

/**
 * Resolves the technical specification text for an item option.
 */
export function extractVariantSpec(item: ItemOption): string {
    if (item.specifications && item.specifications.length > 0) {
        const spec = item.specifications[0].specification.trim();
        if (spec !== '') {
            return spec;
        }
    }

    const commaIndex = item.item_name.indexOf(',');
    if (commaIndex > 0) {
        return item.item_name.substring(commaIndex + 1).replace(/[\s-]+$/, '').trim();
    }

    return item.item_name;
}

/**
 * Groups a flat list of catalog item options into clean base products with their variants.
 * Automatically filters out duplicate test records with trailing hyphens.
 */
export function buildCatalogGroups(itemOptions: ItemOption[]): CatalogGroup[] {
    const groupsMap = new Map<string, CatalogGroup>();
    const seenSpecByGroup = new Map<string, Set<string>>();

    for (const option of itemOptions) {
        const baseName = extractBaseName(option.item_name);
        const groupKey = baseName.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const spec = extractVariantSpec(option);
        const specKey = spec.toLowerCase().trim();

        const isTestDashItem = /[\s-]+-$/.test(option.item_name);

        if (!seenSpecByGroup.has(groupKey)) {
            seenSpecByGroup.set(groupKey, new Set<string>());
        }

        const seenSpecs = seenSpecByGroup.get(groupKey)!;

        // Skip test duplicate items (like "Bond Paper, Letter (80 GSM) - -") if this spec is already present
        if (isTestDashItem && seenSpecs.has(specKey)) {
            continue;
        }

        seenSpecs.add(specKey);

        if (!groupsMap.has(groupKey)) {
            groupsMap.set(groupKey, {
                group_key: groupKey,
                base_name: baseName,
                item_category_id: option.item_category_id,
                item_category_name: option.item_category_name,
                default_unit: option.default_unit || 'box',
                pieces_per_box: option.pieces_per_box || 1,
                sub_unit: option.sub_unit || 'piece',
                variants: [],
            });
        }

        const group = groupsMap.get(groupKey)!;
        group.variants.push({
            item_id: option.item_id,
            specification: spec,
            item_category_id: option.item_category_id,
            item_category_name: option.item_category_name,
            default_unit: option.default_unit || 'box',
            pieces_per_box: option.pieces_per_box || 1,
            sub_unit: option.sub_unit || 'piece',
        });
    }

    return Array.from(groupsMap.values()).sort((a, b) => a.base_name.localeCompare(b.base_name));
}

/**
 * Finds the group that contains a specific variant item_id.
 */
export function findGroupByItemId(groups: CatalogGroup[], itemId: number | string): CatalogGroup | undefined {
    const idNum = Number(itemId);
    if (!idNum) return undefined;
    return groups.find((group) => group.variants.some((v) => v.item_id === idNum));
}

/**
 * Finds a specific variant by item_id across all groups.
 */
export function findVariantByItemId(groups: CatalogGroup[], itemId: number | string): CatalogVariant | undefined {
    const idNum = Number(itemId);
    if (!idNum) return undefined;
    for (const group of groups) {
        const found = group.variants.find((v) => v.item_id === idNum);
        if (found) return found;
    }
    return undefined;
}
