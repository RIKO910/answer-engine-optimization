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
  woocommerce_active: boolean;
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
  address: string;
  phone: string;
  hours: string;
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
}

declare global {
  interface Window {
    aeoGeniusData: AeoGeniusData;
  }
}
