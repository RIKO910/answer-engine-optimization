export interface AeoSettings {
  site_type: string;
  onboarding_complete: boolean;
  ai_provider: string;
  modules: Record<string, boolean>;
  aeo_enable_schema: number;
  aeo_auto_questions: number;
  aeo_voice_optimization: number;
  competitors: string[];
  tracked_keywords: string[];
  business_info: {
    name: string;
    description: string;
    logo_url: string;
    social: string[];
  };
  has_openai_key: boolean;
  has_anthropic_key: boolean;
  has_gemini_key: boolean;
  has_perplexity_key: boolean;
  woocommerce_active: boolean;
  seo_plugin: string;
  defer_to_seo_plugin: number;
  default_models: Record<string, string>;
  openai_model?: string;
  anthropic_model?: string;
  gemini_model?: string;
  perplexity_model?: string;
  notifications: { email_citations: boolean; email_audit: boolean };
  crawler_settings: Record<string, 'allow' | 'block'>;
  agency?: { white_label_name: string; white_label_logo: string };
}

export interface CitationScanResult {
  keyword: string;
  cited: boolean;
  urls: string[];
  sources: string[];
  error?: string;
}

export interface CitationScan {
  scanned_at: string;
  engine: string;
  checked: number;
  cited: number;
  results: CitationScanResult[];
}

export interface CompetitorRow {
  domain: string;
  schema_types: string[];
  missing_types: string[];
  has_llms_txt: boolean;
  error: string;
  checked_at: string;
}

export interface PostScore {
  id: number;
  title: string;
  type: string;
  score: number;
  url: string;
}

export interface AuditIssue {
  id: string;
  post_id: number;
  severity: 'critical' | 'warning' | 'opportunity';
  category: string;
  message: string;
  autofix: boolean;
  status: string;
}

export interface FaqItem {
  question: string;
  answer: string;
  post_id?: number;
  post_title?: string;
}

export interface Location {
  name: string;
  type: string;
  address: string;
  city: string;
  region: string;
  postal_code: string;
  country: string;
  phone: string;
  hours: string;
  price_range: string;
  url: string;
  lat: string;
  lng: string;
}

export interface AeoGeniusData {
  apiUrl: string;
  nonce: string;
  adminUrl: string;
  siteUrl: string;
  siteName: string;
  pluginUrl: string;
  version: string;
  currentPage: string;
  caps?: { manage: boolean; view: boolean; schema: boolean; ai: boolean };
}

export interface BotInfo {
  key: string;
  name: string;
  owner: string;
  purpose: 'training' | 'search' | 'user';
  rule: 'allow' | 'block';
  token_only?: boolean;
}

export interface EngineResult {
  cited: boolean;
  mentioned: boolean;
  position: number;
  sentiment: string;
  urls: string[];
  sources: string[];
  error: string;
  id: number;
}

export interface VisibilitySummary {
  scan_id: string;
  scanned_at: string;
  checked: number;
  cited: number;
  mentioned: number;
  engines: Array<{ engine: string; label: string; checked: number; cited: number; mentioned: number; errors: number; error?: string; avg_position: number | null }>;
  keywords: Array<{ keyword: string; engines: Record<string, EngineResult> }>;
  share_of_voice: Array<{ name: string; is_you: boolean; cited: number; percent: number }>;
  sentiment: { positive: number; neutral: number; negative: number };
}

export interface ScanProgress {
  scan_id?: string;
  busy?: boolean;
  total: number;
  done: number;
  finished: string;
}

declare global {
  interface Window {
    aeoGeniusData: AeoGeniusData;
  }
}
