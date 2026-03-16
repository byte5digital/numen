# AI Pipeline Templates & Preset Library

**New in v0.10.0.** Reusable AI pipeline templates for accelerated content creation workflows.

---

## Overview

Pipeline Templates allow you to create, share, and install reusable AI content pipelines. Instead of configuring personas, stages, and variables from scratch for every brief, teams can install a pre-built template that handles the entire workflow.

The system includes 8 production-ready built-in templates, a community library with ratings and installs, template versioning, and plugin hooks for extending the library with custom template packs.

### Use Cases

- **Marketing teams** — Reuse campaign templates across projects (Blog Post, Social Media, Landing Page)
- **Product teams** — Standardize e-commerce workflows (Product Descriptions)
- **Agency teams** — White-label templates for client work (Email Newsletter, Press Release)
- **Technical writers** — Consistent documentation templates (Technical Documentation)
- **Content creators** — Video production at scale (Video Script)

---

## Built-in Templates

Numen ships with 8 production-ready templates covering common content workflows:

### 1. Blog Post Pipeline
**Slug:** `blog-post-pipeline`

Complete blog post creation with outline, draft, SEO review, and editorial gate.

### 2. Social Media Campaign
**Slug:** `social-media-campaign`

Generate platform-specific posts (Twitter, LinkedIn, Instagram) from a single brief.

### 3. Product Description
**Slug:** `product-description`

Generate compelling product copy with feature bullets and SEO meta descriptions.

### 4. Email Newsletter
**Slug:** `email-newsletter`

Create full newsletters with subject line variants, body, and spam check.

### 5. Press Release
**Slug:** `press-release`

Generate professional press releases in AP style with legal review gate.

### 6. Landing Page
**Slug:** `landing-page`

Craft high-converting landing page copy with headlines, hero, benefits, and CTAs.

### 7. Technical Documentation
**Slug:** `technical-documentation`

Generate developer-ready docs: overview, getting started, API reference, FAQ.

### 8. Video Script
**Slug:** `video-script`

Create full video scripts with hooks, scene breakdown, narration, and pacing review.

---

## Template Schema Format

### Template Metadata

```json
{
  "name": "Template Display Name",
  "slug": "kebab-case-slug",
  "description": "Short description of what the template does",
  "category": "content|social|ecommerce|email|pr|marketing|technical|video",
  "icon": "pencil|megaphone|cart|email|newspaper|rocket|books|film",
  "schema_version": "1.0",
  "author_name": "Creator Name",
  "author_url": "https://example.com",
  "is_published": true
}
```

### Definition Schema (JSON)

The `definition` field in `PipelineTemplateVersion` contains the full pipeline configuration:

```json
{
  "version": "1.0",
  "personas": [
    {
      "ref": "unique_ref",
      "name": "Display Name",
      "system_prompt": "System prompt for the persona...",
      "llm_provider": "openai|anthropic|azure-openai|together-ai",
      "llm_model": "gpt-4o|claude-3-sonnet|...",
      "voice_guidelines": "Brand voice and tone notes..."
    }
  ],
  "stages": [
    {
      "type": "ai_generate|ai_review|ai_transform|human_gate|auto_publish",
      "name": "Stage Display Name",
      "persona_ref": "reference_to_personas[*].ref",
      "config": {
        "prompt_template": "Prompt with {variables}...",
        "instructions": "For human_gate and auto_publish stages"
      },
      "enabled": true
    }
  ],
  "settings": {
    "auto_publish": false,
    "review_required": true,
    "max_retries": 3,
    "timeout_seconds": 300
  },
  "variables": [
    {
      "key": "variable_key",
      "type": "string|text|select|textarea",
      "label": "Display Label",
      "required": true,
      "options": ["option1", "option2"]
    }
  ]
}
```

---

## API Endpoints Reference

### List Templates (Paginated)

```
GET /api/v1/spaces/{space}/pipeline-templates
```

Query: `page`, `per_page`, `category`, `published`, `sort`

### Create Template

```
POST /api/v1/spaces/{space}/pipeline-templates
```

### Get Template

```
GET /api/v1/spaces/{space}/pipeline-templates/{template}
```

### Update Template

```
PATCH /api/v1/spaces/{space}/pipeline-templates/{template}
```

### Delete Template

```
DELETE /api/v1/spaces/{space}/pipeline-templates/{template}
```

### Publish/Unpublish Template

```
POST /api/v1/spaces/{space}/pipeline-templates/{template}/publish
POST /api/v1/spaces/{space}/pipeline-templates/{template}/unpublish
```

### Manage Versions

```
GET /api/v1/spaces/{space}/pipeline-templates/{template}/versions
POST /api/v1/spaces/{space}/pipeline-templates/{template}/versions
GET /api/v1/spaces/{space}/pipeline-templates/{template}/versions/{version}
```

### Template Installation

```
POST /api/v1/spaces/{space}/pipeline-templates/installs/{version}
PATCH /api/v1/spaces/{space}/pipeline-templates/installs/{install}
DELETE /api/v1/spaces/{space}/pipeline-templates/installs/{install}
```

**Rate Limited:** 5 requests per minute per user

### Template Ratings

```
GET /api/v1/spaces/{space}/pipeline-templates/{template}/ratings
POST /api/v1/spaces/{space}/pipeline-templates/{template}/ratings
```

---

## Install Wizard Flow

1. **Select Template** — Browse templates by category, rating, or download count
2. **Review Definition** — Display template metadata, ratings, definition (personas, stages, variables)
3. **Confirm Installation** — Creates install record and Brief with template configuration
4. **Launch Pipeline** — Users can immediately start briefs using the template

---

## Plugin Hook Integration

### Register Template Category

```php
$hookRegistry = app(\App\Plugin\HookRegistry::class);

$hookRegistry->registerTemplateCategory([
    'slug' => 'custom-category',
    'label' => 'Custom Content Type',
    'description' => 'Templates for custom content workflows',
    'icon' => 'star',
]);
```

### Register Template Pack

```php
$hookRegistry->registerTemplatePack([
    'id' => 'my-plugin-templates',
    'name' => 'My Plugin Templates',
    'author' => 'Your Company',
    'url' => 'https://example.com',
    'templates' => [
        [
            'name' => 'Custom Template 1',
            'slug' => 'custom-template-1',
            'description' => 'Description...',
            'category' => 'custom-category',
            'icon' => 'star',
            'schema_version' => '1.0',
            'definition' => [
                'version' => '1.0',
                'personas' => [ /* ... */ ],
                'stages' => [ /* ... */ ],
                'variables' => [ /* ... */ ],
            ],
        ],
    ],
]);
```

---

## Security Notes

### Space Scoping

- **Global** (`space_id = null`) — Available to all spaces (built-in, published templates)
- **Space-scoped** (`space_id = <uuid>`) — Private to a specific space

### Role-Based Access Control

| Operation | Permission | Role |
|-----------|-----------|------|
| Create | `templates.create` | Editor, Admin |
| Update | `templates.update` | Editor, Admin |
| Publish | `templates.publish` | Admin |
| Delete | `templates.delete` | Admin |
| Install | `templates.install` | Editor, Author |
| Rate | `templates.rate` | Any authenticated user |

### Audit Logging

All template operations logged for compliance:
- Template creation/update/deletion
- Version publishing
- Template installation
- Template ratings

### API Rate Limiting

- **Install template:** 5 requests per minute
- **Rating:** 1 request per minute
- **Template creation:** 10 requests per minute

---

## Related Documentation

- [Pipeline Architecture](../architecture/pipelines.md)
- [Plugin System](../plugins.md)
- [RBAC Guide](../RBAC_GUIDE.md)

