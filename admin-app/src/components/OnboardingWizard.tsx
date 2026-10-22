import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import {
  ChevronRight, ChevronLeft, Sparkles, Globe, Building2,
  Store, Newspaper, PenLine, Bot, Rocket, Check,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
  onComplete: () => void;
}

const siteTypes = [
  { id: 'blog', label: 'Blog / Content', desc: 'Articles, tutorials, guides', icon: PenLine },
  { id: 'business', label: 'Business Website', desc: 'Corporate or service site', icon: Building2 },
  { id: 'local', label: 'Local Business', desc: 'Brick-and-mortar, service area', icon: Globe },
  { id: 'ecommerce', label: 'eCommerce', desc: 'WooCommerce online store', icon: Store },
  { id: 'news', label: 'News / Magazine', desc: 'Publishing & media', icon: Newspaper },
];

const aiProviders = [
  { id: 'openai', label: 'OpenAI GPT-4o', desc: 'Best all-round performance' },
  { id: 'anthropic', label: 'Claude 3.5 Sonnet', desc: 'Excellent for long-form content' },
  { id: 'gemini', label: 'Google Gemini 1.5', desc: 'Great for research & facts' },
];

export default function OnboardingWizard({ onComplete }: Props) {
  const [step, setStep] = useState(0);
  const [siteType, setSiteType] = useState('blog');
  const [businessName, setBusinessName] = useState(window.aeoGeniusData?.siteName || '');
  const [description, setDescription] = useState('');
  const [aiProvider, setAiProvider] = useState('openai');

  const completeMutation = useMutation({
    mutationFn: () =>
      api.completeOnboarding({
        site_type: siteType,
        ai_provider: aiProvider,
        business_info: { name: businessName, description, logo_url: '', social: [] },
        modules: {
          content_optimizer: true,
          schema_builder: true,
          faq_automation: true,
          analytics: true,
          local_business: siteType === 'local',
          woocommerce: siteType === 'ecommerce',
          technical: true,
          social_og: true,
          content_briefs: true,
          site_audit: true,
          agency: false,
        },
      }),
    onSuccess: () => {
      toast.success('Setup complete! Welcome to AEO.');
      onComplete();
    },
    onError: () => toast.error('Setup failed. Please try again.'),
  });

  const steps = [
    { title: 'Welcome', subtitle: 'Let\'s optimize your site for AI' },
    { title: 'Site type', subtitle: 'What kind of website is this?' },
    { title: 'Business info', subtitle: 'Tell us about your brand' },
    { title: 'AI provider', subtitle: 'Choose your AI provider' },
    { title: 'Ready', subtitle: 'You\'re all set' },
  ];

  const isLast = step === steps.length - 1;

  return (
    <div className="fixed inset-0 z-[99999] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-950/40" />
      <div role="dialog" aria-modal="true" aria-labelledby="aeo-wizard-title" className="relative w-full max-w-lg bg-white rounded-xl border border-slate-200 shadow-pop overflow-hidden animate-slide-up">
        {/* Header */}
        <div className="px-6 pt-6 pb-5 border-b border-slate-200">
          <div className="flex gap-1 mb-5" aria-hidden="true">
            {steps.map((_, i) => (
              <div
                key={i}
                className={cn(
                  'h-1 flex-1 rounded-full transition-colors duration-300',
                  i <= step ? 'bg-aeo-600' : 'bg-slate-200'
                )}
              />
            ))}
          </div>
          <p className="text-xs text-slate-500 tabular-nums">Step {step + 1} of {steps.length}</p>
          <h2 id="aeo-wizard-title" className="text-lg font-semibold tracking-tight text-slate-900 mt-1">{steps[step].title}</h2>
          <p className="text-sm text-slate-500 mt-0.5">{steps[step].subtitle}</p>
        </div>

        {/* Content */}
        <div className="px-6 py-6 min-h-[280px]">
          {step === 0 && (
            <div className="py-2">
              <div className="aeo-icon-box w-10 h-10 mb-4">
                <Sparkles size={18} strokeWidth={1.75} />
              </div>
              <p className="text-slate-600 text-sm leading-relaxed max-w-md">
                Optimize your WordPress site for AI answer engines — ChatGPT, Perplexity,
                Google AI Overviews, and Bing Copilot. Setup takes under 10 minutes.
              </p>
              <ul className="mt-5 space-y-2">
                {['Free, no upsells', 'No API key required', 'Takes a few minutes'].map((t) => (
                  <li key={t} className="flex items-center gap-2 text-[13px] text-slate-600">
                    <Check size={14} className="text-slate-400" /> {t}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {step === 1 && (
            <div className="grid grid-cols-1 gap-2">
              {siteTypes.map((type) => (
                <button
                  key={type.id}
                  onClick={() => setSiteType(type.id)}
                  className={cn(
                    'flex items-center gap-3 w-full px-3 py-3 m-0 rounded-lg border bg-white text-left cursor-pointer transition-colors duration-150',
                    siteType === type.id
                      ? 'border-aeo-600 ring-1 ring-aeo-600'
                      : 'border-slate-200 hover:border-slate-300'
                  )}
                >
                  <div className="aeo-icon-box">
                    <type.icon size={15} strokeWidth={1.75} />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-slate-900">{type.label}</p>
                    <p className="text-xs text-slate-500">{type.desc}</p>
                  </div>
                  {siteType === type.id && (
                    <Check size={16} className="ml-auto text-aeo-600" />
                  )}
                </button>
              ))}
            </div>
          )}

          {step === 2 && (
            <div className="space-y-4">
              <div>
                <label className="aeo-label">Site or business name</label>
                <input
                  type="text"
                  value={businessName}
                  onChange={(e) => setBusinessName(e.target.value)}
                  className="aeo-input"
                />
              </div>
              <div>
                <label className="aeo-label">Description</label>
                <textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={3}
                  className="aeo-textarea"
                  placeholder="Brief description of your site or business"
                />
              </div>
            </div>
          )}

          {step === 3 && (
            <div className="space-y-2">
              <p className="text-sm text-slate-500 mb-3">
                Choose your AI provider. Add API keys later in Settings — core features work without one.
              </p>
              {aiProviders.map((p) => (
                <button
                  key={p.id}
                  onClick={() => setAiProvider(p.id)}
                  className={cn(
                    'flex items-center gap-3 w-full px-3 py-3 m-0 rounded-lg border bg-white text-left cursor-pointer transition-colors duration-150',
                    aiProvider === p.id
                      ? 'border-aeo-600 ring-1 ring-aeo-600'
                      : 'border-slate-200 hover:border-slate-300'
                  )}
                >
                  <div className="aeo-icon-box">
                    <Bot size={15} strokeWidth={1.75} />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-slate-900">{p.label}</p>
                    <p className="text-xs text-slate-500">{p.desc}</p>
                  </div>
                  {aiProvider === p.id && <Check size={16} className="ml-auto text-aeo-600" />}
                </button>
              ))}
            </div>
          )}

          {step === 4 && (
            <div className="py-2">
              <div className="aeo-icon-box w-10 h-10 mb-4">
                <Rocket size={18} strokeWidth={1.75} />
              </div>
              <p className="text-sm text-slate-600 mb-5">
                Finish setup to save your details. You can run your first site audit from the dashboard.
              </p>
              <div className="space-y-2">
                {['Schema markup configured', 'FAQ tools enabled', 'Business details saved'].map((item) => (
                  <div key={item} className="flex items-center gap-2 text-[13px] text-slate-600">
                    <Check size={14} className="text-emerald-600 flex-shrink-0" />
                    {item}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-slate-200 flex justify-between items-center bg-slate-50">
          <button
            onClick={() => setStep(Math.max(0, step - 1))}
            disabled={step === 0}
            className="aeo-btn-ghost disabled:opacity-30"
          >
            <ChevronLeft size={14} /> Back
          </button>
          {isLast ? (
            <button
              onClick={() => completeMutation.mutate()}
              disabled={completeMutation.isPending}
              className="aeo-btn-primary"
            >
              {completeMutation.isPending ? 'Saving…' : 'Finish setup'}
            </button>
          ) : (
            <button onClick={() => setStep(step + 1)} className="aeo-btn-primary">
              Continue <ChevronRight size={14} />
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
