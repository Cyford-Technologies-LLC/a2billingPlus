-- Add webhook_url to cc_did_assignment so A2BillingPlus knows where to call back
-- when an inbound SMS arrives for a DID.
ALTER TABLE cc_did_assignment
    ADD COLUMN webhook_url VARCHAR(512) NOT NULL DEFAULT '';
