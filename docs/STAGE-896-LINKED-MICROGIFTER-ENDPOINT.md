# Stage 896 linked Microgifter endpoint

The Training Lab pilot controller requires the signed Microgifter pilot issue endpoint delivered by the linked Microgifter Stage 896 pull request.

Deployment order:

1. Merge and deploy the Microgifter endpoint.
2. Merge and deploy the Training Lab pilot controller.
3. Configure separate Stage 894 lookup and Stage 896 issue secrets.
4. Keep the scheduled worker disabled.
5. Complete Stage 895 acceptance.
6. Enable Stage 896 only for one approved pilot.

No SQL is required on either side.
