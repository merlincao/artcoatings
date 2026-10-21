# ArtCoatings

An independent directory of art coatings brands, manufacturers and suppliers —
decorative, mineral & lime, microcement and metallic systems across Italy and Europe.
Based in Sicily, Italy.

## What is in this repository

| Path | Purpose |
|---|---|
| `html/` | **The published static site.** This folder *is* the site root — there is no `/html/` level online. |
| `site/` | Local WordPress installation. CMS only, never deployed. |
| `tools/` | Pipeline scripts: Excel extraction, scraping, import, i18n, WebP conversion, static export, link checks. |

## How the site is built

WordPress is used **only as a local content management environment**. It is never
exposed to the internet. The live site is a set of static files:

```
Edit content in local WordPress (localhost:8080)
        ↓
php tools/static-export.php --clean      →  generates html/
        ↓
git push  →  Cloudflare Pages
```

**Cloudflare Pages setting:** Build output directory = `html`.

All asset references are emitted as *relative* paths, so the contents of `html/`
work identically at a domain root or in any subdirectory.

## Common commands

```bash
php tools/static-export.php --clean      # wipe html/ then export everything (recommended)
php tools/build-seo.php --base https://artcoatings.org   # sitemaps + robots + llms.txt + webmanifest
python tools/check-links.py              # audit: broken links, absolute URLs, empty files, Chinese text, non-WebP images
php tools/convert-webp.php --apply       # convert source images to WebP
php tools/translate-to-en.php --apply    # translate content to English
php tools/attach-images.php --force      # re-attach company images
php tools/fix-rewrite.php                # repair /news-cat/* archives (run twice; see below)
bash deploy.sh                           # export + commit + push in one go
```

### News pipeline (BBC-style rewrites)

```bash
python tools/parse_news.py                                   # news_bbc_*.txt -> data/news_bbc.json
python tools/scrape_news_images.py --proxy http://127.0.0.1:7890   # pull og:image from each source URL
php  tools/convert-news-webp.php                             # -> data/news_images/*.webp (quality 82, long edge 1600)
php  tools/import-news.php                                   # upsert ac_news posts, idempotent by slug
php  tools/add-editorial-page.php                            # create/update /editorial-policy/
```

### SEO / AI-visibility files (generated into `html/`)

`sitemap.xml` (index) · `sitemap-pages.xml` · `sitemap-news.xml` (Google News schema) ·
`sitemap-images.xml` · `robots.txt` (explicit allow for GPTBot / ClaudeBot / PerplexityBot /
Google-Extended / Applebot-Extended …) · `llms.txt` · `llms-full.txt` ·
`manifest.webmanifest` · `humans.txt`.

> `fix-rewrite.php` exists because `category_base` was once set to `news-cat`, which
> collides with the `ac_news_cat` rewrite slug and makes every `/news-cat/<slug>`
> archive 404. `update_option()` and `flush_rewrite_rules()` must run in **separate
> requests** — the taxonomy is already registered with the old base by the time you
> flush — so run the script twice.

> Do **not** use `rm -rf html` from the shell — bulk deletion of hundreds of files is
> blocked by the safety policy. Use the exporter's `--clean` flag instead.

## Conventions

- **Language:** the site is English-only. The WordPress core installed here is the
  Chinese package, so `WPLANG` is pinned to `en_US` in `wp-config.php`; without it
  `<html lang>` renders as `zh-CN`.
- **Images:** WebP only.
- **Language switcher:** the navigation offers English / Français / Italiano /
  Deutsch / Português / Ελληνικά. It is a client-side implementation
  (`assets/js/i18n.js`, `window.changeLanguage(code)`) that translates interface
  copy; company data — brand names, addresses, product descriptions — is intentionally
  left in its original form.

## Contact

xzmcart@hotmail.com
