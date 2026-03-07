# Content Versioning & Drafts — Marketing Messaging

> Herald 📣 | Numen Marketing | 2026-03-07

---

## 1. Tagline

**"Every version. Every AI decision. Fully auditable."**

Alternatives:
- "Version history that doesn't stop at human edits."
- "Content versioning for teams that ship with AI."
- "Draft it, diff it, ship it — humans and AI, one history."

---

## 2. Feature Announcement Copy (README / Changelog)

### Short Version (README badge section)

Numen now ships a complete content versioning system. Auto-save drafts protect your work in progress. Named versions let you label milestones — "v1.0 Launch", "Post-Legal Review", "SEO Refresh Q2" — and snap back to any of them. A side-by-side diff viewer shows exactly what changed between any two versions, down to the word.

But the real differentiator is AI pipeline provenance. When Numen's AI agents generate or optimize content, every change is tracked as a first-class version event — attributed to the specific agent, with the model, token count, and cost recorded. You can diff AI output against the human original, roll it back, or audit the entire chain. No other open-source CMS does this.

Scheduled publishing, version branching, two-step rollback, and soft-locking round out the system. It's the versioning layer a production CMS needs — built for teams where humans and AI collaborate on content.

### Changelog Entry

```markdown
### Added — Content Versioning & Draft System
- **Auto-save drafts** — per-user ephemeral buffers with 2-second debounce; never lose work
- **Named versions** — label snapshots for easy reference ("v1.0 Launch Copy")
- **Side-by-side diff** — word-level and line-level comparison between any two versions
- **Two-step rollback** — restore any version as a draft, review it, then publish
- **Scheduled publishing** — set future publish date/time; dual mechanism (queue + cron safety net)
- **Version branching** — work on the next version while the current one stays live
- **AI pipeline versioning** — each pipeline run creates a tracked version with full provenance
- **AI provenance metadata** — model name, tokens, cost, stages, and brief ID per AI version
- **Soft-locking** — prevents concurrent edits (HTTP 423 when locked)
- **Space-scoped authorization** — editors see only their own space's content
- **Rate limiting** — tiered limits (60/min read, 30/min write, 30/min auto-save)
- **Version API** — full CRUD via `/api/v1/content/{id}/versions/*`
```

---

## 3. Feature Highlights (Landing Page / Feature Comparison)

### Primary Highlights

| Feature | What It Does |
|---|---|
| 📝 **Auto-Save Drafts** | Saves every 2 seconds to a personal buffer. Close your browser mid-sentence — your work is waiting when you come back. |
| 🏷️ **Named Versions** | Label milestones: "Launch Copy", "Post-Legal", "SEO Refresh". Find the version you need in a 50-version timeline without guessing. |
| 🔍 **Side-by-Side Diff** | Word-level diffs for titles and excerpts. Line-level diffs for body content. Visual cards for structural block changes. |
| ⏪ **Two-Step Rollback** | Restore creates a draft — not a live publish. Review before it goes out. Undo the undo if you change your mind. History is never lost. |
| 📅 **Scheduled Publishing** | Pick a date and time. Dual delivery: queue job fires at the exact moment, cron safety net catches anything the queue misses. |
| 🌿 **Version Branching** | Start the redesign while the current version stays live. Multiple branches, multiple editors, zero conflicts. |
| 🤖 **AI Pipeline Provenance** | Every AI-generated version carries: which models ran, how many tokens, what it cost, which pipeline stages completed. Diff AI output against the human original. |
| 🔒 **Soft-Locking** | One editor at a time. HTTP 423 if someone else is editing. 15-minute stale lock threshold. Force-release for admins. |
| 🛡️ **Space Isolation** | Editors see only their space's content. Admins see everything. Cross-space access returns 403. |

### Bullet List (Compact — for feature comparison tables)

- ✅ Auto-save drafts (per-user, 2s debounce)
- ✅ Named versions with labels
- ✅ Side-by-side diff (word + line level)
- ✅ Two-step rollback (draft → review → publish)
- ✅ Scheduled publishing (queue + cron dual mechanism)
- ✅ Version branching (parallel draft trees)
- ✅ AI pipeline provenance (model, tokens, cost per version)
- ✅ Soft-locking with HTTP 423
- ✅ Space-scoped authorization
- ✅ Rate-limited versioning API
- ✅ Event system (ContentPublished, ContentRolledBack, etc.)

---

## 4. Competitive Positioning

### Positioning Matrix

| Capability | **Numen** | WordPress | Strapi | Contentful | Sanity | Payload CMS |
|---|---|---|---|---|---|---|
| Version history | ✅ Full | ✅ Revisions | ✅ Draft/Publish | ✅ Snapshots | ✅ History | ✅ Drafts + Versions |
| Named versions | ✅ Labels | ❌ | ❌ | ❌ | ❌ | ❌ |
| Auto-save drafts | ✅ Per-user buffers | ✅ Auto-save | ❌ Manual only | ❌ | ✅ Real-time | ✅ Auto-save |
| Side-by-side diff | ✅ Word + line level | ⚠️ Plugin | ❌ | ✅ Basic | ✅ Basic | ❌ |
| Two-step rollback | ✅ Draft-then-publish | ❌ Instant | ❌ Instant | ❌ Instant | ❌ Instant | ❌ Instant |
| Scheduled publishing | ✅ Queue + cron | ✅ Built-in | ⚠️ Plugin / custom | ✅ Built-in | ⚠️ Custom | ⚠️ Custom |
| Version branching | ✅ Built-in | ❌ | ❌ | ❌ | ❌ | ❌ |
| AI provenance tracking | ✅ Full metadata | ❌ | ❌ | ❌ | ❌ | ❌ |
| Soft-locking | ✅ HTTP 423 | ❌ | ❌ | ⚠️ Optimistic | ⚠️ Real-time | ❌ |
| Open source | ✅ MIT | ✅ GPL | ✅ MIT (v4→Enterprise) | ❌ Proprietary | ❌ Proprietary | ✅ MIT |

### Key Differentiators (Narrative)

**1. AI-Native Versioning — Nobody Else Does This**

Every other CMS treats AI-generated content as if a human wrote it. There's no attribution, no cost tracking, no way to tell which model rewrote your headline. Numen versions every AI pipeline run as a distinct event with full provenance: model name, token count, dollar cost, pipeline stage, and the brief that triggered it. When regulators ask "which AI touched this content?", Numen has the answer. Nobody else does.

**2. Two-Step Rollback — Safer Than Everyone Else**

Strapi, Contentful, Sanity, Payload — they all do instant rollback. Click "restore" and the old version is live. That's fast, but it's dangerous. One misclick pushes stale content to production. Numen's two-step rollback creates a draft first. You review it, tweak it if needed, then publish deliberately. It's one extra step that prevents production incidents.

**3. Version Branching — Rare in Open-Source CMS**

Want to prep next quarter's content while the current version stays live? In WordPress, you'd duplicate the post. In Strapi, you'd hack around it. In Numen, you click "Branch" and get a parallel draft tree. Multiple editors can work on different branches simultaneously. When you're ready, publish the branch — the current version archives automatically.

**4. Named Versions — Surprisingly Nobody Does This Well**

Every CMS gives you a list of timestamps. Numen lets you name your versions: "v1.0 Launch Copy", "Post-Legal Approved", "SEO Refresh Q2". When your content has 30 versions, names beat timestamps every time.

**5. Scheduled Publishing with a Safety Net**

WordPress has cron-based scheduling. Contentful has it built in. Numen uses a dual mechanism: a queue job fires at the exact scheduled time, and a cron safety net runs every minute to catch anything the queue missed. You get the precision of queues and the reliability of cron. Belt and suspenders.

### One-Paragraph Positioning Statement

Numen is the first open-source headless CMS with AI-native content versioning. While other platforms bolt on basic version history, Numen tracks every edit — human and AI — in a unified timeline with full provenance. Named versions, two-step rollback, visual diffs, version branching, and scheduled publishing give editorial teams the tools to ship confidently. AI pipeline versioning gives compliance teams the audit trail they need. Built on Laravel, open source under MIT.

---

## 5. Developer-Focused Messaging

### Why Developers Should Care

**Clean API surface.** Content versions are a first-class REST resource at `/api/v1/content/{id}/versions`. List, show, diff, rollback, schedule — all standard endpoints with consistent request/response patterns. No GraphQL-only lock-in, no proprietary SDK required.

**Injectable service, not a black box.** `VersioningService` is a plain Laravel service you inject via constructor. `createDraft()`, `publish()`, `rollback()`, `diff()`, `schedule()`, `branch()` — each method does one thing. Override behavior by swapping the service binding in the container.

**Event-driven architecture.** Every versioning action emits a typed event: `ContentPublished`, `ContentScheduled`, `ContentRolledBack`, `ContentVersionCreated`, `ContentDraftAutoSaved`. Hook into any of them for cache invalidation, webhook dispatch, Slack notifications, or custom workflows. Standard Laravel event listeners — no proprietary plugin API.

**Structured diffs, not string diffs.** The `DiffEngine` operates on typed content fields and `ContentBlock` records. Title diffs are word-level. Body diffs are line-level with context. Block diffs track additions, removals, and modifications by sort order. SEO fields diff key-by-key. The `VersionDiff` object serializes cleanly to JSON for frontend rendering.

**AI integration that's actually useful.** `PipelineVersioningIntegration` hooks into your AI pipeline runs and creates properly attributed versions. Each AI version carries a JSON `ai_metadata` blob: pipeline ID, run ID, stages completed, models used, total tokens, total cost. When you build dashboards or cost reports, the data is already there.

**No migration headaches.** Four additive migrations. No existing tables modified destructively. All new columns nullable or safely defaulted. Existing content works without changes. Optional backfill script for marking historical versions as published/archived.

### Developer Quick-Start

```php
// Inject the service
use App\Services\Versioning\VersioningService;

public function __construct(private VersioningService $versioning) {}

// Create a draft from the live version
$draft = $this->versioning->createDraft($content);

// Auto-save as the user types
$this->versioning->autoSave($content, $user, $request->validated());

// Name a version
$this->versioning->saveVersion($draft, 'v2.0 SEO Refresh');

// Publish
$this->versioning->publish($content, $draft);

// Schedule for later
$this->versioning->schedule($content, $draft, Carbon::parse('2026-04-01 09:00'));

// Compare two versions
$diff = $this->versioning->diff($versionA, $versionB);

// Rollback (creates draft — does NOT auto-publish)
$rollbackDraft = $this->versioning->rollback($content, $oldVersion);
```

### Developer Bullet Points (for docs landing page)

- **7 service methods** — createDraft, autoSave, saveVersion, publish, schedule, rollback, diff
- **5 domain events** — ContentPublished, ContentScheduled, ContentRolledBack, ContentVersionCreated, ContentDraftAutoSaved
- **Full REST API** — versioning endpoints follow standard resource patterns
- **SHA-256 content hashing** — fast duplicate detection without full-content comparison
- **Immutable version records** — history is append-only; rollback creates new versions
- **4 additive migrations** — zero breaking changes to existing data
- **1 new Composer dep** — `jfcherng/php-diff` (MIT licensed)

---

## 6. Message Framing by Audience

| Audience | Lead With | Key Message |
|---|---|---|
| **Content editors** | Auto-save + named versions | "Never lose work. Label what matters. See what changed." |
| **Editorial managers** | Scheduled publishing + rollback | "Ship on schedule. Roll back safely. Keep your team's history clean." |
| **CTOs / Engineering leads** | AI provenance + audit trail | "Know which AI changed what, when, and why. Full compliance audit trail." |
| **Developers** | Clean API + event system | "7 service methods, 5 events, standard REST. Build on it, don't fight it." |
| **DevOps / Platform** | Queue + cron dual mechanism | "Scheduled publishing that actually works. Belt and suspenders reliability." |
| **Open-source evaluators** | MIT license + no proprietary lock-in | "Real versioning in an open-source CMS. No enterprise paywall." |

---

*Prepared by Herald 📣 — Numen OSS Marketing*
*Complements: `versioning-release.md` (blog post, social, landing page assets)*
*Next steps: Façade 🏛️ for site implementation, Megaphone 📱 for social scheduling*
