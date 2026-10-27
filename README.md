# GovSupply Connect

<p align="center">
  <img src="public/logo.jpg" alt="GovSupply Connect Logo" width="160" style="border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" />
</p>

<p align="center">
  <strong>Municipal Procurement Transparency & Supply Distribution Monitoring System</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 11" />
  <img src="https://img.shields.io/badge/React-18-61DAFB?style=flat-square&logo=react&logoColor=black" alt="React 18" />
  <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?style=flat-square&logo=typescript&logoColor=white" alt="TypeScript" />
  <img src="https://img.shields.io/badge/Inertia.js-v2-8B5CF6?style=flat-square&logo=inertia&logoColor=white" alt="Inertia.js v2" />
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white" alt="Tailwind CSS" />
  <img src="https://img.shields.io/badge/PostgreSQL-Supported-336791?style=flat-square&logo=postgresql&logoColor=white" alt="PostgreSQL" />
  <img src="https://img.shields.io/badge/License-MIT-green.svg?style=flat-square" alt="License MIT" />
</p>

---

## 🏛️ Overview

**GovSupply Connect** is an enterprise-grade municipal procurement, inventory distribution, and supply chain surveillance platform engineered for Local Government Units (LGUs).

The system seamlessly links requisitioning departments, the **Bids and Awards Committee (BAC)**, the **General Services Office (GSO)**, and the **Office of the Mayor / Executive leadership**. It enforces strict statutory compliance with Philippine Public Procurement (Republic Act No. 9184), mitigates supply chain leakage through statistical anomaly detection, and provides an open **Public Transparency Portal** for citizen oversight.

---

## 🌟 Key Features & Modules

### 📋 1. PPMP & Office Requisition Management
* **Project Procurement Management Plan (PPMP):** Authoring, budget category allocation, quarterly scheduling, and executive approval workflows.
* **Supply Requests:** Multi-item requisitioning linked to approved PPMP items with fund availability validation.
* **Status Traceability:** Real-time requisition tracking from draft, department endorsement, executive approval, procurement handoff, to final distribution.

### ⚖️ 2. Procurement & BAC Operations
* **Purchase Requests (PR):** Automated PR generation from single or consolidated departmental supply requests.
* **Consolidation Engine:** Bundles identical requisition items across multiple offices for bulk procurement economies of scale.
* **Purchase Orders (PO):** PO issuance, itemized scheduling, supplier catalog matching, and price tracking.
* **Workflow Guardrails:** Enforces backend blocking to prevent rejected or cancelled requisitions from proceeding to procurement.

### 📦 3. GSO Intake & Multi-Batch Delivery Tracking
* **Staggered Multi-Batch Delivery:** Track multiple sequential deliveries under the same Purchase Order ID with remaining balance calculations.
* **Delivery Verification & Inspection:** Dual-party inspection recording accepted, damaged, and missing quantities.
* **Defect & Shortage Claims:** Automated replacement workflows, discrepancy reporting, and proactive follow-up purchase requests.
* **Warehouse Stock Reservation:** Dynamic inventory reservations preventing stock overallocation.

### 🏢 4. Inventory Distribution & Warehouse Monitoring
* **Office Distribution Records:** Departmental handover logging with accountable recipient acknowledgment and digital claim receipts.
* **Bundled Warehouse Quantity:** Granular warehouse location tracking, reorder levels, and automatic stock alerts.
* **Complete Inventory Ledger:** Immutable record of all stock-ins, allocations, returns, and write-offs.

### 📊 5. Algorithmic Governance & Statistical Surveillance
GovSupply Connect moves municipal oversight beyond static thresholds through inferential statistics and statistical process control (SPC):
* **Grubbs' Outlier Z-Score ($Z \ge 2.0\sigma$):** Flags anomalous commodity purchase prices compared to historical baselines.
* **Shewhart Statistical Process Control (2-Sigma):** Detects statistically abnormal consumption spikes across requisitioning offices.
* **Sliding-Window Demand Velocity:** Flags irregular requisition frequency clusters and burst demands.
* **Fiscal Midpoint Burn-Rate Depletion:** Identifies premature budget depletion anomalies.
* **Discrepancy & Defect Monitoring:** Tracks supplier defect rates and delivery discrepancies.

### 🏛️ 6. Executive Monitoring & Infographic Evidence
* **Interactive Executive Dashboard:** High-level KPIs covering budget utilization, active procurements, and inventory levels.
* **Infographic Evidence Cards:** Visual breakdown of detected anomalies detailing sample mean ($\mu$), standard deviation ($\sigma$), standardized score ($Z$), and historical benchmark comparisons.
* **Approval Gates:** Streamlined executive approval workflows for PPMPs, emergency procurements, and anomalous transactions.

### 🌐 7. Citizen Public Transparency Portal
* **Open Disclosure Registry:** Publicly accessible portal displaying verified procurement transactions and distributions.
* **Document Downloads:** Direct citizen access to approved resolution documents, notices of award, and delivery verification reports.
* **Interactive Statistics:** Monthly spending summaries, procurement category breakdowns, and supplier participation metrics.

---

## 🔒 Security & Governance

* **Role-Based Access Control (RBAC):** Strict domain-separated permissions for `ADMIN`, `EXEC_OFFICER`, `PROC_OFFICER`, `GSO_OFFICER`, and `OFFICE_USER`.
* **Two-Factor Authentication (2FA):** Email-based 2FA challenge protection for administrative and executive actions.
* **Comprehensive Audit Trail:** Immutable logging of user actions, financial approvals, status changes, and authentication events.
* **Password Policy:** Enforces mandatory password rotation on seeded and initial accounts.

---

## 🛠️ Technology Stack

| Domain | Technology |
| :--- | :--- |
| **Backend Framework** | [Laravel 11](https://laravel.com/) (PHP 8.2+) |
| **Frontend Framework** | [React 18](https://react.dev/) + [Inertia.js v2](https://inertiajs.com/) |
| **Language & Typing** | [TypeScript](https://www.typescriptlang.org/) |
| **Styling & UI** | [Tailwind CSS 3](https://tailwindcss.com/) & [Headless UI](https://headlessui.com/) |
| **Database** | [PostgreSQL](https://www.postgresql.org/) (Production-ready) / SQLite / MySQL |
| **Build & Asset Bundler** | [Vite 7](https://vitejs.dev/) |
| **PDF Generation** | jsPDF & html2canvas |
| **Testing Suites** | PHPUnit, Playwright, Cypress, Python Selenium |

---

## 🚀 Getting Started

### Prerequisites

Ensure you have installed:
* **PHP** 8.2 or higher (with `pdo`, `mbstring`, `openssl`, `curl`, `xml`, `gd` extensions)
* **Composer** 2.x
* **Node.js** 20.x or higher & **npm**
* **PostgreSQL** or **MySQL** (or SQLite for local development)

---

### Installation Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/kinggrey0987-cyber/gov_procure_final.git
   cd gov_procure_final
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies:**
   ```bash
   npm install
   ```

4. **Configure Environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Edit `.env` to configure your database connection (`DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).*

5. **Run Database Migrations & Seeders:**
   ```bash
   php artisan migrate --seed
   ```

6. **Create Public Storage Symlink:**
   ```bash
   php artisan storage:link
   ```

7. **Compile Frontend Assets:**
   ```bash
   # For local development with Hot Module Replacement (HMR):
   npm run dev

   # Or for production bundle:
   npm run build
   ```

8. **Start Local Development Server:**
   ```bash
   php artisan serve
   ```
   *Navigate to `http://localhost:8000` in your browser.*

---

## 🧪 Testing Suite

GovSupply Connect comes with comprehensive backend and end-to-end test suites:

```bash
# Run backend PHPUnit tests
php artisan test

# Run frontend type-checking
npm run build

# Run Playwright E2E tests
npm run test:playwright

# Run Cypress integration tests
npm run test:cypress
```

---

## 📦 Deployment Notes

### Persistent Storage Configuration
Uploaded procurement attachments and public disclosure files require persistent disk storage:
* **Local / VPS:** Set `FILESYSTEM_PUBLIC_DISK=public` and ensure `php artisan storage:link` has been executed.
* **Ephemeral Containers (e.g. Render / AWS):** Set `FILESYSTEM_PUBLIC_DISK=public_s3` and supply S3 credentials (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_URL`).

### Remote Maintenance Hardening
The `/system-migrate` maintenance console requires an explicit `DEPLOY_SECRET_KEY` in production `.env` (or an authenticated session with administrative permissions).

---

## 📄 License

This project is open-source software licensed under the [MIT License](LICENSE).
