# Training Lab Asset Inventory

This document prevents Training Lab assets from being confused with assets from other Microgifter scripts.

## Current rule

Do not treat Loyalty Quest template images as Training Lab template images.

The Loyalty Quest images belong to the Loyalty Quest script and should not be used as Training Lab evidence, placeholder proof, or template inventory unless David explicitly requests that they be moved or copied.

## Asset folder expectations

Training Lab assets should be documented by folder and purpose.

Recommended structure:

```txt
assets/
  images/
  templates/
  screenshots/
templates/
  README.md
```

## Required asset documentation

For each real Training Lab template asset, document:

- File path.
- Purpose.
- Whether it is final, placeholder, mockup, or archive.
- Source/owner.
- Whether it came from Training Lab, Loyalty Quest, or another Microgifter module.

## Placeholder policy

Placeholder images are allowed only when clearly labeled as placeholders. Do not use placeholder images to claim a template is complete.

## Cleanup checklist

During cleanup, identify:

- Images copied from Loyalty Quest.
- Duplicate mockup images.
- Generated images that are not tied to a Training Lab template.
- Screenshots that should live in docs rather than production asset folders.
- Unused images that should move to an archive folder.
