---
paths:
  - 'deploy/**'
---

# Deploy

## The deploy Dockerfile may only COPY paths that are committed
The build context is a clone, not your working tree. A `COPY` of something gitignored works on the machine that wrote it and dies for everyone else with `failed to compute cache key: "/x": not found`, which reads like a Docker problem rather than a missing file.

`COPY .yarn ./.yarn` did exactly this: `.gitignore` has `/.yarn/*` with exceptions only for `patches`, `plugins`, `releases` and `versions`, none of which exist, so the directory is absent from a clone. It was removed. Corepack is enabled earlier in the image and takes the version from `packageManager` in `package.json`, which is all Yarn 4 needs. Put the COPY back only if a pinned release or a patch is ever committed under `.yarn/`.

Check before adding a COPY: `git ls-files <path>` must print something. Verify a change with `docker build -f deploy/Dockerfile -t eveil-build-test .` from the repo root, which skips the compose interpolation that otherwise demands a filled `.env`.
