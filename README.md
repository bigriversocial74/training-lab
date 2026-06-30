# Microgifter Training Lab

This repository is the standalone source of truth for the Microgifter Training Lab.

## Active repository

- Repo: `bigriversocial74/training-lab`
- Default branch: `main`
- Public URL: `https://github.com/bigriversocial74/training-lab`

## Important boundary

This repo is no longer treated as `bigriversocial74/contactform/examples/labs/` for active Training Lab work. Older handoffs or copied paths may still mention `contactform` or `examples/labs/`; those references should be treated as historical unless explicitly revalidated.

## Safety model

Training Lab must remain simulation-first:

- Do not process real uploads.
- Do not process live payments.
- Do not change account balances.
- Do not perform live claim redemption.
- Do not change production Microgifter accounts unless specifically approved and documented.

See `docs/DB-SAFE-BOUNDARIES.md` before changing database, proof, reward, or claim behavior.

## Agent workflow

Before coding:

1. Start from `main`.
2. Create a short-lived branch.
3. Keep changes scoped.
4. Do not mix Loyalty Quest assets or old `contactform` paths into this repo.
5. If SQL is changed, document whether it is import-safe and whether it is intended to run.
6. Open a PR back into `main`.

## Current cleanup status

This repo needs a clean source-of-truth structure before major feature work:

- Handoff docs live under `docs/`.
- DB boundaries live under `docs/DB-SAFE-BOUNDARIES.md`.
- Asset/template inventory lives under `docs/ASSET-INVENTORY.md`.
- Template placeholder rules live under `templates/README.md`.
