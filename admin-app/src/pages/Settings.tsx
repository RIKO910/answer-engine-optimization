import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Save, Settings as SettingsIcon, Check } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import type { AeoSettings } from '@/types';

const moduleLabels: Record<string, string> = {
  content_optimizer: 'Content optimizer',
  schema_builder: 'Schema builder',
  faq_automation: 'FAQ automation',
  analytics: 'Analytics',
  local_business: 'Local business',
  woocommerce: 'WooCommerce',
  technical: 'Technical (llms.txt, AI crawlers)',
  social_og: 'Open Graph & social cards',
  content_briefs: 'Content briefs',
  site_audit: 'Site audit',
  agency: 'Agency tools',
};

type SettingsForm = Partial<AeoSettings> & {
  openai_api_key?: string;
  anthropic_api_key?: string;
  gemini_api_key?: string;
};

export default function Settings() {
  const { data, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.getSettings() as unknown as Promise<AeoSettings>,
  });

  const [form, setForm] = useState<SettingsForm>({});

  useEffect(() => {
    if (data) setForm(data as SettingsForm);
  }, [data]);

  const saveMutation = useMutation({
    mutationFn: () => api.updateSettings(form as Record<string, unknown>),
    onSuccess: () => toast.success('Settings saved'),
    onError: () => toast.error('Failed to save settings'),
  });

  if (isLoading) return <LoadingState message="Loading settings…" />;

  const modules = (form.modules || {}) as Record<string, boolean>;

  return (
    <div>
      <PageHeader
        title="Settings"
        description="AI provider, modules and tracking."
        icon={SettingsIcon}
        actions={
          <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="aeo-btn-primary">
            <Save size={14} /> {saveMutation.isPending ? 'Saving…' : 'Save changes'}
          </button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <CardHeader
            title="AI provider"
            description="Optional. Core features work without an API key."
          />
          <div className="space-y-4">
            <div>
              <label className="aeo-label">Provider</label>
              <select
                value={form.ai_provider || 'openai'}
                onChange={(e) => setForm({ ...form, ai_provider: e.target.value })}
                className="aeo-select"
              >
                <option value="openai">OpenAI (GPT-4o)</option>
                <option value="anthropic">Anthropic (Claude 3.5)</option>
                <option value="gemini">Google Gemini 1.5 Pro</option>
              </select>
            </div>
            {[
              { key: 'openai_api_key', label: 'OpenAI API key', placeholder: 'sk-...', configured: form.has_openai_key },
              { key: 'anthropic_api_key', label: 'Anthropic API key', placeholder: 'sk-ant-...', configured: form.has_anthropic_key },
              { key: 'gemini_api_key', label: 'Gemini API key', placeholder: 'AIza...', configured: form.has_gemini_key },
            ].map(({ key, label, placeholder, configured }) => (
              <div key={key}>
                <label className="aeo-label flex items-center justify-between">
                  <span>{label}</span>
                  {configured && (
                    <span className="inline-flex items-center gap-1 text-xs font-normal text-emerald-600">
                      <Check size={12} /> Saved
                    </span>
                  )}
                </label>
                <input
                  type="password"
                  autoComplete="off"
                  placeholder={configured ? '••••••••••••' : placeholder}
                  onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                  className="aeo-input"
                />
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <CardHeader
            title="Modules"
            description="Turn individual features on or off"
          />
          <div className="-mx-2">
            {Object.entries(modules).map(([key, enabled]) => (
              <label
                key={key}
                className="flex items-center justify-between px-2 h-10 rounded-md hover:bg-slate-50 cursor-pointer transition-colors"
              >
                <span className="text-sm text-slate-800">{moduleLabels[key] || key.replace(/_/g, ' ')}</span>
                <button
                  type="button"
                  role="switch"
                  aria-checked={!!enabled}
                  onClick={() => setForm({ ...form, modules: { ...modules, [key]: !enabled } })}
                  aria-label={key.replace(/_/g, ' ')}
                  className={`relative w-8 h-[18px] p-0 m-0 border-0 rounded-full cursor-pointer transition-colors ${enabled ? 'bg-aeo-600' : 'bg-slate-200'}`}
                >
                  <span className={`absolute top-[2px] w-[14px] h-[14px] bg-white rounded-full transition-[left] duration-150 ${enabled ? 'left-[16px]' : 'left-[2px]'}`} />
                </button>
              </label>
            ))}
          </div>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader
            title="Tracking"
            description="Keywords to monitor and competitor domains to compare against"
          />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="aeo-label">Keywords</label>
              <textarea
                value={(form.tracked_keywords ?? []).join('\n')}
                onChange={(e) => setForm({ ...form, tracked_keywords: e.target.value.split('\n').filter(Boolean) })}
                rows={5}
                placeholder="One keyword per line"
                className="aeo-textarea"
              />
            </div>
            <div>
              <label className="aeo-label">Competitor domains <span className="font-normal text-slate-400">· max 5</span></label>
              <textarea
                value={(form.competitors ?? []).join('\n')}
                onChange={(e) => setForm({ ...form, competitors: e.target.value.split('\n').filter(Boolean).slice(0, 5) })}
                rows={5}
                placeholder="competitor.com"
                className="aeo-textarea"
              />
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}
