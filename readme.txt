=== Answer Engine Optimization ===
Contributors: riko910
Donate link: https://tarikul.top/
Tags: seo, schema, faq, ai, chatgpt
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free Answer Engine Optimization (AEO) plugin for WordPress. Schema markup, AI FAQ generator, JSON-LD, ChatGPT & Google AI Overviews optimization.

== Description ==

**Answer Engine Optimization** is the most complete free AEO and AI SEO plugin for WordPress. Optimize your website for **ChatGPT Search**, **Perplexity AI**, **Google AI Overviews** (SGE), **Bing Copilot**, and **Claude** — so AI answer engines cite, understand, and surface your content.

Traditional SEO plugins focus on blue links. Search is changing. Millions of users now get answers directly from AI — without clicking. This plugin gives you structured data, FAQ schema, content scoring, and AI visibility tools built specifically for that new reality.

= Why Answer Engine Optimization? =

* **Rank in AI answers** — Increase chances of being cited by ChatGPT, Perplexity, and Google AI Overviews
* **Automated schema markup** — 40+ Schema.org types including FAQPage, HowTo, Product, LocalBusiness, Article
* **AI FAQ generator** — Create optimized question-and-answer pairs in seconds (highest-impact AEO signal)
* **AEO Score (0–100)** — Proprietary readiness score per post with actionable recommendations
* **Site audit & auto-fix** — Scan your entire site and fix 80% of issues with one click
* **100% free forever** — No Pro tier, no paywall, no upsells. Every feature included.

= Who Is This Plugin For? =

* **Bloggers & content creators** — Get featured in AI summaries and voice search results
* **Small business owners** — LocalBusiness schema, NAP data, and local FAQ templates
* **WooCommerce stores** — Automatic Product schema with price, stock, ratings, and variants
* **SEO agencies** — White-label tools, REST API, WP-CLI, bulk schema, client reports
* **Developers** — Full REST API, hooks, and programmatic control

= 11 Powerful Modules — All Free =

**1. AI Content Optimizer**
Calculate a 0–100 AEO Score for every post and page. AI content rewriter improves machine readability. Semantic keyword suggestions and readability analysis included.

**2. Universal Schema Builder**
Visual JSON-LD editor with 40+ Schema.org types: Article, FAQPage, HowTo, Product, LocalBusiness, Organization, WebSite, Recipe, JobPosting, Event, and more. Real-time validation. Auto-schema based on post type.

**3. FAQ & Q&A Automation**
AI-powered FAQ generator creates 5–15 relevant Q&A pairs from any content. Global FAQ library. FAQPage schema auto-deployed. Voice search optimization with speakable markup signals.

**4. AI Visibility Analytics**
Track AI citation trends. Monitor Perplexity, Bing Copilot, and Google SGE appearances. Competitor AEO gap analysis for up to 5 domains. Performance charts and keyword tracking.

**5. Local Business AEO**
Multi-location NAP management. LocalBusiness schema with geo coordinates, opening hours, and area served. Local FAQ templates for "near me" queries.

**6. WooCommerce Product AEO**
Automatic Product + Offer + AggregateRating schema on every product page. Real-time price, availability, SKU, GTIN, brand, and variant markup for AI shopping assistants.

**7. Technical AEO Infrastructure**
AI crawler management (GPTBot, PerplexityBot, ClaudeBot). Auto-generated llms.txt. Open Graph and Twitter Card tags. robots.txt optimization for AI discovery.

**8. Content Briefs & Opportunity Finder**
AI content brief generator. Topic gap analysis. "People Also Ask" clustering. AI Answer Opportunity Score per topic.

**9. Site Audit & Auto-Fix Engine**
Full-site AEO crawler. Issues classified as Critical, Warning, or Opportunity. One-click auto-fix for missing schema, FAQs, and configuration gaps.

**10. Social & Open Graph AEO**
og:title, og:description, og:image on all pages. Twitter/X Cards. LinkedIn and WhatsApp preview optimization.

**11. Agency & White-Label Tools**
Replace plugin branding with your agency name. REST API full access. WP-CLI commands. Bulk schema operations across 100+ posts.

= Schema Markup & Structured Data =

Add rich JSON-LD structured data without writing code:

* FAQPage and QAPage schema for featured snippets and AI citations
* Article, BlogPosting, NewsArticle, TechArticle
* Product, Offer, AggregateRating, Review (WooCommerce)
* LocalBusiness, Restaurant, MedicalBusiness
* HowTo with step-by-step markup
* Organization, Person, WebSite, BreadcrumbList
* Recipe, Course, JobPosting, Event, VideoObject
* And 30+ more Schema.org types

Compatible alongside **Yoast SEO**, **Rank Math**, and other SEO plugins — adds an AEO layer without conflicts.

= AI Integration (Optional) =

Connect your own API key for enhanced AI features:

* **OpenAI** (GPT-4o) — Content rewriting, FAQ generation, briefs
* **Anthropic** (Claude 3.5) — Long-form AEO optimization
* **Google Gemini** (1.5 Pro) — Research-focused content briefs

**No API key required.** Schema, scoring, audit, and FAQ tools work fully without any AI subscription.

= Modern Admin Experience =

Beautiful React-based dashboard with setup wizard (under 10 minutes), real-time charts, drag-and-drop schema editor, and Gutenberg sidebar support. Works on desktop and tablet.

= Developer Features =

* REST API: `wp-json/aeo-genius/v1/`
* WP-CLI: `wp aeo audit`, `wp aeo score`, `wp aeo fix`
* GPL v2 open source — fork, modify, contribute
* Multisite compatible

**Stop optimizing only for Google crawlers. Start optimizing for every AI answer engine — free, forever.**

== Installation ==

= Automatic Installation =

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for **Answer Engine Optimization**
3. Click **Install Now**, then **Activate**
4. Open **AEO** in the left admin menu
5. Complete the 5-step setup wizard (takes under 10 minutes)

= Manual Installation =

1. Upload the `answer-engine-optimization` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen
3. Navigate to **AEO → Dashboard**
4. Run the onboarding wizard to configure schema, FAQs, and site type
5. (Optional) Add OpenAI, Anthropic, or Gemini API keys under **AEO → Settings**

= After Installation =

* Run your first **Site Audit** (AEO → Site Audit → Run Audit)
* Review your **AEO Score** on the dashboard
* Add FAQ schema to top posts using the **FAQ Manager** or post editor meta box
* Add tracked keywords in Settings to monitor AI citations

= For Developers =

`
cd wp-content/plugins/answer-engine-optimization
npm install
npm run build
`

== Frequently Asked Questions ==

= What is Answer Engine Optimization (AEO)? =

Answer Engine Optimization is the practice of optimizing web content so AI-powered answer engines — like ChatGPT Search, Perplexity AI, Google AI Overviews, Bing Copilot, and Claude — can discover, understand, and cite your website when users ask questions. Unlike traditional SEO (ranking in link lists), AEO focuses on becoming the source AI systems quote in direct answers.

= How is this different from Yoast SEO or Rank Math? =

Yoast and Rank Math are excellent traditional SEO plugins. Answer Engine Optimization adds a dedicated AEO layer: AI citation tracking, AEO Score per post, AI FAQ generation, competitor gap analysis for AI visibility, llms.txt for AI crawlers, and schema types optimized for answer engines. It works alongside them — not as a replacement.

= Is this plugin really 100% free? =

Yes. Every feature in this plugin is free with no Pro tier, no feature locks, and no upsell prompts. AI-powered features use your own API key (OpenAI, Anthropic, or Gemini) so there are no hidden subscription costs from us.

= Do I need an OpenAI or AI API key? =

No. Core features — schema markup, AEO scoring, site audit, FAQ management, Open Graph tags, llms.txt, and local business schema — work completely without any API key. API keys only unlock AI content rewriting, AI FAQ generation, and content briefs.

= Does this plugin add schema markup / JSON-LD? =

Yes. The plugin automatically outputs Article, FAQPage, WebSite, Organization, and post-specific JSON-LD schema. You can also build custom schema with the visual Schema Builder supporting 40+ Schema.org types including Product, HowTo, LocalBusiness, Recipe, and Event.

= How does the FAQ schema help with SEO and AI search? =

FAQPage schema is one of the strongest signals for AI answer engines and Google featured snippets. When you mark up questions and answers with structured data, AI systems can extract and cite your answers directly. The plugin auto-generates FAQs with AI and deploys FAQPage schema on every page with FAQ content.

= Will this help me rank in ChatGPT and Perplexity? =

The plugin optimizes the signals AI engines use: structured data, clear Q&A format, direct answers, semantic clarity, and crawlability (llms.txt, AI bot directives). While no plugin can guarantee citations, sites with strong FAQ schema, high AEO scores, and comprehensive structured data consistently perform better in AI answer results.

= Does it work with WooCommerce? =

Yes. When WooCommerce is active, the plugin automatically generates Product schema with price, currency, availability (InStock/OutOfStock), aggregate ratings, SKU, brand, and product images on every product page.

= Does it support voice search optimization? =

Yes. The plugin adds direct answer blocks, conversational FAQ formatting, speakable schema signals, and flags answers over 40 words for voice search trimming — all designed to improve performance in Google Assistant and voice-based AI queries.

= Can I use this for local SEO and Google Business Profile? =

Yes. The Local Business module supports multiple locations with NAP (Name, Address, Phone), opening hours, geo coordinates, area served, and LocalBusiness JSON-LD schema — optimized for "near me" and local AI queries.

= Is it compatible with Gutenberg and the Classic Editor? =

Yes. The plugin includes a post editor meta box for target questions, direct answers, and FAQ items. It works with Gutenberg, Classic Editor, and does not conflict with page builders like Elementor.

= Does it conflict with other SEO plugins? =

No. Answer Engine Optimization is designed to complement Yoast SEO, Rank Math, All in One SEO, and SEOPress. It adds AEO-specific schema, scoring, and analytics without overriding their core SEO settings.

= What is the AEO Score? =

The AEO Score is a proprietary 0–100 rating per post measuring readiness for AI citation. It evaluates content structure (20%), schema coverage (25%), FAQ presence (15%), semantic clarity (20%), and citation signals (20%). Scores update automatically on publish.

= How does the site audit work? =

The audit scans all published posts and pages for missing schema, absent FAQs, thin content, missing direct answers, and configuration gaps. Issues are rated Critical, Warning, or Opportunity. Most issues can be fixed automatically with one click.

= Is there a REST API for developers? =

Yes. Full REST API at `/wp-json/aeo-genius/v1/` with endpoints for schema, AEO scores, FAQ generation, site audit, analytics, content briefs, bulk schema, and settings. Application Passwords and cookie auth supported.

= Does it support WP-CLI? =

Yes. Commands include `wp aeo audit` (run site audit), `wp aeo score` (calculate all post scores), and `wp aeo fix <issue_id>` (auto-fix audit issues).

= What AI crawlers does it manage? =

The plugin lets you allow or block GPTBot (OpenAI), PerplexityBot, ClaudeBot (Anthropic), and Google-Extended individually via robots.txt. It also auto-generates an llms.txt file for AI content discovery.

= Is the plugin GDPR compliant? =

The plugin does not add tracking cookies by default. Content sent to AI APIs is site content only (not visitor PII). API keys are encrypted in the database. GDPR export and deletion hooks are registered.

= Can agencies white-label this plugin? =

Yes. Agency Tools let you replace the plugin name and logo in WordPress admin, use the REST API for client dashboards, run bulk operations, and generate performance reports — all free.

== Screenshots ==

1. AEO Dashboard — site-wide score, schema coverage, FAQ coverage, and quick actions
2. Content Optimizer — per-post AEO scores and AI content rewriting with side-by-side diff
3. Schema Builder — visual JSON-LD editor with 40+ Schema.org types and live preview
4. FAQ Manager — AI FAQ generator and global FAQ library across your site
5. Site Audit — full-site scan with Critical, Warning, and Opportunity issues plus auto-fix
6. AI Analytics — citation trends, engine breakdown, and competitor gap analysis
7. Onboarding Wizard — 5-step setup for site type, business info, and AI provider
8. Settings — module toggles, API keys, tracked keywords, and competitor domains

== Changelog ==

= 1.0.1 =
* Modern UI/UX redesign with gradient sidebar, React dashboard, and polished components
* Improved onboarding wizard with step indicators and site type selection
* Enhanced readme and WordPress.org directory optimization

= 1.0.0 =
* Initial release — full Answer Engine Optimization suite
* 11 feature modules: Content Optimizer, Schema Builder, FAQ Automation, Analytics, Local Business, WooCommerce, Technical AEO, Content Briefs, Site Audit, Social OG, Agency Tools
* React 18 + TypeScript admin UI with HashRouter navigation
* REST API at /wp-json/aeo-genius/v1/ with 20+ endpoints
* WP-CLI commands: audit, score, fix
* AEO Score engine (0–100) with 5 weighted factors
* 49 Schema.org types with visual JSON-LD builder
* AI providers: OpenAI, Anthropic, Gemini + local fallback (no API key required)
* Site audit with one-click auto-fix engine
* WooCommerce Product schema integration
* LocalBusiness multi-location support
* llms.txt, AI crawler robots.txt, Open Graph tags
* Onboarding wizard, Gutenberg meta box, encrypted API key storage
* Custom database tables: schema cache, audit log, citations

== Upgrade Notice ==

= 1.0.1 =
UI refresh and documentation improvements. Safe to update — no database migration required.

= 1.0.0 =
Initial release. Install and run the setup wizard to configure your site for AI answer engines.

== Support ==

* [Documentation & Plugin Page](https://tarikul.top/plugins/answer-engine-optimization/)
* [Author: Tarikul Islam Riko](https://tarikul.top/)
* [GitHub Repository](https://github.com/tarikulislamriko/answer-engine-optimization)
* [Report a Bug](https://github.com/tarikulislamriko/answer-engine-optimization/issues)
