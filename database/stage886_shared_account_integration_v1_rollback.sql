-- Stage 886 Shared Microgifter Account Integration v1 rollback
-- WARNING: This permanently removes Stage 886 identity links and nonce audit records.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS training_auth_nonces;
DROP TABLE IF EXISTS training_account_links;
SET FOREIGN_KEY_CHECKS = 1;
