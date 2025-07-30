# Step-Up MFA for Sensitive Encounters

Adds a "privileged-action" MFA prompt whenever a clinician views or edits an appointment that belongs to a high-sensitivity category (eg **Ketamine Infusion**).

The feature is completely pluggable – no core-file modifications – and relies on OpenEMR’s Symfony event dispatcher.

---
## 1  Installation (once per server)

```bash
cd openemr # project root
git checkout feature/stepup-mfa
mysql openemr < sql/stepup_mfa_globals.sql
```

Sync/rsync the branch to your server.  No database change beyond three `globals` rows.

---
## 2  Module Files

| File | Purpose |
|------|---------|
| `src/Services/SensitiveEncounterMfaService.php` | Reusable business logic (detect, cache, audit) |
| `interface/modules/custom_modules/oe-module-stepup-mfa/openemr.bootstrap.php` | Hooks into `PatientDemographics\ViewEvent` & redirects when required |
| `interface/stepup_mfa_verify.php` | MFA challenge page (TOTP today, U2F planned) |
| `interface/admin/stepup_mfa_settings.php` | Admin GUI to enable & configure |
| `sql/stepup_mfa_globals.sql` | Seeds default globals |

---
## 3  Configuration

**Administration → Step-Up MFA Settings**

1. Enable the checkbox.
2. Pick one or more appointment categories (Ctrl/Cmd-click).
3. Adjust grace-period (seconds) if desired (defaults 15 min).

These values are stored in the `globals` table:

| Name | Example | Meaning |
|------|---------|---------|
| `stepup_mfa_enabled` | `1` | Master switch |
| `stepup_mfa_categories` | `5,8,12` | Comma list of `pc_catid` |
| `stepup_mfa_timeout` | `900` | Seconds of "remember" per-patient |

---
## 4  Workflow

1. User logs in as normal (standard MFA at login still applies if enabled).
2. They click an appointment with category **Ketamine Infusion** (id 5).
3. `SensitiveEncounterMfaService` determines this is sensitive.
4. If the clinician has **not** completed step-up MFA for this patient within the last 15 minutes ⇒ redirect to `/interface/stepup_mfa_verify.php`.
5. User enters the 6-digit TOTP code.
6. On success the original URL loads; audit entry `MFA_SUCCESS` is written; session flag prevents further prompts during the grace-period.

---
## 5  Security / Audit

* Events logged via `EventAuditLogger` with event codes:
  * `MFA_REQUIRED` – redirect happened
  * `MFA_SUCCESS`  – correct code entered
  * `MFA_FAILURE`  – invalid code
* Grace-period is stored server-side in PHP session; never persisted.
* Only **administrators** (ACL `admin,super`) may change the settings.

---
## 6  Future Road-Map

* **U2F / WebAuthn fallback** – allow security-keys instead of TOTP.
* **Unit tests** (`PHPUnit`) for the service class.
* **Cypress E2E** covering success / failure paths.
* Optional per-category timeouts or provider-specific exemptions.

---
## 7  Troubleshooting

| Symptom | Resolution |
|---------|------------|
| Always redirected even after entering code | Ensure server clock is correct; confirm `stepup_mfa_timeout` not set to 0 |
| SQL seed fails with `Unknown column gl_category` | Use the simplified seed already included (only `gl_name`, `gl_value`) |
| MFA page shows *Invalid Code* | Verify the user has TOTP enabled in **MFA Management** and code not expired |

---
© 2025 Your Org.  GPL-3  |  Author: Engineering Team
