import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Building2, Save, Terminal, Code, Layers, Users } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import ProGate from '@/components/ProGate';
import { isPro } from '@/lib/pro';

const agencyFeatures = [
  { icon: Building2, label: 'White-label admin branding' },
  { icon: Code, label: 'REST API — /wp-json/aeo-genius/v1/' },
  { icon: Terminal, label: 'WP-CLI — wp aeo audit, score, fix' },
  { icon: Layers, label: 'Bulk schema operations' },
  { icon: Users, label: 'Competitor gap analysis (5 domains)' },
];

export default function AgencyTools() {
  const [whiteLabelName, setWhiteLabelName] = useState('');
  const [whiteLabelLogo, setWhiteLabelLogo] = useState('');
  const queryClient = useQueryClient();
  const { data: settings } = useQuery({ queryKey: ['settings'], queryFn: () => api.getSettings() });

  useEffect(() => {
    const agency = ((settings as Record<string, unknown>)?.agency || {}) as Record<string, string>;
    setWhiteLabelName(agency.white_label_name || '');
    setWhiteLabelLogo(agency.white_label_logo || '');
  }, [settings]);

  const saveMutation = useMutation({
    mutationFn: () =>
      api.updateSettings({
        agency: { white_label_name: whiteLabelName, white_label_logo: whiteLabelLogo },
        modules: { agency: true },
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
      toast.success('Branding saved');
    },
    onError: () => toast.error('Save failed'),
  });

  return (
    <div>
      <PageHeader
        title="Agency Tools"
        description="White-label branding and developer access for client sites."
        icon={Building2}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {isPro() ? (
        <Card>
          <CardHeader title="White-label" description="Shown in the sidebar instead of the plugin name" />
          <div className="space-y-4">
            <div>
              <label className="aeo-label">Agency name</label>
              <input
                type="text"
                value={whiteLabelName}
                onChange={(e) => setWhiteLabelName(e.target.value)}
                placeholder="Your agency"
                className="aeo-input"
              />
              <p className="aeo-hint">Leave empty to use the default name.</p>
            </div>
            <div>
              <label className="aeo-label">Logo URL</label>
              <input
                type="url"
                value={whiteLabelLogo}
                onChange={(e) => setWhiteLabelLogo(e.target.value)}
                placeholder="https://your-agency.com/logo.png"
                className="aeo-input"
              />
            </div>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="aeo-btn-primary">
              <Save size={14} /> Save
            </button>
          </div>
        </Card>
        ) : (
          <ProGate feature="white_label" />
        )}

        <Card>
          <CardHeader title="Developer access" />
          <div className="-mx-2">
            {agencyFeatures.map(({ icon: Icon, label }) => (
              <div key={label} className="flex items-center gap-3 px-2 h-10 rounded-md">
                <Icon size={15} strokeWidth={1.75} className="text-slate-400 flex-shrink-0" />
                <span className="text-sm text-slate-800">{label}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
