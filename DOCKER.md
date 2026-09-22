# Running with Docker

The stack has two services, defined in [docker-compose.yml](docker-compose.yml):

- `wordpress` — built from the [Dockerfile](Dockerfile). WordPress core comes
  from the official `wordpress:php8.3-apache` image; the `pbtv` theme and the
  bundled plugins (Akismet, Hello Dolly) are baked in from this repo, and the
  theme's CSS is rebuilt from source during the image build.
- `db` — `mysql:8.0`, with data persisted in the `db_data` volume.

Uploaded media is persisted separately in the `wp_uploads` volume.

## First run

```bash
cp .env.example .env
# edit .env: set DB_PASSWORD, DB_ROOT_PASSWORD, and PBTV_YOUTUBE_API_KEY

docker compose up -d --build
```

Visit `http://localhost:8080` (or `WP_PORT` from `.env`) and complete the
WordPress install wizard, then activate the `pbtv` theme under
Appearance → Themes.

## Notes

- `.env` is gitignored — never commit it. `.env.example` documents the
  variables without real secrets.
- `PBTV_YOUTUBE_API_KEY` is injected into `wp-config.php` at container start
  via `WORDPRESS_CONFIG_EXTRA` (see [wp-content/themes/pbtv/inc/youtube.php](wp-content/themes/pbtv/inc/youtube.php)
  for how it's consumed) — it is never baked into the image.
- `wp-content/themes/pbtv` is bind-mounted from the repo into the container,
  so PHP edits show up on a browser refresh — no rebuild needed. The bundled
  plugins are still baked into the image and reset to what's in this repo on
  every rebuild; only the database and `wp-content/uploads` persist across
  `docker compose down`.
- Theme CSS/JS is still built at image build time (`npm run build`), so after
  editing `src/` assets you need either `docker compose up -d --build` or a
  local Vite dev server (`npm run dev` in `wp-content/themes/pbtv`) targeting
  the container.
- To pick up plugin changes, rebuild: `docker compose up -d --build`.
