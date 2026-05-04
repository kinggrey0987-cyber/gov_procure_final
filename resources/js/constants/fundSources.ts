export type FundSourceOption = {
    value: string;
    label: string;
};

export const FUND_SOURCES: FundSourceOption[] = [
    { value: 'GAA_CURRENT', label: 'GAA Current Appropriation' },
    { value: 'GAA_CONTINUING', label: 'GAA Continuing Appropriation' },
    { value: 'APPROPRIATION_ORDINANCE', label: 'Appropriation Ordinance (LGU)' },
    { value: 'IGI', label: 'Internally Generated Income (IGI)' },
    { value: 'SPECIAL_PURPOSE_FUND', label: 'Special Purpose Fund' },
    { value: 'TRUST_FUND', label: 'Trust Fund' },
    { value: 'FOREIGN_ASSISTED', label: 'Foreign-Assisted Fund' },
    { value: 'MVUC', label: 'Special Road Fund (MVUC)' },
];

export const DEFAULT_FUND_SOURCE = 'GAA_CURRENT';

export const getFundSourceLabel = (value: string): string => {
    const source = FUND_SOURCES.find(s => s.value === value);
    return source ? source.label : value;
};
