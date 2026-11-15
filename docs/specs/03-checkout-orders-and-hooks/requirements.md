# Requirements: `03-checkout-orders-and-hooks` — Atomic Checkout, Stock Reservation & Order State Machine

## EARS Acceptance Criteria
- **REQ-01:** WHEN two concurrent checkouts request the last unit of a SKU, THE SYSTEM SHALL atomically reserve stock for exactly one order and return `409 INSUFFICIENT_STOCK` to the second.