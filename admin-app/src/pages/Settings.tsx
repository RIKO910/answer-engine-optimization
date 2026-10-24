import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Save, Settings as SettingsIcon, Check } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import type { AeoSettings } from '@/types';
import Switch from '@/components/ui/Switch';
import { Link } from 'react-router-dom';
import { __ } from '@/lib/i18n';

const moduleLabels: Record<string, string> = {
  content_optimizer: 'Content optimizer',
  schema_builder: 'Schema builder',
  faq_automation: 'FAQs (visible section + FAQPage schema)',
  analytics: 'Analytics',
  local_business: 'Local business',
  woocommerce: 'WooCommerce',
  technical: 'llms.txt & AI crawler rules',
  social_og: 'Open Graph & social cards',
  content_briefs: 'Content briefs',
  site_audit: 'Site audit',
  agency: 'Agency tools',
};

const providers = [
  { id: 'openai', label: 'OpenAI', keyField: 'openai_api_key', hasKey: 'has_openai_key', placeholder: 'sk-…' },
  { id: 'anthropic', label: 'Anthropic (Claude)', keyField: 'anthropic_api_key', hasKey: 'has_anthropic_key', placeholder: 'sk-ant-…' },
  { id: 'gemini', label: 'Google Gemini', keyField: 'gemini_api_key', hasKey: 'has_gemini_key', placeholder: 'AIza…' },
  { id: 'mistral', label: 'Mistral', keyField: 'mistral_api_key', hasKey: 'has_mistral_key', placeholder: '' },
  { id: 'deepseek', label: 'DeepSeek', keyField: 'deepseek_api_key', hasKey: 'has_deepseek_key', placeholder: 'sk-…' },
  { id: 'openrouter', label: 'OpenRouter', keyField: 'openrouter_api_key', hasKey: 'has_openrouter_key', placeholder: 'sk-or-…' },
] as const;

type Form = Partial<AeoSettings> & Record<string, unknown>;


export default function Settings() {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.getSettings() as unknown as Promise<AeoSettings>,
  });

  const [form, setForm] = useState<Form>({});
  // Keep raw textarea text so Enter/new lines work while typing; parse on save.
  const [competitorsText, setCompetitorsText] = useState('');
  const [keys, setKeys] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!data) return;
    setForm(data as Form);
    setCompetitorsText((data.competitors ?? []).join('\n'));
    setKeys({});
  }, [data]);

  const saveMutation = useMutation({
    mutationFn: (extra: Record<string, unknown> = {}) => {
      const lines = (t: string) => t.split('\n').map((l) => l.trim()).filter(Boolean);
      const payload: Record<string, unknown> = {
        ai_provider: form.ai_provider,
        modules: form.modules,
        notifications: form.notifications,
        defer_to_seo_plugin: form.defer_to_seo_plugin ? 1 : 0,
        aeo_enable_schema: form.aeo_enable_schema ? 1 : 0,
        aeo_voice_optimization: form.aeo_voice_optimization ? 1 : 0,
        aeo_auto_questions: form.aeo_auto_questions ? 1 : 0,
        openai_model: form.openai_model ?? '',
        anthropic_model: form.anthropic_model ?? '',
        gemini_model: form.gemini_model ?? '',
        perplexity_model: form.perplexity_model ?? '',
        mistral_model: form.mistral_model ?? '',
        deepseek_model: form.deepseek_model ?? '',
        openrouter_model: form.openrouter_model ?? '',
        author_box: form.author_box ? 1 : 0,
        competitors: lines(competitorsText),
        ...keys,
        ...extra,
      };
      return api.updateSettings(payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
      toast.success('Settings saved');
    },
    onError: (e: Error) => toast.error(e.message || 'Failed to save settings'),
  });

  if (isLoading) return <LoadingState message="Loading settings…" />;

  const modules = (form.modules || {}) as Record<string, boolean>;
  const notifications = (form.notifications || { email_audit: false, email_citations: false }) as {
    email_audit: boolean;
    email_citations: boolean;
  };
  const defaults = (form.default_models || {}) as Record<string, string>;
  const provider = providers.find((p) => p.id === (form.ai_provider || 'openai')) ?? providers[0];
  const set = (patch: Form) => setForm((f) => ({ ...f, ...patch }));

  const keyInput = (field: string, label: string, configured: boolean, placeholder: string) => (
    <div key={field}>
      <label className="aeo-label flex items-center justify-between" htmlFor={`aeo-${field}`}>
        <span>{label}</span>
        {configured && (
          <span className="inline-flex items-center gap-2 text-xs font-normal">
            <span className="inline-flex items-center gap-1 text-emerald-600">
              <Check size={12} /> Saved
            </span>
            <button
              type="button"
              className="p-0 m-0 bg-transparent border-0 text-slate-500 hover:text-red-600 cursor-pointer text-xs"
              onClick={() => saveMutation.mutate({ [`clear_${field}`]: true })}
            >
              Remove
            </button>
          </span>
        )}
      </label>
      <input
        id={`aeo-${field}`}
        type="password"
        autoComplete="off"
        value={keys[field] ?? ''}
        placeholder={configured ? '•••••••••••• (leave empty to keep)' : placeholder}
        onChange={(e) => setKeys((k) => ({ ...k, [field]: e.target.value }))}
        className="aeo-input"
      />
    </div>
  );

  return (
    <div>
      <PageHeader
        title="Settings"
        description="AI provider, modules, crawlers and tracking."
        icon={SettingsIcon}
        actions={
          <button onClick={() => saveMutation.mutate({})} disabled={saveMutation.isPending} className="aeo-btn-primary">
            <Save size={14} /> {saveMutation.isPending ? 'Saving…' : 'Save changes'}
          </button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <CardHeader title="AI provider" description="Optional. Core features work without an API key." />
          <div className="space-y-4">
            <div>
              <label className="aeo-label" htmlFor="aeo-provider">Provider</label>
              <select
                id="aeo-provider"
                value={provider.id}
                onChange={(e) => set({ ai_provider: e.target.value })}
                className="aeo-select"
              >
                {providers.map((p) => (
                  <option key={p.id} value={p.id}>{p.label}</option>
                ))}
              </select>
            </div>
            {keyInput(provider.keyField, `${provider.label} API key`, !!form[provider.hasKey], provider.placeholder)}
            <div>
              <label className="aeo-label" htmlFor="aeo-model">Model</label>
              <input
                id="aeo-model"
                type="text"
                value={(form[`${provider.id}_model`] as string) ?? ''}
                placeholder={defaults[provider.id] || ''}
                onChange={(e) => set({ [`${provider.id}_model`]: e.target.value })}
                className="aeo-input font-mono"
              />
              <p className="aeo-hint">Leave empty to use the default ({defaults[provider.id]}).</p>
            </div>
          </div>
        </Card>

        <Card>
          <CardHeader
            title={__('AI visibility engines')}
            description={__('Scans use each engine with live web search. Perplexity needs its own key; ChatGPT, Gemini and Claude reuse the keys above.')}
          />
          <div className="space-y-4">
            {keyInput('perplexity_api_key', 'Perplexity API key', !!form.has_perplexity_key, 'pplx-…')}
            {providers
              .filter((p) => ['openai', 'anthropic', 'gemini'].includes(p.id) && p.id !== provider.id)
              .map((p) => keyInput(p.keyField, `${p.label} API key`, !!form[p.hasKey], p.placeholder))}
            <p className="aeo-hint">
              {__('Questions to track, brand names and engine choice live on the')}{' '}
              <Link to="/analytics" className="text-aeo-700">{__('AI visibility')}</Link> {__('page.')}
            </p>
          </div>
        </Card>

        <Card>
          <CardHeader title="Modules" description="Turn individual features on or off" />
          <div className="-mx-2">
            {Object.entries(modules).map(([key, enabled]) => (
              <div key={key} className="flex items-center justify-between gap-4 px-2 h-10 rounded-md hover:bg-slate-50">
                <span className="text-sm text-slate-800">{moduleLabels[key] || key.replace(/_/g, ' ')}</span>
                <Switch
                  checked={!!enabled}
                  label={moduleLabels[key] || key}
                  onChange={() => set({ modules: { ...modules, [key]: !enabled } })}
                />
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <CardHeader title="Output" description="What the plugin adds to your pages" />
          <div className="-mx-2">
            {[
              { key: 'aeo_enable_schema', label: 'Structured data (JSON-LD)' },
              { key: 'aeo_voice_optimization', label: 'Show direct answer + Speakable schema' },
              { key: 'aeo_auto_questions', label: '"Suggest questions" in the post editor' },
              { key: 'author_box', label: __('Author box with credentials under posts (E-E-A-T)') },
            ].map(({ key, label }) => (
              <div key={key} className="flex items-center justify-between gap-4 px-2 h-10 rounded-md hover:bg-slate-50">
                <span className="text-sm text-slate-800">{label}</span>
                <Switch checked={!!form[key]} label={label} onChange={() => set({ [key]: form[key] ? 0 : 1 })} />
              </div>
            ))}
            <div className="flex items-center justify-between gap-4 px-2 py-2 rounded-md hover:bg-slate-50">
              <div>
                <span className="text-sm text-slate-800">Let my SEO plugin handle base schema & Open Graph</span>
                <p className="text-xs text-slate-500 mt-0.5">
                  {form.seo_plugin
                    ? `${form.seo_plugin} detected. Avoids duplicate WebSite, Organization and Article markup.`
                    : 'No SEO plugin detected, so this plugin outputs everything.'}
                </p>
              </div>
              <Switch
                checked={!!form.defer_to_seo_plugin}
                label="Defer to SEO plugin"
                onChange={() => set({ defer_to_seo_plugin: form.defer_to_seo_plugin ? 0 : 1 })}
              />
            </div>
          </div>
        </Card>

        <Card>
          <CardHeader title={__('AI crawlers & llms.txt')} description={__('28 AI crawlers, presets, content signals, llms.txt and Markdown pages.')} />
          <Link to="/ai-access" className="aeo-btn-secondary">{__('Open AI access')}</Link>
        </Card>

        <Card>
          <CardHeader title="Weekly email" description="Sent to the site admin email" />
          <div className="-mx-2">
            {[
              { key: 'email_audit' as const, label: 'Site audit summary' },
              { key: 'email_citations' as const, label: 'AI citation summary (needs Perplexity key)' },
            ].map(({ key, label }) => (
              <div key={key} className="flex items-center justify-between gap-4 px-2 h-10 rounded-md hover:bg-slate-50">
                <span className="text-sm text-slate-800">{label}</span>
                <Switch
                  checked={!!notifications[key]}
                  label={label}
                  onChange={() => set({ notifications: { ...notifications, [key]: !notifications[key] } })}
                />
              </div>
            ))}
          </div>
          <div className="mt-4 pt-4 border-t border-slate-200">
            <label className="aeo-label" htmlFor="aeo-competitors">
              Competitor domains <span className="font-normal text-slate-400">· max 5</span>
            </label>
            <textarea
              id="aeo-competitors"
              value={competitorsText}
              onChange={(e) => setCompetitorsText(e.target.value)}
              rows={3}
              placeholder="competitor.com"
              className="aeo-textarea"
            />
          </div>
        </Card>
      </div>
    </div>
  );
}
