# GovSupply Connect Capstone Demo Script

## Reset command

Use this command before a formal demo when you want a clean workflow dataset while preserving master data such as users, roles, offices, suppliers, item categories, items, and inventory baseline:

```bash
php artisan demo:reset-workflow
```

After the reset, verify seed integrity:

```bash
php artisan demo:audit-seed
```

Expected audit result:

```text
Seed audit complete. Issues=0 Fixes=0
```

## Known demo actors from current workflow seed

The workflow seed reuses existing accounts and prints the actor map during reset:

| Role | Demo email |
| --- | --- |
| Executive Officer | `jeronopjer@gmail.com` |
| GSO / Inventory Officer | `rienyhulleza1022@gmail.com` |
| Procurement Officer | `jayrossrusselherbias@gmail.com` |
| Office User A | `rienz6977@gmail.com` |
| Office User B | `govprocure86@gmail.com` |
| Office User C | `roengwapo09@gmail.com` |

Use the project’s known demo password policy/password list from your local setup. Do not include real production passwords in this document.

## Ten-minute defense flow

### 1. Public transparency landing

Start unauthenticated.

Show:

- Public transparency summary.
- Published disclosures.
- Downloadable public reports/documents.
- How the public can verify procurement transparency without internal access.

Defense line:

> The system supports public accountability by publishing selected procurement disclosures while protecting internal workflows.

### 2. Office planning and request flow

Login as an office user.

Show:

- Office dashboard.
- PPMP list and detail.
- Supply request tracking.
- Request status history.
- Supplies ready for claim, if present.

Defense line:

> The office role starts the procurement lifecycle through PPMP planning and supply requests, with traceable status history.

### 3. Procurement processing flow

Login as the procurement officer.

Show:

- Procurement dashboard.
- Purchase request review.
- Purchase orders.
- Supplier records.
- Procurement documents.

Defense line:

> Procurement officers convert validated requests into purchase orders while maintaining supplier and document records.

### 4. GSO / inventory flow

Login as the GSO or inventory officer.

Show:

- Inventory dashboard.
- Delivery monitoring.
- Delivery receiving and verification.
- Inventory monitoring.
- Distribution records.
- Claims review.

Defense line:

> The GSO role handles delivery, verification, stock movement, distribution, and claim reconciliation.

### 5. Executive monitoring and governance

Login as the executive officer.

Show:

- Executive dashboard KPIs.
- Procurement analytics.
- Anomalies and alerts.
- PPMP approvals.
- Governance logs.
- Transparency/disclosure management.

Defense line:

> Executives get oversight through KPIs, anomaly signals, approvals, audit trails, and disclosure controls.

### 6. Return to public portal

End unauthenticated or as a public user.

Show:

- Published disclosure appears publicly.
- Draft/internal disclosures remain hidden.

Defense line:

> The workflow ends with controlled public transparency, not unrestricted data exposure.

## Demo safety checklist

Before presenting:

- Run `php artisan demo:reset-workflow`.
- Run `php artisan demo:audit-seed` and confirm `Issues=0 Fixes=0`.
- Run `php artisan test` and confirm all tests pass.
- Run `npm run build` and confirm production build passes.
- Confirm each demo account can log in.
- Open dashboards once before the panel to warm caches/assets.
- Prepare screenshots of each major dashboard as backup.

## Expected seeded workflow coverage

The current workflow reset creates a longitudinal dataset with:

- PPMP records and PPMP items.
- Office supply requests and line items.
- Procurement requests and purchase orders.
- Deliveries and delivery items.
- Distribution records and claims.
- Inventory baseline records.
- Anomalies and alerts.
- Public disclosures and supporting documents.

## Core defense message

GovSupply Connect is a role-based government procurement governance platform. It covers planning, requesting, procurement processing, delivery, inventory, distribution, claims, executive monitoring, auditability, notifications, document attachments, anomaly detection, and public disclosure.

## If something fails during demo

- If login fails, switch to screenshots and explain the role workflow.
- If a dashboard has no data, run `php artisan demo:reset-workflow` again.
- If public documents fail to download, run `php artisan demo:audit-seed`.
- If assets fail to load, run `npm run build` and reload.
- If a forbidden page appears, explain that role-based access control is working and switch to the correct role.
