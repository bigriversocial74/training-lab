# Stage 896 rollback

Disable new pilot activity immediately:

```text
TL_STAGE896_LIMITED_PILOT_ENABLED=false
TL_MICROGIFTER_PILOT_ISSUE_ENABLED=false
MG_TRAINING_LAB_PILOT_ISSUE_ENABLED=false
```

Keep the Stage 894 signed lookup available while any pilot is uncertain so read-back verification can continue. Keep the scheduled worker disabled. Existing idempotent delivery and audit records are not deleted.
