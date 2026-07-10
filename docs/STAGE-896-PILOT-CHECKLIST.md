# Stage 896 operator checklist

Before pilot:

- Stage 895 acceptance passed at 100%
- Stage 894 lookup endpoint and client enabled
- Stage 896 issue endpoint and client enabled with a separate secret
- Reconciliation enabled
- Manual processing and production issuing enabled
- Scheduled worker disabled
- One low-value USD reward selected
- Active linked recipient confirmed
- Published merchant-owned Microgift template confirmed

After pilot:

- Training Lab pilot state is `verified`
- Handoff state is `delivered`
- Reward state is `linked` or `issued`
- Microgifter lookup returns the same idempotency key and instance
- Recipient Action Center contains the reward
- Scheduled worker remains disabled
- Stage 896 issue endpoint is disabled after the approved pilot window
