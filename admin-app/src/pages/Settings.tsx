import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Save, Settings as SettingsIcon, Bot, ToggleLeft, Key, Target } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import type { AeoSettings } from '@/types';

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

  if (isLoading) return <LoadingState message="Loading settings..." />;

  const modules = (form.modules || {}) as Record<string, boolean>;

  return (
    <div>
      <PageHeader
        title="Settings"
        description="API keys, feature modules, and tracking configuration"
        icon={SettingsIcon}
        actions={
          <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="aeo-btn-primary">
            <Save size={16} /> {saveMutation.isPending ? 'Saving...' : 'Save Changes'}
          </button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader
            title="AI Provider"
            description="Connect your AI API keys for enhanced features"
            action={<Bot size={18} className="text-aeo-500" />}
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
              { key: 'openai_api_key', label: 'OpenAI API Key', placeholder: 'sk-...', configured: form.has_openai_key },
              { key: 'anthropic_api_key', label: 'Anthropic API Key', placeholder: 'sk-ant-...', configured: form.has_anthropic_key },
              { key: 'gemini_api_key', label: 'Gemini API Key', placeholder: 'AIza...', configured: form.has_gemini_key },
            ].map(({ key, label, placeholder, configured }) => (
              <div key={key}>
                <label className="aeo-label flex items-center gap-2">
                  <Key size={12} /> {label}
                  {configured && <span className="aeo-badge bg-emerald-50 text-emerald-600 text-[10px]">configured</span>}
                </label>
                <input
                  type="password"
                  placeholder={placeholder}
                  onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                  className="aeo-input"
                />
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <CardHeader
            title="Feature Modules"
            description="Toggle individual AEO modules"
            action={<ToggleLeft size={18} className="text-slate-400" />}
          />
          <div className="space-y-1">
            {Object.entries(modules).map(([key, enabled]) => (
              <label
                key={key}
                className="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors"
              >
                <span className="text-sm font-medium text-slate-700 capitalize">{key.replace(/_/g, ' ')}</span>
                <button
                  type="button"
                  role="switch"
                  aria-checked={!!enabled}
                  onClick={() => setForm({ ...form, modules: { ...modules, [key]: !enabled } })}
                  className={`relative w-9 h-5 rounded-full transition-colors ${enabled ? 'bg-aeo-500' : 'bg-slate-200'}`}
                >
                  <span className={`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform ${enabled ? 'left-[18px]' : 'left-0.5'}`} />
                </button>
              </label>
            ))}
          </div>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader
            title="Tracking & Competitors"
            description="Keywords to monitor and competitor domains for gap analysis"
            action={<Target size={18} className="text-slate-400" />}
          />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="aeo-label">Tracked Keywords</label>
              <textarea
                value={(form.tracked_keywords ?? []).join('\n')}
                onChange={(e) => setForm({ ...form, tracked_keywords: e.target.value.split('\n').filter(Boolean) })}
                rows={5}
                placeholder="One keyword per line"
                className="aeo-textarea"
              />
            </div>
            <div>
              <label className="aeo-label">Competitor Domains (max 5)</label>
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
