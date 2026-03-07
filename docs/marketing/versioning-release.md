# Content Versioning & Draft System — Marketing Release Assets

> Herald 📣 | Numen Marketing | 2026-03-07

---

## 1. Feature Announcement Blog Post

**Title:** Numen Now Has Full Content Versioning — Including AI Pipeline Provenance

**Subtitle:** Every draft, every edit, every AI decision. Tracked. Visible. Reversible.

---

Content management without version history is like writing in permanent marker. One wrong move and you're repainting the wall. Today, we're shipping Numen's complete Content Versioning & Draft System — and we've gone further than any headless CMS has before.

### The Basics (Done Right)

Let's start with what you'd expect: auto-save drafts, named versions, one-click rollback. Numen continuously saves your work as you write, so you never lose a keystroke. When you hit a milestone, name it — "First draft," "Post-legal review," "Launch version" — and it's snapshotted permanently. Need to go back? One click. No friction.

The diff viewer shows you exactly what changed between any two versions, side-by-side. Text additions in green. Deletions in red. Structural changes highlighted. You'll see the evolution of your content at a glance.

**Scheduled publishing** lets you set it and forget it. Write your content today, schedule it for Tuesday at 9am. Numen handles the rest.

**Version branching** means multiple writers can work on variations of the same content — a localized version, an A/B variant, a seasonal edit — without clobbering each other's work.

This is table stakes for a serious CMS. We built it right.

### The Part That Changes Everything: AI Pipeline Provenance

Here's where Numen diverges from every other CMS on the market.

Numen runs content through AI pipelines — Brief → ContentCreator → SEO Optimizer → Editorial Review → Publish. At each step, an AI agent touches your content. And until now, every CMS treated those AI modifications as a black box. Content went in, content came out. You had no idea what changed, who changed it, or why.

We fixed that.

Every AI pipeline run is now a first-class version event. When the SEO agent rewrites your meta description, that change is tracked as its own version — attributed to the specific agent, with the reasoning logged. When Editorial flags a tone inconsistency and rewrites a paragraph, you see that. You can diff it. You can roll it back.

**Content provenance** is the term we're using, and we mean it seriously. For any piece of content in Numen, you can answer:

- What was the original human-authored text?
- Which AI agents touched it and in what order?
- What did each agent change, and what was its stated reason?
- Which version was published, and at what time?

This matters more than it might seem. As AI-assisted content becomes the norm, the ability to audit AI decisions — to see the reasoning, not just the result — becomes critical. Regulatory concerns, brand safety, editorial accountability: these all require an audit trail. Numen gives you that audit trail.

### For Teams

Version history is also a collaboration tool. When your content team, legal team, and AI pipelines are all touching the same document, knowing who did what and when is essential. Numen's versioning covers all of it — human edits and AI edits — in one unified history.

Named versions let you establish review checkpoints. "Sent to legal" is a version. "Legal approved" is a version. "AI SEO pass" is a version. Your workflow becomes legible.

### The Technical Reality

This is built on Numen's `ContentVersion` model with full branching support. Versions are immutable — we never mutate history. The diff engine handles structured content blocks, not just raw text, so you get meaningful diffs even for complex page layouts. AI pipeline versions carry full metadata: agent name, model version, prompt hash, reasoning, token counts.

For developers building on Numen's headless API: version history is exposed via `/api/v1/content/{id}/versions`. You can fetch any version, compare versions, and restore versions programmatically.

### Available Now

Content versioning is live. Auto-save, named versions, diff viewer, rollback, scheduled publishing, version branching, and AI provenance tracking. Open source, MIT licensed.

If you're tired of flying blind through AI-assisted content workflows, [give Numen a star on GitHub](#) and try it.

---

*Numen is an AI-first headless CMS built by [byte5](https://byte5.de). Open source under MIT.*

---

## 2. Changelog Entry

```
## [Unreleased] — Content Versioning & Draft System

### Added
- **Auto-save drafts** — continuous background saving; never lose work in progress
- **Named versions** — snapshot any state with a human-readable label
- **Side-by-side diff viewer** — visual comparison between any two versions (additions, deletions, structural changes)
- **One-click rollback** — restore any previous version instantly, non-destructively
- **Scheduled publishing** — set a future publish date/time per content item
- **Version branching** — parallel version trees for A/B variants, localizations, and drafts
- **AI pipeline versioning** — every pipeline run (ContentCreator, SEO, Editorial) creates a tracked version event
- **AI provenance metadata** — agent name, model version, prompt hash, reasoning, and token counts stored per AI-generated version
- **Programmatic version API** — `GET /api/v1/content/{id}/versions`, `GET /api/v1/content/{id}/versions/{versionId}`, `POST /api/v1/content/{id}/versions/{versionId}/restore`

### Technical Details
- Versions stored as immutable records in `content_versions` table
- Diff engine operates on structured `ContentBlock` data, not raw text
- AI versions carry full `AIGenerationLog` references for complete audit trail
- Branch metadata stored as JSON; merge strategy: last-write-wins with conflict flagging
```

---

## 3. Landing Page Section

### Section Headline
**Every Version. Every AI Decision. Fully Tracked.**

### Subheadline
Content versioning that goes beyond "undo" — Numen tracks human edits and AI pipeline changes with full attribution and reasoning.

### Feature Grid

**📝 Auto-Save & Named Versions**
Numen saves continuously as you write. Checkpoint milestones with meaningful names. Your entire content history, always there.

**🔍 Side-by-Side Diff Viewer**
See exactly what changed between any two versions. Additions, deletions, structural edits — visualized clearly. Know what you're publishing before you publish it.

**⏪ One-Click Rollback**
Made a mistake? Changed your mind? Restore any previous version instantly. History is immutable; you can always go back.

**📅 Scheduled Publishing**
Write now, publish later. Set a date and time, and Numen handles the rest. Your editorial calendar, automated.

**🌿 Version Branching**
Create parallel versions for A/B tests, localizations, or seasonal edits. Work in branches, merge when ready.

**🤖 AI Pipeline Provenance**
When Numen's AI agents touch your content — the SEO optimizer, the editorial reviewer, the content creator — every change is a tracked version. See which agent changed what, and why. Full audit trail for every AI decision.

### Pull Quote
> "With AI writing more and more content, you need to know what changed, who changed it, and why. Numen's provenance tracking gives you that audit trail — for humans and AI agents alike."

### CTA
**Try Numen — Open Source, MIT Licensed**
[⭐ Star on GitHub](#) · [View Docs](#) · [Live Demo](#)

---

## 4. Social Media Posts

### Post 1 — X (Twitter) — Feature Drop

```
Just shipped: full content versioning in Numen 🚀

✅ Auto-save drafts
✅ Named versions + diff viewer
✅ One-click rollback
✅ Scheduled publishing
✅ Version branching

But the real unlock: AI pipeline provenance.

Every AI agent that touches your content? Tracked. With reasoning.

Open source, MIT. 👇
[link]
```

---

### Post 2 — X (Twitter) — Differentiator Angle

```
Most CMSes let you version content.

None of them tell you *which AI agent* rewrote your headline, *what model* it used, and *why it made the changes it did*.

Numen now does.

Content provenance for the AI era.

[link]
```

---

### Post 3 — LinkedIn — Thought Leadership

```
Content without version history is a liability. Content without AI version history is flying blind.

We just shipped a complete versioning system for Numen, our open-source AI-first headless CMS. Auto-save, named snapshots, visual diffs, one-click rollback, scheduled publishing, version branching — the full stack.

But we went further.

Numen runs content through AI pipelines: a content creator agent, an SEO optimizer, an editorial reviewer. Until now, every CMS treated AI modifications as a black box. Content went in, content came out.

We call what we built "AI pipeline provenance." Every AI-generated change in Numen is a tracked version event — attributed to the specific agent, with the model version, reasoning, and token metadata logged. You can diff any AI change against the human-authored original. You can roll it back. You can audit it.

As teams integrate AI into editorial workflows, knowing what AI changed and why isn't a nice-to-have. It's a trust requirement.

Numen is open source under MIT. If you're building content infrastructure and care about auditability, take a look.

[link] #OpenSource #CMS #AIContent #ContentOps #Headless
```

---

*Assets prepared by Herald 📣 — Numen Marketing*
*Ready for: blog publication, changelog, site update (Façade 🏛️), social scheduling (Megaphone 📱)*
