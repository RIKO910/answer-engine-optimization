import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Building2, Save, Terminal, Code, Layers, Users } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';

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

  const saveMutation = useMutation({
    mutationFn: () =>
      api.updateSettings({
        agency: { white_label_name: whiteLabelName, white_label_logo: whiteLabelLogo },
        modules: { agency: true },
      }),
    onSuccess: () => toast.success('Agency settings saved'),
    onError: () => toast.error('Save failed'),
  });

  return (
    <div>
      <PageHeader
        title="Agency Tools"
        description="White-label branding, API access, and multi-site management"
        icon={Building2}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader title="White-Label Branding" description="Customize the admin experience for clients" />
          <div className="space-y-4">
            <div>
              <label className="aeo-label">Agency Name</label>
              <input
                type="text"
                value={whiteLabelName}
                onChange={(e) => setWhiteLabelName(e.target.value)}
                placeholder="Your Agency Name"
                className="aeo-input"
              />
              <p className="text-xs text-slate-400 mt-1">Replaces "Answer Engine Optimization" in admin</p>
            </div>
            <div>
              <label className="aeo-label">Agency Logo URL</label>
              <input
                type="url"
                value={whiteLabelLogo}
                onChange={(e) => setWhiteLabelLogo(e.target.value)}
                placeholder="https://your-agency.com/logo.png"
                className="aeo-input"
              />
            </div>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="aeo-btn-primary">
              <Save size={16} /> Save Branding
            </button>
          </div>
        </Card>

        <Card>
          <CardHeader title="Included Features" description="All agency tools are free" />
          <div className="space-y-2">
            {agencyFeatures.map(({ icon: Icon, label }) => (
              <div key={label} className="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
                <div className="aeo-icon-box bg-aeo-50 text-aeo-600">
                  <Icon size={16} />
                </div>
                <span className="text-sm font-medium text-slate-700">{label}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
