<?php

namespace App\Enums;

enum ProcurementMethod: string
{
    case COMPETITIVE_BIDDING = 'COMPETITIVE_BIDDING';
    case LIMITED_SOURCE_BIDDING = 'LIMITED_SOURCE_BIDDING';
    case COMPETITIVE_DIALOGUE = 'COMPETITIVE_DIALOGUE';
    case UNSOLICITED_OFFER = 'UNSOLICITED_OFFER';
    case DIRECT_CONTRACTING = 'DIRECT_CONTRACTING';
    case DIRECT_ACQUISITION = 'DIRECT_ACQUISITION';
    case REPEAT_ORDER = 'REPEAT_ORDER';
    case SMALL_VALUE_PROCUREMENT = 'SMALL_VALUE_PROCUREMENT';
    case NEGOTIATED = 'NEGOTIATED';
    case DIRECT_SALES = 'DIRECT_SALES';
    case STI_PROCUREMENT = 'STI_PROCUREMENT';

    public static function options(): array
    {
        return [
            self::COMPETITIVE_BIDDING->value => 'Competitive Bidding',
            self::LIMITED_SOURCE_BIDDING->value => 'Limited Source Bidding',
            self::COMPETITIVE_DIALOGUE->value => 'Competitive Dialogue',
            self::UNSOLICITED_OFFER->value => 'Unsolicited Offer with Bid Matching',
            self::DIRECT_CONTRACTING->value => 'Direct Contracting',
            self::DIRECT_ACQUISITION->value => 'Direct Acquisition',
            self::REPEAT_ORDER->value => 'Repeat Order',
            self::SMALL_VALUE_PROCUREMENT->value => 'Small Value Procurement',
            self::NEGOTIATED->value => 'Negotiated Procurement',
            self::DIRECT_SALES->value => 'Direct Sales',
            self::STI_PROCUREMENT->value => 'Direct Procurement for Science, Technology, and Innovation',
        ];
    }

    public static function values(): array
    {
        return array_keys(self::options());
    }
}
