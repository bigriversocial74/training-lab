# Training Lab Handoff

## Source of truth

Use this standalone repository for Training Lab work:

```txt
bigriversocial74/training-lab
main
```

Do not use the old `bigriversocial74/contactform/examples/labs/` path as the active source of truth unless David explicitly says the work is being moved back into `contactform`.

## Current cleanup direction

The repo should be cleaned before new feature work continues. The cleanup goal is documentation and inventory only:

- Root README.
- Safe database boundary doc.
- Asset/template inventory doc.
- Template folder README.
- Clear distinction between Training Lab assets and Loyalty Quest assets.

## Do not mix in

- Loyalty Quest template images.
- Old nested paths from `contactform`.
- Generated duplicate folders.
- Old SQL files unless they are clearly marked as archive or do-not-run.

## Development rules

1. Create branches from `main`.
2. Keep changes scoped.
3. Do not add authentication gates to active pages unless specifically requested.
4. Do not move or overwrite working config without explicit approval.
5. If SQL is needed, include a clearly named SQL file and document whether it is import-safe.
6. Open PRs back into `main`.

## Review checklist

Before merge, confirm:

- No Loyalty Quest images were introduced as Training Lab template assets.
- No old `contactform/examples/labs` paths are treated as active repo paths.
- No live payment/account-balance/claim behavior was enabled.
- SQL docs identify safe vs archive/do-not-run scripts.
