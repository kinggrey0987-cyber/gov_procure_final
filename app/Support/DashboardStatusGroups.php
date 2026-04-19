<?php

namespace App\Support;

final class DashboardStatusGroups
{
    /**
     * @return list<string>
     */
    public static function officePendingSupplyRequestStatuses(): array
    {
        return ['DRAFT', 'SUBMITTED', 'IN_REVIEW', 'PENDING'];
    }

    /**
     * @return list<string>
     */
    public static function officeReadyForClaimDistributionStatuses(): array
    {
        return ['RELEASED'];
    }

    /**
     * @return list<string>
     */
    public static function officeCompletedDistributionStatuses(): array
    {
        return ['RECEIVED'];
    }

    /**
     * @return list<string>
     */
    public static function procurementPendingPurchaseRequestStatuses(): array
    {
        return ['OPEN'];
    }

    /**
     * @return list<string>
     */
    public static function procurementInProgressPurchaseRequestStatuses(): array
    {
        return ['PROCESSING'];
    }

    /**
     * @return list<string>
     */
    public static function procurementOngoingPurchaseOrderStatuses(): array
    {
        return ['PLACED'];
    }

    /**
     * @return list<string>
     */
    public static function procurementCompletedPurchaseOrderStatuses(): array
    {
        return ['COMPLETED'];
    }

    /**
     * @return list<string>
     */
    public static function claimActiveStatuses(): array
    {
        return ['OPEN', 'IN_REVIEW'];
    }

    /**
     * @return list<string>
     */
    public static function executiveOpenIncidentStatuses(): array
    {
        return ['OPEN'];
    }

    /**
     * @return list<string>
     */
    public static function executiveInReviewIncidentStatuses(): array
    {
        return ['IN_REVIEW'];
    }

    /**
     * @return list<string>
     */
    public static function executivePendingPpmpStatuses(): array
    {
        return ['SUBMITTED'];
    }

    /**
     * @return list<string>
     */
    public static function executivePpmpWorkflowStatuses(): array
    {
        return ['SUBMITTED', 'APPROVED', 'RETURNED_FOR_REVISION', 'REJECTED'];
    }
}