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
    { title: 'Site Type', subtitle: 'What kind of website is this?' },
    { title: 'Business Info', subtitle: 'Tell us about your brand' },
    { title: 'AI Setup', subtitle: 'Choose your AI provider' },
    { title: 'Launch', subtitle: 'You\'re all set!' },
  ];

  const isLast = step === steps.length - 1;

  return (
    <div className="fixed inset-0 z-[99999] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" />
      <div className="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl overflow-hidden animate-slide-up">
        {/* Header gradient */}
        <div className="bg-gradient-brand px-8 pt-8 pb-6 text-white relative overflow-hidden">
          <div className="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-white/10 blur-xl" />
          <div className="absolute -left-4 bottom-0 w-24 h-24 rounded-full bg-white/5 blur-lg" />
          <div className="relative">
            <div className="flex items-center gap-2 mb-4">
              <div className="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                <Sparkles size={16} />
              </div>
              <span className="text-xs font-semibold uppercase tracking-widest text-white/70">
                Setup Wizard
              </span>
            </div>
            <h2 className="text-xl font-bold">{steps[step].title}</h2>
            <p className="text-sm text-white/70 mt-1">{steps[step].subtitle}</p>
          </div>
          {/* Step indicators */}
          <div className="flex gap-1.5 mt-6">
            {steps.map((_, i) => (
              <div
                key={i}
                className={cn(
                  'h-1 flex-1 rounded-full transition-all duration-300',
                  i <= step ? 'bg-white' : 'bg-white/25'
                )}
              />
            ))}
          </div>
        </div>

        {/* Content */}
        <div className="px-8 py-6 min-h-[280px]">
          {step === 0 && (
            <div className="text-center py-4">
              <div className="w-16 h-16 rounded-2xl bg-gradient-brand-subtle flex items-center justify-center mx-auto mb-5">
                <Sparkles size={28} className="text-aeo-600" />
              </div>
              <p className="text-slate-600 text-sm leading-relaxed max-w-sm mx-auto">
                Optimize your WordPress site for AI answer engines — ChatGPT, Perplexity,
                Google AI Overviews, and Bing Copilot. Setup takes under 10 minutes.
              </p>
              <div className="flex justify-center gap-6 mt-6 text-xs text-slate-400">
                <span>✓ 100% Free</span>
                <span>✓ No API key required</span>
                <span>✓ Auto-fix included</span>
              </div>
            </div>
          )}

          {step === 1 && (
            <div className="grid grid-cols-1 gap-2.5">
              {siteTypes.map((type) => (
                <button
                  key={type.id}
                  onClick={() => setSiteType(type.id)}
                  className={cn(
                    'flex items-center gap-4 p-4 rounded-2xl border-2 text-left transition-all duration-200',
                    siteType === type.id
                      ? 'border-aeo-500 bg-aeo-50/50 shadow-sm'
                      : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50'
                  )}
                >
                  <div className={cn(
                    'aeo-icon-box',
                    siteType === type.id ? 'bg-aeo-100 text-aeo-600' : 'bg-slate-100 text-slate-500'
                  )}>
                    <type.icon size={18} />
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-slate-800">{type.label}</p>
                    <p className="text-xs text-slate-400">{type.desc}</p>
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
                <label className="aeo-label">Site / Business Name</label>
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
            <div className="space-y-3">
              <p className="text-sm text-slate-500 mb-4">
                Choose your AI provider. Add API keys later in Settings — core features work without one.
              </p>
              {aiProviders.map((p) => (
                <button
                  key={p.id}
                  onClick={() => setAiProvider(p.id)}
                  className={cn(
                    'flex items-center gap-4 w-full p-4 rounded-2xl border-2 text-left transition-all',
                    aiProvider === p.id
                      ? 'border-aeo-500 bg-aeo-50/50'
                      : 'border-slate-100 hover:border-slate-200'
                  )}
                >
                  <div className="aeo-icon-box bg-violet-50 text-violet-600">
                    <Bot size={18} />
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-slate-800">{p.label}</p>
                    <p className="text-xs text-slate-400">{p.desc}</p>
                  </div>
                  {aiProvider === p.id && <Check size={16} className="ml-auto text-aeo-600" />}
                </button>
              ))}
            </div>
          )}

          {step === 4 && (
            <div className="text-center py-4">
              <div className="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-5">
                <Rocket size={28} className="text-emerald-600" />
              </div>
              <p className="text-sm text-slate-600 mb-5">
                We'll run an initial site scan and apply auto-fixes in the background.
              </p>
              <div className="grid grid-cols-2 gap-2 max-w-xs mx-auto text-left">
                {['Schema markup configured', 'FAQ automation enabled', 'Site audit scheduled', 'AEO baseline calculated'].map((item) => (
                  <div key={item} className="flex items-center gap-2 text-xs text-slate-600">
                    <Check size={14} className="text-emerald-500 flex-shrink-0" />
                    {item}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-8 py-5 border-t border-slate-100 flex justify-between items-center bg-slate-50/50">
          <button
            onClick={() => setStep(Math.max(0, step - 1))}
            disabled={step === 0}
            className="aeo-btn-ghost disabled:opacity-30"
          >
            <ChevronLeft size={16} /> Back
          </button>
          <span className="text-xs text-slate-400 font-medium">
            {step + 1} / {steps.length}
          </span>
          {isLast ? (
            <button
              onClick={() => completeMutation.mutate()}
              disabled={completeMutation.isPending}
              className="aeo-btn-primary"
            >
              {completeMutation.isPending ? 'Setting up...' : 'Launch AEO'}
              <Rocket size={16} />
            </button>
          ) : (
            <button onClick={() => setStep(step + 1)} className="aeo-btn-primary">
              Continue <ChevronRight size={16} />
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
