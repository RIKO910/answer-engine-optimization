=== Answer Engine Optimization ===
Contributors: riko910
Tags: seo, aeo, answer engine, schema markup, ai optimization, faq, voice search
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-Powered Answer Engine Optimization for WordPress — optimize for ChatGPT, Perplexity, Google SGE, and all AI answer engines.

== Description ==

Answer Engine Optimization (AEO) is the world's most comprehensive free Answer Engine Optimization plugin for WordPress. Built with React 18 + TypeScript, it enables any website owner to dominate AI-driven search engines through automated structured data, intelligent content optimization, and real-time AI visibility analytics.

**100% Free — All Features Included. No Pro tiers. No upsells.**

= Core Modules =

* **AI Content Optimizer** — AEO Score engine (0-100), AI content rewriter, semantic analysis
* **Universal Schema Builder** — 40+ Schema.org types with visual JSON-LD editor
* **FAQ & Q&A Automation** — AI FAQ generator, global FAQ library, voice search optimization
* **AI Visibility Dashboard** — Citation tracker, performance charts, competitor gap analysis
* **Local Business AEO** — Multi-location NAP management, LocalBusiness schema
* **WooCommerce Product AEO** — Auto Product schema, pricing, ratings, variants
* **Technical AEO** — AI crawler robots.txt, llms.txt, Open Graph tags
* **Content Briefs** — AI opportunity finder and content brief generator
* **Site Audit & Auto-Fix** — Full-site scan with one-click auto-fix engine
* **Agency Tools** — White-label branding, REST API, WP-CLI commands

= Key Features =

* React-based wizard-driven admin UI
* Onboarding wizard (under 10 minutes)
* REST API at /wp-json/aeo-genius/v1/
* OpenAI, Anthropic, and Gemini AI provider support
* Works without API key (local fallback for core features)
* Gutenberg meta box + Classic Editor support
* GPL v2 open source

== Installation ==

1. Upload the plugin to `/wp-content/plugins/answer-engine-optimization`
2. Activate through the Plugins screen
3. Go to **AEO** in the WordPress admin menu
4. Complete the setup wizard
5. (Optional) Add AI API keys in Settings for enhanced AI features

= Development Build =

```
cd wp-content/plugins/answer-engine-optimization
npm install
npm run build
```

== Frequently Asked Questions ==

= What is Answer Engine Optimization? =
AEO optimizes content for AI answer engines (ChatGPT Search, Perplexity, Google AI Overviews, Bing Copilot) that provide direct answers instead of link lists.

= Do I need an AI API key? =
No. Core features (schema, audit, scoring, FAQs) work fully without any API key. AI features use your own OpenAI/Anthropic/Gemini key when configured.

= Does it work with WooCommerce? =
Yes. Product schema is automatically generated for all WooCommerce products when the module is enabled.

== REST API ==

Base URL: `/wp-json/aeo-genius/v1/`

Endpoints: dashboard, settings, schema, aeo-score, generate-faq, rewrite-content, audit/site, analytics/citations, briefs/opportunities, bulk/schema, local/locations

== WP-CLI ==

* `wp aeo audit` — Run full site audit
* `wp aeo score` — Calculate AEO scores for all posts
* `wp aeo fix <issue_id>` — Auto-fix an audit issue

== Changelog ==

= 1.0.0 =
* Initial PRD v1.0 release
* React 18 admin UI with all 11 modules
* REST API, WP-CLI, onboarding wizard
* 40+ schema types, AI providers, site audit auto-fix
* WooCommerce integration, local business, analytics

== Upgrade Notice ==

= 1.0.0 =
Initial release of Answer Engine Optimization plugin per PRD v1.0.
