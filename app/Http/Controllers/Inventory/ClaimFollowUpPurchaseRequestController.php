<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Claim\CreateClaimFollowUpPurchaseRequest;
use App\Models\Claim;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClaimFollowUpPurchaseRequestController
{
    public function __construct(private readonly PurchaseRequestService $purchaseRequestService)
    {
    }

    public function store(CreateClaimFollowUpPurchaseRequest $request, Claim $claim): RedirectResponse
    {
        abort_unless($request->user()->can('claim.follow-up-pr.create', $claim), 403);

        try {
            $purchaseRequest = $this->purchaseRequestService->createFromPendingClaimReplacement(
                claim: $claim,
                actor: $request->user(),
                remarks: $request->validated('remarks'),
            );

            return Redirect::route('inventory.claims.show', $claim->claims_id)
                ->with('success', sprintf(
                    'Follow-up purchase request %s created for claim %s and queued for Procurement Unit review.',
                    (string) $purchaseRequest->pr_number,
                    (string) $claim->claim_code
                ));
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? 'Unable to create follow-up purchase request.';

            return Redirect::route('inventory.claims.show', $claim->claims_id)
                ->withErrors($e->errors())
                ->with('error', $firstError);
        } catch (Throwable $e) {
            return Redirect::route('inventory.claims.show', $claim->claims_id)
                ->with('error', 'Error creating follow-up purchase request: '.$e->getMessage());
        }
    }
}
