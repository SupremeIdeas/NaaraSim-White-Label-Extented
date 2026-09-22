# Account Erasure — Retention & Purge (blueprint Section 26 / GDPR)

> Plain-language summary of exactly what is retained, for how long, and what
> becomes truly unrecoverable and when. Written to be shown to a compliance
> reviewer or legal counsel directly.

## What changed

Before this fix, approving a pending account-deletion request
(`AccountService::approveDeletion()`) called `erase()`, which **immediately
and permanently deleted** the user's wallet transactions, eSIM orders, SMS
orders, virtual numbers, referrals, wallet, and the user row itself in the
same request — leaving only an audit-log tombstone (the user's id and a
one-way hash of their email). There was zero retention window: if a
regulator, law-enforcement request, chargeback dispute, or AML audit ever
needed that history after approval, it was already gone.

`erase()` now performs **anonymize-and-retain** instead, with a real,
eventual, admin-configured purge.

## The three-stage lifecycle

### Stage 1 — Anonymization (immediate, on super-admin approval)

The moment a super admin approves a pending deletion, `AccountService::erase()`
runs and, in one DB transaction:

- Nulls out every genuinely personally-identifying field on the `User` row:
  name, email (replaced with a deterministic, unique, non-reversible
  placeholder), Google id, avatar, phone, WhatsApp number, country code, bio,
  city, address line, postal code, date of birth, plus 2FA secrets/recovery
  codes and security questions.
- Sets `is_active = false` and `anonymized_at = now()` — the account is
  unreachable and unusable to anyone, including its original owner, from
  that instant.
- Revokes all Sanctum API tokens.
- Sets `retention_purge_due_at = now() + account_erasure.retention_years`
  (admin-configurable via `Setting`, default **6 years** — confirm the exact
  figure with legal/compliance counsel for your jurisdiction; most financial
  regulators require 5–7 years).
- Writes an `account.anonymized` audit-log entry with the retention deadline
  and a one-way hash of the original email.
- Sends the "your account was erased" notification **before** the PII fields
  are overwritten, so the real name/email still render in that one email.

**What is retained, unaffected, under the same user id**: every
`WalletTransaction`, `EsimOrder`, `SmsOrder`, `VirtualNumber`, and the
`UserWallet` record itself, plus referral rows. From the user's own point of
view this is indistinguishable from deletion — they cannot log in, and their
name/email are gone from anything they could ever see again.

### Stage 2 — Retention window (the admin-configured hold)

The anonymized row and its linked financial/order records sit untouched for
`account_erasure.retention_years` (default 6 years) after anonymization. A
super admin can look up the retained records for a specific case during this
window via **Admin → Legal hold records** (`AccountService::viewRetainedRecordsForLegalHold()`),
which requires a case reference and reason and writes an
`account.legal_hold_viewed` audit entry on every access. This is a read-only
view — it does not and cannot restore the anonymized name/email; that
overwrite is irreversible by design. What a legal-hold request actually needs
is the retained financial trail under the account's id, and that's what this
surfaces.

### Stage 3 — Final purge (once the retention window elapses)

The `account:purge-erased` command runs daily (03:15, `routes/console.php`)
and finds every account where `anonymized_at` is set and
`retention_purge_due_at` has passed. For each one, `AccountService::purge()`:

- Writes an `account.purged` audit-log entry.
- Permanently deletes the wallet transactions, eSIM orders, SMS orders,
  virtual numbers, referrals, wallet, tokens, and finally the user row
  itself — genuinely irreversible, no audit trail retains the deleted rows'
  content (only the fact that a purge happened, and when).

This is where real data minimization happens — correctly deferred past the
legally-required retention window, instead of happening instantly on
approval.

## Verification performed

- `tests/Feature/AccountLifecycleTest.php`: approving a deletion anonymizes
  (not hard-deletes) the user row; `is_active` is false; every PII field is
  wiped; `anonymized_at`/`retention_purge_due_at` are set; the linked eSIM
  order survives under the same user id; the `account.erased` hard-delete
  tombstone action no longer fires at approval time.
- `account:purge-erased` leaves an anonymized account within its retention
  window untouched, and permanently deletes one whose
  `retention_purge_due_at` has passed (tested with a backdated timestamp).
- A super admin can view retained records under a legal hold with a case
  reference + reason; the call is rejected without both, and rejected for a
  non-super-admin.

## Not built in this pass

- The exact retention period (default 6 years) should be confirmed against
  real legal/compliance counsel for the platform's actual operating
  jurisdictions before going live — the default is a reasonable placeholder,
  not a legal opinion.
- No UI currently exists for an admin to change `account_erasure.retention_years`
  — it's a `Setting` key, settable via `Setting::setValue()` (e.g. from
  `php artisan tinker` or a future settings screen) until a dedicated admin
  field is added.
