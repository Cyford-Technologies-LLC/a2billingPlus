# Billing, Rating, and Reporting

The new billing module starts with deterministic services that can be tested
outside the legacy AGI runtime. Legacy call handling can keep writing `cc_call`,
but new code should use these services for rating checks and reporting math.

## Rating Fixtures

Regression scenarios live in `tests/Fixtures/rating_scenarios.json`.

The fixtures currently cover:

- longest-prefix rate selection
- initial billing block rounding
- billing increment rounding after the first block
- connection charges
- minimum charges
- customer sell cost and provider buy cost calculations

## Rating Rules

For a positive-duration call, billable seconds are calculated as:

```text
initial block when duration <= initblock
initblock + ceil((duration - initblock) / billingblock) * billingblock otherwise
```

Customer cost uses `rateinitial`, `connectcharge`, and `mincharge`.
Provider cost uses `buyrate`, `buyrateconnectcharge`, and `buyratemincharge`.
Both values are rounded to five decimal places with half-up rounding.

## Reporting Rules

ASR is answered calls divided by attempted calls for a date range. A call is
treated as answered when `terminatecauseid = 1` and `sessiontime > 0`.

ALOC is the average `sessiontime` for answered calls only.

Reconciliation compares `SUM(sessionbill)` against `SUM(buycost)` and reports
gross margin for the same date window.
