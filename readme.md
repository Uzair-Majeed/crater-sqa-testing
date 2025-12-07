<img src="https://res.cloudinary.com/bytefury/image/upload/v1574149856/Crater/craterframe.png">

# SQE Final Project - Comprehensive Quality Engineering for Crater

**Project Title:** Comprehensive Quality Engineering for the Open-Source Crater Application  
**Start Date:** Sep 2024 - Dec 2024  
**Section:** SE-B

**Testers:**
*   Uzair Majeed (23i-3063)
*   Hussnain Haider (23i-0695)
*   Faez Ahmed (23i-0598)

---

## 1. Appendix

| Resource | Link |
| :--- | :--- |
| **Original Website** | [https://crater.financial/](https://crater.financial/) |
| **Original Repo** | [https://github.com/crater-invoice-inc/crater](https://github.com/crater-invoice-inc/crater) |
| **Project Repo** | [https://github.com/Uzair-Majeed/crater-sqa-testing](https://github.com/Uzair-Majeed/crater-sqa-testing.git) |
| **Deployment** | [https://crater-sqa-testing-production.up.railway.app/](https://crater-sqa-testing-production.up.railway.app/) |
| **Artifacts & Pages** | [https://uzair-majeed.github.io/crater-sqa-testing/index.html](https://uzair-majeed.github.io/crater-sqa-testing/index.html) |

**Sample Credentials:**
*   **Email:** `uzairmjd886@gmail.com`
*   **Password:** `12345678`
*   *(Or create a new account on the original website)*

**Key Documents:**
*   `SQE_Unit_Testing_Doc.pdf`
*   `SQE_Integration_Testing_Doc.pdf`

---

## 2. Test Plan (IEEE 829 Standard Format)

### 1.1 Test Objectives
*   **Functional Correctness:** Validate UI workflows via black-box testing.
*   **Backend Correctness:** Validate logic via white-box testing.
*   **Automated Quality Gates:** Ensure stability through CI/CD.
*   **Regression Detection:** Catch issues early with automated builds.
*   **Non-Functional Quality:** Meet usability, performance, accessibility, and security expectations.

### 1.2 Test Items
*   **Crater Web Application:** Forked version.
*   **Backend:** Laravel/PHP framework.
*   **UI:** Single Page Application (SPA) components.
*   **API Endpoints:** Invoices, Customers, Estimates, etc.

### 1.3 Features to be Tested
*   Login & Authentication
*   Dashboard Analytics
*   Create/Edit Invoice
*   Customer Management
*   Expenses & Payments
*   Tax Settings
*   *And many others...*

### 1.4 Features NOT to be Tested
*   Mobile UI
*   External Integrations
*   Multi-tenant Billing
*   Localization
*   Email Providers

### 1.5 Testing Types Included

| Category | Tool / Approach |
| :--- | :--- |
| **White-Box** | Pest, Code Coverage (Xdebug) |
| **Integration** | Pest + Laravel |
| **Black-Box UI/E2E** | Cypress |
| **Security** | OWASP ZAP |
| **Performance** | Lighthouse |
| **Accessibility** | Lighthouse |

---

## 3. Test Approach

### 2.1 White-Box Testing
*   Executed **Pest unit tests** inside a Docker environment.
*   Performed branch and statement coverage analysis using **Xdebug**.

### 2.2 Integration Testing
Tested backend component interactions:
*   Invoice → Customer
*   Invoice → Payments
*   Tax → Products

### 2.3 Black-Box Testing
**Cypress UI testing** including:
*   Login flow
*   Create invoice
*   Edit product
*   Delete customer
*   Invoice PDF journey
*   *Executed in Cypress headless mode within Docker.*

### 2.4 Non-Functional Testing (Staging @ Railway)
| Category | Tool |
| :--- | :--- |
| **Performance** | Lighthouse |
| **Accessibility** | Lighthouse |
| **Security** | OWASP |

---

## 4. Test Tools

| Phase | Tools |
| :--- | :--- |
| **Source** | GitHub |
| **Build** | Jenkins + GitHub Actions |
| **Container** | Docker |
| **Unit** | Pest |
| **Coverage** | Xdebug |
| **UI** | Cypress |
| **Staging** | Railway |
| **Audits** | Lighthouse + OWASP |
| **Reporting** | GitHub Actions artifacts + GitHub Pages |

---

## 5. CI/CD Integration

**Pipeline Mapping:**
*   **Trigger:** On every push/pull request to `main` or `master`.
*   **Configuration:** `.github/workflows/ci.yml`

### Pipeline Stages (GitHub Actions)
1.  **PHP 8.1 - Build & Test:**
    *   Checkout & Setup PHP/Composer.
    *   Seed Database (SQLite).
    *   Run **Integration Tests** (Pest).
    *   Run **Unit Tests** (Pest) with Coverage.
    *   Upload Results & Deploy Coverage to GitHub Pages.
2.  **Cypress E2E Tests:**
    *   Setup Node.js & Cypress.
    *   Start Laravel Server.
    *   Run Cypress Main Suite & Blackbox Suite.
    *   Upload Videos & Screenshots as artifacts.

*(Initially used Jenkins, migrated to GitHub Actions for better integration).*

### Deployment
*   **Staging:** Automatic deployment to Railway for Performance/Security/Accessibility audits.
*   **Production:** Manual/Triggered deployment to Railway final production.

---

## 6. Test Environment

| Stage | Tools Used |
| :--- | :--- |
| **Local Dev** | Docker + Pest |
| **Testing** | GitHub Actions |
| **Staging** | Railway |
| **Browser UI** | Cypress, Lighthouse, Chrome Audit |
| **Security** | OWASP, Chrome Audit |

---

## 7. AI-Automation in Testing

We implemented a robust AI-driven automation strategy to enhance efficiency and consistency.

*   **Automated Test Generation:** Used OpenAI GPT-4.1 & Gemini 2.5 Flash to generate Unit and Integration tests for broad codebase coverage.
*   **Refactoring & Cleanup:** AI agents refactored failing tests, increased coverage, and replaced complex mocks with factory data.
*   **Standardized Documentation:** Scripts automatically generated IEEE 829-2008 compliant PDF reports from raw test results.
*   **Workflow Optimization:** Helper scripts merged results, organized files, and streamlined the pipeline.

---

## 8. IEEE Test Case Format Highlights

### White-Box & Integration
*   **Command:** `php -d memory_limit=2000M vendor/bin/pest tests/Unit-Testing --coverage --coverage-html coverage`
*   **Results:** 2,725 passed tests with ~20% overall code coverage (focused on critical infrastructure).
*   **Integration:** Verified API-DB interactions (e.g., `verify address entity association`).

### Black-Box (Sample)
**TC-UI-001: Login with valid credentials**
*   **Objective:** Verify valid credentials allow login.
*   **Preconditions:** App running on localhost.
*   **Steps:** Navigate to login -> Enter credentials (admin/admin) -> Click login.
*   **Expected:** User lands on dashboard.

### Non-Functional Summary
*   **Lighthouse:** Identified performance bottlenecks (LCP ~9.7s) on dashboard; Accessibility basic checks passed.
*   **OWASP:** Found 0 High/Critical issues. 269 Medium issues mostly related to potential DOM XSS (requires sanitization).

---

## 9. Deliverables & Artifacts

| Deliverable | Description |
| :--- | :--- |
| **Test Plan** | IEEE Standard Format |
| **Test Cases** | UI, API, Unit Suites |
| **CI/CD Config** | `.github/workflows/ci.yml` |
| **Cypress Assets** | Video logs & Screenshots (Artifacts) |
| **Coverage Report** | Hosted on GitHub Pages |
| **security Reports** | Lighthouse & OWASP Reports in Repo |

---

## 10. Collaboration

| Team Member | Role & Contributions |
| :--- | :--- |
| **Uzair Majeed** | White-box testing, Performance Testing, CI/CD Pipeline |
| **Hussnain Haider** | Cypress Automation, Documentation |
| **Faez Ahmed** | Cypress Automation, Documentation |

---

## 11. Conclusion
We successfully implemented an end-to-end quality automation pipeline for Crater. Functional correctness is verified via automated Unit and UI tests, deployments are automated via CI/CD, and AI agents assist in maintaining test coverage and documentation standards.
