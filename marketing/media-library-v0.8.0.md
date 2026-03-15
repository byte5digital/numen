# Media Library & Digital Asset Management — Launch Marketing Copy

_Prepared by Herald 📣 | Numen AI-First CMS | 2026-03-15_

---

## 1. GitHub Discussion Announcement Post

**Title:** 🎬 Shipped: AI-Powered Media Library & Digital Asset Management (v0.8.0)

---

We just landed the full Media Library & DAM system into `dev` and cut v0.8.0. This is a complete rethinking of how you manage media in a headless CMS — AI-native, self-hosted, and built into your content pipeline from day one.

### The problem we were solving

Most CMSs treat media as an afterthought. You get a folder tree, maybe some basic upload, and that's it. Want CDN-optimized variants? Build it yourself. Want to know which images are actually in use? Good luck grepping your content. Want automatic tagging so your asset library is discoverable? That'll be a third-party SaaS service you manage separately.

We saw teams paying Cloudinary $200+/month for features they only half-used, or maintaining custom S3 pipelines because their CMS media library was a joke. Meanwhile, they're running Numen for AI-powered editorial workflows but still handling media like it's 2015.

We built this differently: **a media library that thinks**.

### What shipped

**Folders & Collections**

Organize assets into hierarchical folder structures and flat collections. Move assets between folders with drag-and-drop. Collections group related assets across folder boundaries — perfect for campaigns, client deliverables, or editorial themes. Both are fully queryable via the API.

**Drag-and-Drop Upload**

Drop files anywhere in the admin. Batch upload multiple files at once. Progress tracking in real-time. No fussing with forms.

**AI Auto-Tagging via Claude Vision** ← this is the differentiator

When you upload an image, Numen automatically analyzes it with Claude's vision capabilities and suggests tags: content, objects, mood, scene type, and technical attributes (orientation, color scheme, composition). Confidence scores included. You can accept, reject, or customize tags before they're persisted. Tag suggestions work for PNG, JPG, WebP — any image your browser can display.

Tags become filterable metadata in your asset library and queryable from the headless API. No more hunting through filenames trying to find "that beach sunset from the Q2 campaign."

**Image Editing** — Crop, Rotate, Resize

Edit images inline without leaving the admin:
- Crop with aspect ratio presets (16:9, 4:3, 1:1, square)
- Rotate 90°, 180°, 270°
- Resize to specific dimensions or percentages

Changes are non-destructive: original stays archived, the edited variant is created as a new asset. You can always roll back.

**Automatic Variant Generation**

Upload once, get three optimized variants automatically:
- **Thumbnail** (300×300, web-optimized JPEG/WebP)
- **Medium** (1200×800, standard web display)
- **Large** (2560×1600, high-quality delivery)

Variants are generated on upload, stored separately, and served from CDN with unique URLs. No runtime resizing, no performance penalties.

**Usage Tracking**

See exactly where each asset is used across your content. Which articles link to that product photo? Which landing page is using that hero image? The library tells you. Prevents accidental deletion of in-use assets and surfaces unused media for cleanup.

**Public Headless API — CDN-Ready URLs**

`/v1/public/media/{asset_id}/{variant_slug}.{ext}`

Every asset and variant gets a public, cacheable URL. Variants are named predictably (`thumbnail`, `medium`, `large`, or custom). URLs are stable — change asset metadata and the URLs don't break. Perfect for CDN edge caching and long-lived client-side references.

Full REST API with Sanctum auth for management operations: CRUD on assets, tag assignment, folder operations, usage reports.

**MediaPicker Vue Component**

Seamless content editor integration. Insert the `<MediaPicker />` component in your content form, and editors can browse, search, filter by tags, and insert asset URLs directly into their content. No context-switching, no separate admin tab.

Variant picker included: choose which variant to insert (thumbnail, medium, large, or original).

**Metadata Extraction & Visibility**

Automatic on upload:
- Filename and extension
- MIME type
- Dimensions (width × height)
- File size
- Upload timestamp and uploader

All stored and queryable. Useful for filtering ("show me all images larger than 1920×1080") and for API consumers who need to know asset properties before rendering.


### How to use it

**Upload & organize:**

1. Go to admin → Media Library
2. Create folders if you want hierarchy (`Campaign Assets > Q1 2026`)
3. Drag files into the library
4. AI auto-tags your images — review and finalize
5. Variants are generated automatically

**Use in content:**

6. In your content editor form, add the `<MediaPicker />` component
7. Editors search/browse/filter and insert asset URLs
8. Usage is tracked automatically

**Programmatic access:**

```bash
# Fetch all assets in a folder
curl "http://your-site/api/v1/media?folder_id=abc123"

# Get an asset with all variants
curl "http://your-site/api/v1/media/asset-uuid"

# Get usage report for an asset
curl "http://your-site/api/v1/media/asset-uuid/usage"

# Public CDN URL (no auth required)
curl "https://your-site/v1/public/media/asset-uuid/medium.webp"
```

Full docs: `docs/features/media-library.md`

---

### Why this matters for your team

**For Content Teams:**
- Upload once, get three production-ready variants automatically.
- AI tags your images so they're discoverable later without manual metadata entry.
- Usage tracking prevents "Wait, who's using this?" drama.

**For Agencies:**
- Self-hosted means no per-asset fees, no SaaS bill surprises.
- Clients own their media. Not locked into Cloudinary. Not dependent on a third party.
- Headless API makes it easy to integrate media into any frontend framework.

**For Developers:**
- Predictable CDN URLs for variants — easy to cache, easy to reference.
- Comprehensive metadata in the API — build powerful search and filtering.
- MediaPicker component saves you from building custom asset selection UI.
- WebP generation included. Responsive image sets are trivial.

---

### Competitive angles

**vs Cloudinary:**
- Cloudinary bills by assets, transformations, and storage. $10–200+/month depending on volume.
- Numen is self-hosted — one deployment cost, unlimited uploads and transforms.
- Cloudinary is an external dependency — if their API is down, your images break.
- Numen lives in your infrastructure.

**vs WordPress Media Library:**
- WordPress has folders, but no AI tagging, no variant generation, no usage tracking.
- Numen auto-generates responsive variants on upload. WordPress doesn't.
- WordPress Media Library isn't an independent service — it's tightly coupled to WordPress posts.
- Numen is headless — works with any frontend, any CMS.

**vs Strapi Media Library:**
- Strapi has upload and folders, but no AI auto-tagging.
- Strapi doesn't auto-generate variants or provide CDN-optimized URLs.
- Numen's usage tracking prevents accidental deletion of in-use assets — Strapi doesn't track that.
- Numen integrates with our AI pipeline so media metadata is part of your editorial workflow.

**vs Custom S3 Setup:**
- Custom S3 is cheap but requires you to build: upload handling, variant generation, URL routing, usage tracking, admin UI.
- Numen gives you all that out of the box.
- No custom code, no maintenance, no DevOps overhead.

---

### What's next

- Bulk variant regeneration (re-process all assets with new presets)
- Custom variant presets (define your own sizes, formats, quality)
- AI-powered image generation (create variant images from text prompts using DALL-E/FLUX)
- Detailed analytics: storage used, bandwidth, popular assets
- Integration with Zapier/Make for asset workflows

---

If you hit edge cases or want to contribute enhanced variant presets or bulk operations, post them here. This is the foundation — we're building on it fast.


---

## 2. Key Messaging Points (Social / Landing Page)

**For use as bullet points, feature cards, or social copy — pick and mix.**

- 🤖 **AI that tags your images automatically** — Upload a photo, Numen analyzes it with Claude vision and suggests tags. Content, objects, mood, composition. Confidence scores included. No manual metadata work at scale.

- 📦 **Automatic variant generation on upload** — One image becomes three: thumbnail, medium, large. All optimized for web, all stored separately, all served with stable URLs. No resize-on-demand, no performance tax.

- 🔗 **CDN-ready public URLs** — Every asset and variant gets a permanent, cacheable URL: `/v1/public/media/{asset}/{variant}.webp`. Cache forever, zero auth required, perfect for static CDN edges.

- 📊 **Usage tracking built in** — Know exactly where each asset is used across your content. Prevents accidental deletion. Surfaces unused media for cleanup.

- 🎬 **Headless MediaPicker component** — Drop it into any content form. Editors browse, search, filter by tags, insert URLs. No context-switching, no separate admin tab.

- 📁 **Folders and collections** — Organize by hierarchy or by theme. Both are queryable. No more guessing where that campaign asset went.

- 🔐 **Self-hosted, your data** — Not locked into Cloudinary, not paying per-asset fees. One deployment, unlimited uploads and transforms. Media lives in your infrastructure.

---

## 3. Changelog Entry

### [0.8.0] — 2026-03-15

#### Added — Media Library & Digital Asset Management

- **Media organization** — Hierarchical folder structures and flat collections for asset grouping. Drag-and-drop folder management. Move assets between folders with one click.

- **Drag-and-drop upload** — Batch upload multiple files at once with real-time progress tracking. No forms, no friction.

- **AI auto-tagging via Claude Vision** — Images are automatically analyzed on upload and tagged with content, objects, mood, scene type, composition, and technical attributes. Confidence scores. Manual override supported. Tags are filterable in admin and queryable via API.

- **Image editing suite** — Crop (with aspect ratio presets), rotate, and resize images inline without leaving the admin. Non-destructive: original stays archived, edits are new variants.

- **Automatic variant generation** — Three variants generated on upload: thumbnail (300×300), medium (1200×800), large (2560×1600). All optimized for web (JPEG/WebP). Stored separately with unique CDN URLs.

- **Metadata extraction** — Filename, MIME type, dimensions, file size, upload timestamp automatically captured and queryable.

- **Usage tracking** — See which content pieces reference each asset. Prevents accidental deletion of in-use media. Enables cleanup workflows.

- **Public headless API** — RESTful access to assets, folders, collections, and usage reports. Sanctum authentication for management operations.

- **CDN-ready URLs** — Permanent, cacheable URLs for all assets and variants: `/v1/public/media/{asset_id}/{variant_slug}.{ext}`. No authentication required for public delivery.

- **MediaPicker Vue component** — Content editor integration. Browse, search, filter, and insert asset URLs directly in content forms. Variant selection included.

- **Media admin dashboard** — Storage usage, upload volume, upload trends, unused asset identification.


---

## 4. Platform Copy Stubs

### Twitter/X Thread

**Tweet 1 (Hook):**
Most CMSs still treat media like it's an afterthought: folders, upload, done. No AI tagging, no variant generation, no idea which images you're actually using.

We shipped something different.

**Tweet 2 (Problem):**
Teams pay Cloudinary $200+/month for features they half-use, or maintain custom S3 pipelines because their CMS media library isn't fit for production.

Meanwhile they're running headless CMS + AI pipeline + entire editorial workflow... and managing media in 2015 mode.

**Tweet 3 (Solution intro):**
Numen v0.8.0: Media Library that thinks.

Drop an image. AI auto-tags it (content, objects, mood, composition, scene). Three variants generated automatically (thumbnail, medium, large). Stable CDN URLs. Usage tracked.

**Tweet 4 (Features):**
- Claude Vision auto-tagging with confidence scores
- One upload → three optimized variants instantly
- Permanent, cacheable public URLs for CDN
- See exactly where each asset is used
- MediaPicker component for seamless editor integration
- Self-hosted (your data, no SaaS fees)

**Tweet 5 (Developer angle):**
No custom resize-on-demand code. No managing transforms. No per-asset billing. Upload once, variants exist, API returns stable URLs, cache forever.

**Tweet 6 (Audience):**
For content teams managing 100+ images/month. For agencies building media-heavy products. For anyone tired of Cloudinary's bill or WordPress media's limitations.

**Tweet 7 (CTA):**
GitHub Discussions: numen/numen · Media Library v0.8.0
Docs: docs/features/media-library.md
Self-hosted. Open source. Your media, your infrastructure.


---

### LinkedIn Post

**Headline:** Shipping a Media Library that Actually Works

**Body:**

Most content teams manage media the way we managed email in 2000: folders, folders, more folders. Manually tagging images takes hours. Finding which content uses which asset takes archaeology.

SaaS solutions like Cloudinary charge per-asset and per-transformation. Self-hosted frameworks like WordPress Media Library lack the smarts to make media discoverable or variant generation automatic.

We built Numen's Media Library differently.

**What shipped:**

✅ AI auto-tagging via Claude Vision
- Upload an image. Numen analyzes it and suggests tags: content, objects, mood, composition, technical attributes.
- Confidence scores. Manual override. Queryable by tags.

✅ Automatic variant generation
- One upload becomes three production-ready variants: thumbnail, medium, large.
- Optimized for web. CDN-ready URLs. No resize-on-demand tax.

✅ Usage tracking
- Know where each asset is used across your content library.
- Prevent accidental deletion. Identify unused media for cleanup.

✅ Headless MediaPicker component
- Editors insert assets directly from their content form.
- Browse, search, filter by tags. No context-switching.

✅ Self-hosted, your data
- No per-asset fees. No SaaS dependency. Media lives in your infrastructure.

**The result:** Content teams upload faster. Campaigns are organized without manual metadata work. Assets are discoverable. Developers get stable, cacheable URLs for every variant.

We're taking the pain out of media at scale.

Read more: [GitHub Discussions link]


---

### GitHub Discussions Post (Alternative, more technical)

**Title:** Announcing Media Library v0.8.0: AI-Powered DAM Built Into Numen

We've shipped the full Media Library & Digital Asset Management system. This post covers what landed, why it matters, and how to use it.

**Problem statement:**

Asset management in most CMS platforms falls into two categories:
1. **Basic (WordPress, Ghost):** Folders and upload. No metadata intelligence, no variant generation, no usage tracking.
2. **Specialized SaaS (Cloudinary, imgix):** Powerful but external, expensive per-asset, and another third-party dependency you manage separately from your CMS.

Content teams building with Numen wanted something different: AI-native media organization that's integrated into the content pipeline, self-hosted, and doesn't charge per-asset.

**What shipped:**

All the features documented in the announcement post above, with REST API examples and component usage.

**API Examples:**

```bash
# List assets in a folder
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/v1/media?folder_id=abc123&limit=50"

# Get asset with all metadata and variants
curl "http://localhost:8000/api/v1/media/asset-uuid"

# Response includes asset metadata, tags with confidence scores, and variant URLs

# Get usage report
curl "http://localhost:8000/api/v1/media/asset-uuid/usage"

# Upload a new asset (with auth)
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@image.jpg" \
  -F "folder_id=abc123" \
  "http://localhost:8000/api/v1/media/upload"
```

**For content editors:**

Drop the `<MediaPicker />` component into your content form. Editors browse, search, filter by tag or folder, and insert URLs. No backend glue required.

**Migration notes:**

If you have existing media in WordPress or another system, you can bulk import using the REST API.

**What's next:**

- Custom variant presets
- Bulk variant regeneration
- Image generation from prompts (DALL-E/FLUX)
- Storage and bandwidth analytics
- CDN provider exports

---

### HackerNews "Show HN" Post

**Title:** Show HN: Numen Media Library – AI-Powered DAM for Headless CMS

**Text:**

We shipped a Media Library & Digital Asset Management system for Numen (open source, self-hosted AI-first headless CMS).

The idea: media management shouldn't require a separate SaaS service. Drop an image, get AI auto-tags, three variants (thumbnail/medium/large) generated automatically, stable CDN-ready URLs, usage tracking, and a headless API.

**Key features:**

- Claude Vision auto-tagging on upload (content, objects, mood, composition, confidence scores)
- Automatic variant generation: one upload → three optimized variants stored separately
- Stable, cacheable public URLs: `/v1/public/media/{id}/{variant}.webp`
- Usage tracking: know which content pieces reference each asset
- MediaPicker Vue component for content editor integration
- Headless REST API with full CRUD and search
- Self-hosted (no per-asset fees, no Cloudinary dependency)

**Why we built it:**

Most teams either use a barebones media library (WordPress) or pay Cloudinary/imgix $100+/month for features they could self-host. We wanted media management that's part of the content pipeline, AI-native, and integrated into the CMS from day one.

**Tech stack:**

- Laravel backend with event-driven pipeline
- Vue 3 admin UI
- Claude Vision for auto-tagging
- WebP/JPEG variant generation (Intervention Image)
- Laravel Sanctum for API auth

**Links:**
- GitHub: [project link]
- Docs: [docs link]
- MIT licensed

Feedback welcome. If you've worked with Cloudinary or custom S3 pipelines and have thoughts, I'm curious.


---

## 5. Competitive Positioning Summary

| Aspect | Numen Media Library | Cloudinary | WordPress Media | Strapi Media | Custom S3 |
|--------|-------------------|-----------|-----------------|-------------|----------|
| **Auto-tagging** | Claude Vision ✅ | Manual or add-on | No | No | You build it |
| **Variant generation** | Automatic on upload | On-demand (billable) | Manual via plugin | No | You build it |
| **CDN-ready URLs** | Built-in public API | Built-in | No | No | You build it |
| **Usage tracking** | Built-in | No | No | No | You build it |
| **MediaPicker component** | Included | External | External | External | You build it |
| **Self-hosted** | ✅ Yes | No (SaaS) | Yes (with plugin) | ✅ Yes | ✅ Yes |
| **Per-asset billing** | No | Yes ($0.10–1.00+/asset) | No | No | No |
| **Headless API** | ✅ REST + public CDN | ✅ REST + CDN | ✅ REST | ✅ REST | ✅ Custom |
| **Integrated with CMS** | ✅ Numen pipeline | External dependency | ✅ WordPress | ✅ Strapi | You integrate |
| **Total cost (1000 assets)** | Hosting only (~$20–50/mo) | $100–300+/mo | Hosting only | Hosting only | Hosting + dev time |

---

## 6. Recommended Outreach

**Announce in these channels:**

1. **GitHub Discussions** (numen/numen) — Primary audience: developers, CMS users
2. **Dev.to** — "Building a Media Library That Thinks" (longer-form breakdown)
3. **Hacker News Show HN** — "Media Library for Open Source CMS"
4. **Reddit** — r/webdev, r/headlesscms, r/laravel
5. **LinkedIn** — Content/media teams, DevRel, agency directors
6. **Discord** — Numen community server, mention in #announcements

---

**Prepared by:** Herald 📣 | Numen Marketing  
**Date:** 2026-03-15  
**Version:** v0.8.0  
**Status:** Ready for publish

