# ADR: Performance Evaluation System MVP Rules

Date: 2026-08-26
Status: Accepted by project owner
Source: project owner finalized decisions and `PES/TASKLIST.md`

## Context

The Performance Evaluation System needs a small, auditable workflow for HR-created evaluations and public client reviews.

## Decisions

- An evaluation is identified by staff, evaluation form, and an HR-controlled timeframe.
- Client drafts stay in the browser/session and are not persisted.
- The client must review a read-only summary and explicitly confirm before submission.
- Submitted evaluation data is permanent, HR-owned, read-only, and stored with the original response JSON.
- Temporary links control access only. They expire or lock after submission, while the evaluation remains available to HR.
- Expired unsubmitted links may be regenerated; submitted links may not.
- Forms are data-driven and support rating, text, textarea, select, required/optional, and ordering. A created evaluation keeps a form snapshot.
- Staff, forms, evaluations, and links use archive/soft-delete behavior; no hard delete in the UI.
- Email notifications are non-blocking and require an explicitly configured sender/SMTP provider.
- No background jobs or speculative automation are part of the MVP.

## Current implementation direction

The current deployed application remains React + Vercel + Supabase. The separate PHP 8 + MySQL + cPanel proposal is not to be mixed into this implementation until the deployment target is confirmed.

## Verification

- App TypeScript check passed on 2026-08-26.
- API TypeScript check passed on 2026-08-26.
- Production Vite build passed on 2026-08-26.
- Supabase migration file pending application to the hosted database.
