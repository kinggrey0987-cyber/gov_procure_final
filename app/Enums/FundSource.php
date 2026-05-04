<?php

namespace App\Enums;

enum FundSource: string
{
    case GAA_CURRENT = 'GAA_CURRENT';
    case GAA_CONTINUING = 'GAA_CONTINUING';
    case APPROPRIATION_ORDINANCE = 'APPROPRIATION_ORDINANCE';
    case IGI = 'IGI';
    case SPECIAL_PURPOSE_FUND = 'SPECIAL_PURPOSE_FUND';
    case TRUST_FUND = 'TRUST_FUND';
    case FOREIGN_ASSISTED = 'FOREIGN_ASSISTED';
    case MVUC = 'MVUC';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::GAA_CURRENT->value => 'GAA Current Appropriation',
            self::GAA_CONTINUING->value => 'GAA Continuing Appropriation',
            self::APPROPRIATION_ORDINANCE->value => 'Appropriation Ordinance (LGU)',
            self::IGI->value => 'Internally Generated Income (IGI)',
            self::SPECIAL_PURPOSE_FUND->value => 'Special Purpose Fund',
            self::TRUST_FUND->value => 'Trust Fund',
            self::FOREIGN_ASSISTED->value => 'Foreign-Assisted Fund',
            self::MVUC->value => 'Special Road Fund (MVUC)',
        ];
    }

    public static function getLabel(string $value): string
    {
        return self::labels()[$value] ?? $value;
    }
}
