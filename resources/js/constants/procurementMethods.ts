export type ProcurementMethodOption = {
    value: string;
    label: string;
};

export const PROCUREMENT_METHODS: ProcurementMethodOption[] = [
    { value: 'COMPETITIVE_BIDDING', label: 'Competitive Bidding' },
    { value: 'LIMITED_SOURCE_BIDDING', label: 'Limited Source Bidding' },
    { value: 'COMPETITIVE_DIALOGUE', label: 'Competitive Dialogue' },
    { value: 'UNSOLICITED_OFFER', label: 'Unsolicited Offer with Bid Matching' },
    { value: 'DIRECT_CONTRACTING', label: 'Direct Contracting' },
    { value: 'DIRECT_ACQUISITION', label: 'Direct Acquisition' },
    { value: 'REPEAT_ORDER', label: 'Repeat Order' },
    { value: 'SMALL_VALUE_PROCUREMENT', label: 'Small Value Procurement' },
    { value: 'NEGOTIATED', label: 'Negotiated Procurement' },
    { value: 'DIRECT_SALES', label: 'Direct Sales' },
    { value: 'STI_PROCUREMENT', label: 'Direct Procurement for Science, Technology, and Innovation' },
];

export const DEFAULT_PROCUREMENT_METHOD = 'COMPETITIVE_BIDDING';

export const getProcurementMethodLabel = (value: string): string => {
    const method = PROCUREMENT_METHODS.find(m => m.value === value);
    return method ? method.label : value;
};
