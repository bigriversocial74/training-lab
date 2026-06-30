# Training Lab Single-SQL Boundary

When the existing database schema is uploaded, create one consolidated SQL file only.

## Required Input Before SQL

- Current users/account table names
- Current merchant/organization table names
- Current wallet table names
- Current reward table names
- Current claim/redeem table names
- Current role/permission table names
- Current migration naming pattern
- MySQL version/engine assumptions

## SQL Output Rule

Produce one file:

```text
microgifter_training_lab_stage_5_consolidated.sql
```

The file should include:

- table creation
- indexes
- foreign keys only after referenced tables are confirmed
- seed-safe defaults if needed
- install order notes
- rollback notes

## Do Not Include Yet

- upload processing
- wallet balance changes
- payment tables
- automatic reward issuing
- claim/redeem changes
- duplicate user/account tables
