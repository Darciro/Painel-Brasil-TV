# Deploy to DigitalOcean via GitHub Actions

[.github/workflows/deploy.yml](.github/workflows/deploy.yml) builds the
image, pushes it to GitHub Container Registry (ghcr.io), then SSHes into a
DigitalOcean Droplet to pull and restart it — triggered on every push to
`main` (i.e. after a PR is merged, never on the PR itself, so secrets are
never exposed to unreviewed code).

## One-time setup

### 1. Droplet: deploy directory + `.env`

```bash
ssh <user>@<droplet-ip>
mkdir -p /opt/pbtv
```

Create `/opt/pbtv/.env` on the droplet with production values (same shape
as [.env.example](.env.example) — copy it over and fill in real secrets).
This file is never touched by CI; it only exists on the server.

### 2. Droplet: dedicated deploy SSH key

From your machine (not the droplet):

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f pbtv_deploy_key -N ""
ssh-copy-id -i pbtv_deploy_key.pub <user>@<droplet-ip>
```

This adds the public key to `~/.ssh/authorized_keys` for `<user>` on the
droplet. `<user>` needs permission to run `docker`/`docker compose`
(member of the `docker` group).

### 3. GitHub repo secrets

Repo → Settings → Secrets and variables → Actions → New repository secret:

| Secret | Value |
| --- | --- |
| `DO_HOST` | Droplet IP or hostname |
| `DO_SSH_USER` | The SSH user from step 2 |
| `DO_SSH_KEY` | Contents of `pbtv_deploy_key` (the **private** key) |
| `DO_SSH_PORT` | SSH port, usually `22` |

`GITHUB_TOKEN` (used to push to ghcr.io) is automatic — no secret needed.

### 4. First run and package visibility

Merge a PR into `main` (or push directly) to trigger the workflow once.
This creates the ghcr.io package. By default it's **private**, which means
the droplet's plain `docker compose pull` (no login) will fail. Go to:

`https://github.com/Darciro?tab=packages` → the `painel-brasil-tv` package →
Package settings → Change visibility → **Public**.

The image only contains theme/plugin code, not secrets (those are injected
at container start via `.env`), so making it public is safe. If you'd
rather keep it private, add a `docker login ghcr.io` step with a
classic PAT (`read:packages` scope) stored as another secret before the
`pull` in the workflow's SSH script.

## How it works on every push to `main`

1. `build-and-push` builds the image from the repo's [Dockerfile](Dockerfile)
   and pushes it to `ghcr.io/darciro/painel-brasil-tv` tagged `latest` and
   with the commit SHA.
2. `deploy` copies [docker-compose.prod.yml](docker-compose.prod.yml) to
   `/opt/pbtv` on the droplet, then over SSH pulls the SHA-tagged image and
   runs `docker compose up -d`.

`docker-compose.prod.yml` has no `build:` key — the droplet never builds,
it only pulls what CI already built. `db` and `wp_uploads` persist across
deploys via named volumes, same as in local dev.

## Rollback

SSH into the droplet and point `WORDPRESS_IMAGE` at a previous commit SHA:

```bash
cd /opt/pbtv
export WORDPRESS_IMAGE=ghcr.io/darciro/painel-brasil-tv:<previous-sha>
docker compose -f docker-compose.prod.yml up -d
```
