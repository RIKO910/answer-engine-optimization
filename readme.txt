=== Answer Engine Optimization ===
Contributors: riko910
Donate link: https://tarikul.top/
Tags: seo, schema, faq, ai, chatgpt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Help ChatGPT, Perplexity and Google AI Overviews understand and cite your site: JSON-LD, FAQs, llms.txt, AI crawler rules and citation tracking.

== Description ==

**Answer Engine Optimization** helps AI answer engines such as **ChatGPT Search**, **Perplexity**, **Google AI Overviews**, **Copilot** and **Claude** understand and cite your WordPress site.

It adds clean structured data, a visible FAQ and direct-answer block, an `llms.txt` index, AI crawler controls, a per-post AEO score and a site audit. It works on its own or next to Yoast SEO, Rank Math, All in One SEO, SEOPress and The SEO Framework.

= What it does =

* **Structured data (JSON-LD)** - one connected `@graph` with WebSite, Organization, Article/WebPage, FAQPage, Speakable and LocalBusiness/Hotel nodes. When an SEO plugin is active it lets that plugin output the base graph and only adds what's missing, so there are no duplicate entities.
* **FAQs and direct answers** - add a target question, a short direct answer and FAQ items per post. They are shown on the page (FAQ uses accessible `<details>` elements) and marked up as FAQPage and Speakable.
* **Question suggestions** - "Suggest questions" in the editor proposes questions and answers from your content (your AI key, or the post's own headings when no key is set).
* **AEO score (0-100)** - per post, from content structure, schema completeness, FAQs, readability and citation signals. Works with any language, including Bengali, Arabic and CJK scripts.
* **Site audit** - flags thin content, missing images, FAQs, direct answers and configuration problems. Some issues can be fixed in one click.
* **llms.txt** - an automatic `/llms.txt` index of your public content in the llmstxt.org format. Password-protected and noindex pages are left out.
* **AI crawler controls** - block GPTBot, ClaudeBot, PerplexityBot or Google-Extended in robots.txt.
* **AI citation tracking (Perplexity)** - checks your tracked keywords on Perplexity's Sonar API and records which answers link to your site. Needs your own Perplexity API key. Nothing is estimated or simulated.
* **Competitor check** - reads competitors' homepage structured data and checks for an llms.txt file, and shows schema types they use that you don't.
* **Local business** - multiple locations with PostalAddress, opening hours, geo coordinates and business type (Hotel, Restaurant, Store and more).
* **WooCommerce** - adds brand and GTIN to WooCommerce's own Product schema instead of printing a second Product.
* **Open Graph and Twitter cards** - when no SEO plugin is handling them.
* **Content briefs** - target questions, entities and schema type for a topic, plus an opportunity score based on how much of your own content already covers it.
* **Weekly email** - optional audit and citation summary.
* **White-label** - replace the plugin name and logo in the admin menu and dashboard.

= AI providers (optional) =

Bring your own API key for OpenAI, Anthropic (Claude) or Google Gemini to improve FAQ generation, rewriting and briefs. You can pick the model in Settings. Keys are stored encrypted. Without a key, the plugin uses built-in rules based on your content.

Citation tracking uses a separate Perplexity API key.

= Developers =

* REST API under `/wp-json/aeo-genius/v1/` (cookie or Application Password auth).
* WP-CLI: `wp aeo audit`, `wp aeo score`, `wp aeo fix <issue_id>`, `wp aeo scan`.
* Filters: `aeo_post_types`, `aeo_post_schema_nodes`, `aeo_auto_schema`, `aeo_location_schema`, `aeo_llms_txt`, `aeo_llms_txt_limit`, `aeo_defer_to_seo_plugin`, `aeo_is_noindex`, `aeo_ai_model`, `aeo_woocommerce_product_schema`, `aeo_digest_recipient`.
* Multisite: network activation sets up every site, including sites created later.
* Deleting the plugin removes its data. Define `AEO_KEEP_DATA` as `true` to keep it.

= Privacy =

The plugin adds no cookies or visitor tracking. When you use AI features, post content (never visitor data) is sent to the provider you configured. Citation scans send your tracked keywords to Perplexity. Competitor checks fetch the competitor's public homepage and llms.txt.

== Installation ==

= Automatic Installation =

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for **Answer Engine Optimization**
3. Click **Install Now**, then **Activate**
4. Open **AEO** in the left admin menu
5. Complete the short setup wizard

= Manual Installation =

1. Upload the `answer-engine-optimization` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen
3. Navigate to **AEO → Dashboard**
4. Run the onboarding wizard to configure schema, FAQs, and site type
5. (Optional) Add an OpenAI, Anthropic or Gemini key, and a Perplexity key for citation tracking, under **AEO → Settings**

= After Installation =

* Run your first **Site Audit** (AEO → Site Audit → Run Audit)
* Review your **AEO Score** on the dashboard
* Add FAQ schema to top posts using the **FAQ Manager** or post editor meta box
* Add tracked keywords and a Perplexity API key in Settings to track AI citations

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

Yes. There is no Pro version. AI features use your own API key (OpenAI, Anthropic, Gemini, or Perplexity for citation tracking), so you only pay the provider for what you use.

= Do I need an OpenAI or AI API key? =

No. Schema, scoring, the site audit, FAQs, Open Graph, llms.txt and local business schema work without any key. An AI key improves FAQ generation, rewriting and briefs. Citation tracking needs a Perplexity key.

= Does this plugin add schema markup / JSON-LD? =

Yes. It outputs one connected JSON-LD graph (WebSite, Organization, Article/WebPage, FAQPage, Speakable, LocalBusiness). The Schema Builder lets you replace a post's schema with your own JSON-LD for about 60 Schema.org types, with templates and required-property checks.

= How does the FAQ schema help with SEO and AI search? =

Clear question-and-answer content is easy for AI systems to extract and quote, and FAQPage markup helps them identify it. Google no longer shows FAQ rich results for most sites, but it still reads the markup. The plugin shows FAQs on the page and marks them up only where you add them.

= Will this help me rank in ChatGPT and Perplexity? =

No plugin can guarantee citations. This plugin improves the things you control: clear answers, structured data and crawlability. With a Perplexity key you can check whether Perplexity actually cites your pages for the keywords you track.

= Does it work with WooCommerce? =

Yes. WooCommerce already outputs Product schema (price, availability, ratings, SKU). This plugin adds brand and GTIN to it rather than printing a duplicate Product.

= Does it support voice search optimization? =

It adds a visible direct-answer block at the top of a post and Speakable schema that points to it.

= Can I use this for local SEO and Google Business Profile? =

It supports multiple locations with name, full postal address, phone, opening hours, price range, geo coordinates and business type (for example Hotel). It does not connect to Google Business Profile.

= Is it compatible with Gutenberg and the Classic Editor? =

Yes. The AEO box appears below the editor in both the block editor and the Classic Editor.

= Does it conflict with other SEO plugins? =

No. When Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework or Squirrly is active, the plugin lets it handle WebSite, Organization, Article and Open Graph output and only adds FAQPage, Speakable and LocalBusiness markup. You can turn this off in Settings.

= What is the AEO Score? =

A 0-100 rating per post: content structure (20%), schema completeness (25%), FAQs (15%), readability (20%) and citation signals such as a target question, featured image, freshness and indexability (20%). It updates when you save a post.

= How does the site audit work? =

It checks published content for thin text, missing featured images, FAQs and direct answers, plus site settings. Issues are rated Critical, Warning or Opportunity. FAQs, Open Graph, schema output and the organization name can be fixed in one click; content issues need a manual edit.

= Is there a REST API for developers? =

Yes, at `/wp-json/aeo-genius/v1/`, covering schema, scores, FAQs, the audit, analytics, briefs, bulk schema and settings. Cookie and Application Password authentication are supported.

= Does it support WP-CLI? =

Yes: `wp aeo audit`, `wp aeo score`, `wp aeo fix <issue_id>` and `wp aeo scan`.

= What AI crawlers does it manage? =

You can block GPTBot (OpenAI), ClaudeBot (Anthropic), PerplexityBot and Google-Extended individually in robots.txt. Allowed bots simply follow your normal robots.txt rules.

= Is the plugin GDPR compliant? =

The plugin stores no visitor personal data and sets no cookies, so there is nothing to export or erase. Content sent to AI providers is your site content only. API keys are encrypted in the database.

= Can agencies white-label this plugin? =

Yes. Set an agency name and logo under Agency; they replace the plugin name in the admin menu and dashboard.

== Screenshots ==

1. AEO Dashboard — site-wide score, schema coverage, FAQ coverage, and quick actions
2. Content — per-post AEO scores and AI rewrite preview
3. Schema Builder — JSON-LD editor with templates and validation
4. FAQ Manager — AI FAQ generator and global FAQ library across your site
5. Site Audit — full-site scan with Critical, Warning, and Opportunity issues plus auto-fix
6. Analytics — Perplexity citation results and competitor schema check
7. Setup wizard — short setup for site type, business info, and AI provider
8. Settings — module toggles, API keys, tracked keywords, and competitor domains

== Changelog ==

= 1.1.0 =
* Fixed: activation never ran (tables and defaults were not created) because the hook was registered too late.
* Fixed: /llms.txt returned 404 until permalinks were re-saved.
* Fixed: AI citation and competitor data were simulated. Citation tracking now uses Perplexity's API; competitor checks read real homepage schema. Old simulated rows are removed on upgrade.
* Fixed: duplicate WebSite, Organization, Article, Product and Open Graph output next to SEO plugins and WooCommerce.
* Fixed: posts with FAQs lost their Article schema. Schema is now one connected @graph with Speakable support.
* Fixed: a crafted FAQ answer could break out of the JSON-LD script tag (XSS). Output is now escaped.
* Fixed: contributors could edit schema, scores and FAQs of posts they cannot edit through the REST API.
* Fixed: question suggestions could inject HTML into the editor; the modal no longer opens by itself.
* Fixed: robots.txt "Allow" groups made AI bots ignore your other rules, and an invalid Sitemap line pointed to llms.txt.
* Fixed: settings and features that read an old option name (direct answer block, question suggestions).
* Fixed: word counts and scores were 0 for non-Latin languages such as Bengali.
* Fixed: "Missing schema" auto-fix replaced full schema with a stub; FAQ auto-fix inserted placeholder text.
* Fixed: random opportunity scores; keywords textarea dropped new lines; API keys could not be removed.
* Added: Perplexity citation scans, weekly email digest, multisite support, uninstall cleanup, model picker, AI crawler settings, Hotel/lodging schema types, location address fields, WP-CLI `wp aeo scan`.
* Changed: current default AI models; all custom post types are supported.
* Changed: minimal admin redesign.

= 1.0.2 =
* Tested updatd

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

= 1.1.0 =
Important bug and security fixes. Removes simulated citation data. Safe to update.

= 1.0.1 =
UI refresh and documentation improvements. Safe to update — no database migration required.

= 1.0.0 =
Initial release. Install and run the setup wizard to configure your site for AI answer engines.

== Support ==

* [Documentation & Plugin Page](https://tarikul.top/plugins/answer-engine-optimization/)
* [Author: Tarikul Islam Riko](https://tarikul.top/)
* [GitHub Repository](https://github.com/tarikulislamriko/answer-engine-optimization)
* [Report a Bug](https://github.com/tarikulislamriko/answer-engine-optimization/issues)
