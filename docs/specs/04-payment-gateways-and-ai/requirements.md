# Requirements: `04-payment-gateways-and-ai` — Pluggable Payment Gateways & Gemini AI Catalog Copilot

## EARS Acceptance Criteria
- **REQ-01:** WHEN a payment webhook arrives with a duplicate `idempotency_key`, THE SYSTEM SHALL return `200 OK` without double-transitioning the order or re-firing order paid hooks.