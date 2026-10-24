# Data protection assessment (draft)

> **Status: DRAFT for review by the Data Protection Officer and legal counsel. This is not legal advice.**
> It maps how the platform implements the Nigeria Data Protection Act 2023 (NDPA) so that reviewers
> can confirm, correct or extend it. Section references to the Act are indicative and **must be
> confirmed by counsel**.

## 1. Controller and roles

| Item | Value |
| --- | --- |
| Data controller | `[PLACEHOLDER: legal name of the campaign organisation]` |
| Data Protection Officer | `[PLACEHOLDER: name and contact]`. Platform role: `data_protection_officer` |
| Processors | Hosting provider, SMS provider (Africa's Talking), WhatsApp Business Platform (if used), object storage. **Data processing agreements required** |
| Registration with NDPC | To be assessed. Likely required if the campaign is a data controller "of major importance" |

## 2. Personal data inventory

| Data | Where | Purpose | Lawful basis (to confirm) | Protection |
| --- | --- | --- | --- | --- |
| Supporter name | `supporters.full_name` | Identify and contact supporters | Consent | Access by role and area; trigram index for search |
| Supporter phone | `supporters.phone_encrypted`, `phone_hash`, `phone_last4` | Contact (with channel consent), deduplication | Consent | AES-256-GCM with a dedicated key; keyed HMAC hash; masked in the UI; reveals audited |
| Location (LGA, ward, polling unit) | `supporters.*_id` | Coordination by area | Consent | Area-scoped access |
| Supporter status | `supporters.verification_status`, `wants_to_volunteer` | Data quality, volunteer recruitment | Consent | Area-scoped |
| 18+ attestation | `supporters.adult_confirmed_at` | Exclude minors | Legitimate interest / legal compliance | Timestamp only; no date of birth |
| Consent decisions | `consent_records` | Proof of consent and withdrawal | Legal obligation / accountability | Append-only (database trigger), keyed hash |
| Suppression list | `suppressions` | Never contact people who opted out or asked for erasure | Legal obligation | Keyed hash only |
| Follow-up notes | `contact_attempts.notes_encrypted` | Coordination | Consent | Encrypted; cleared on erasure |
| Staff accounts | `users` | Authentication and accountability | Contract / legitimate interest | Encrypted phone, TOTP for privileged roles, audited |
| Login history, audit log | `login_events`, `audit_logs` | Security and accountability | Legitimate interest / legal obligation | Append-only audit log with hash chain |
| Data-subject requests | `data_subject_requests` | Fulfil rights | Legal obligation | Encrypted contact number, DPO only |

**Never collected:** NIN, BVN, PVC/VIN numbers, religion, ethnicity, date of birth, address, photos of
supporters. Purchased contact lists and scraped data are not imported.

### Sensitive personal data

Registration as a **supporter** reveals a **political opinion**, which the NDPA treats as sensitive
personal data. The platform therefore relies on **explicit consent**, captured as follows:

- An 18+ attestation and a positive action (registration) after the privacy notice summary is shown.
- Separate, unticked-by-default consent per channel (SMS, WhatsApp, calls) and purpose, recorded with
  the notice version, source and evidence.
- Canvassing outcomes record **contact status only** (reached, not home, asked not to be contacted…),
  never a person's voting intention or opinion.

Counsel to confirm that this meets the Act's conditions for processing sensitive personal data, or
whether additional safeguards are needed.

## 3. Principles and how they are met

| Principle | Implementation |
| --- | --- |
| Lawfulness, fairness, transparency | Privacy notice versions (`privacy_notices`); consent references the version shown; summary shown at every capture point; Pidgin variant |
| Purpose limitation | Consent is per purpose (`campaign_updates`, `volunteering`, `events`); sends check the purpose (Phase 9) |
| Data minimisation | Minimal fields; see "never collected" above |
| Accuracy | Corrections with reasons and optimistic locking; verification workflow; duplicate review |
| Storage limitation | `retention_until` (default 36 months, renewed by consent or contact); weekly anonymisation (`privacy:apply-retention`); legal hold |
| Integrity and confidentiality | TLS, field encryption, keyed hashes, role and area access control, PIN-confirmed sensitive areas, private storage, signed expiring downloads, audit log |
| Accountability | Hash-chained audit log; consent evidence; DSR history; this document |

## 4. Data-subject rights

| Right | How | Timing |
| --- | --- | --- |
| Withdraw consent | SMS `STOP` (Phase 8), USSD menu 6 (Phase 8), web preferences (Phase 10), staff entry, DSR | **Immediate**: suppression is added at once, and queued messages are re-checked before sending (Phase 9) |
| Object / restrict | DSR | Contact suppressed **immediately** on receipt; record flagged `processing_restricted` on completion |
| Erasure | DSR, retention | Contact suppressed on receipt; anonymised on completion after identity verification |
| Access | DSR | DPO prepares a JSON package of all data held (record, consent history, contacts, suppressions) |
| Rectification | DSR or staff correction | Audited corrections |

Deadline: 30 days by default (`DSR_RESPONSE_DAYS`, changeable in settings). Counsel to confirm the
statutory period. The requester gets an SMS acknowledgement with the reference and due date, and an
SMS on completion or rejection.

Identity: requests from the person's own phone (USSD, SMS, web after a one-time code) are treated as
verified; other requests need the DPO to verify identity and record the method.

## 5. Security of processing

See `docs/SECURITY.md`. Key points:

- Keys `PHONE_HASH_KEY` and `FIELD_ENCRYPTION_KEY` are separate from `APP_KEY`, and production
  refuses to boot without them.
- Access is limited by role and by area, enforced in queries and tested against URL manipulation.
- Exports are queued, scoped and masked by default, expire after 24 hours, and are audited
  (request, completion, download).

## 6. Breach response (to be completed with counsel)

1. Contain: suspend affected accounts, revoke sessions and devices, rotate credentials.
2. Assess: use the audit log and request IDs to establish scope.
3. Notify the NDPC within the statutory deadline (understood to be 72 hours; confirm), and notify
   affected people where the risk is high.
4. Record the incident and the remediation.

A detailed runbook follows in Phase 12.

## 7. Open items for the DPO and counsel

- [ ] Approve the privacy notice (replace `PLACEHOLDER-2026-10`) in English and Pidgin.
- [ ] Confirm lawful bases, sensitive-data conditions and section references.
- [ ] Carry out and sign off a Data Protection Impact Assessment (high-risk processing of political opinions at scale).
- [ ] Confirm the retention period and DSR deadline.
- [ ] Decide on hosting location and any cross-border transfer safeguards.
- [ ] Put data processing agreements in place with the SMS, WhatsApp, hosting and storage providers.
- [ ] Assess the NDPC registration obligation.
- [ ] Have a native speaker review the Pidgin notices.
