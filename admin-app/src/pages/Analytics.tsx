import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { RefreshCw, BarChart3, Globe, Users } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';

const engineColors: Record<string, string> = {
  perplexity: '#6366f1',
  bing_copilot: '#0ea5e9',
  google_sge: '#10b981',
};

export default function Analytics() {
  const { data: overview, isLoading } = useQuery({
    queryKey: ['analytics'],
    queryFn: () => api.getAnalyticsOverview(),
  });

  const { data: competitors } = useQuery({
    queryKey: ['competitors'],
    queryFn: () => api.getCompetitorAnalysis(),
  });

  const scanMutation = useMutation({
    mutationFn: () => api.scanCitations(),
    onSuccess: (data) => toast.success(`Found ${data.new_citations} new citations`),
    onError: () => toast.error('Citation scan failed'),
  });

  if (isLoading) return <LoadingState message="Loading analytics..." />;

  const citations = (overview?.citations || {}) as Record<string, unknown>;
  const trend = (citations.trend || []) as Array<{ date: string; count: number }>;
  const byEngine = (citations.by_engine || []) as Array<{ engine: string; count: number }>;
  const compData = (competitors?.competitors || []) as Array<Record<string, unknown>>;

  return (
    <div>
      <PageHeader
        title="AI Visibility Analytics"
        description="Track how AI engines discover and cite your content"
        icon={BarChart3}
        actions={
          <button
            onClick={() => scanMutation.mutate()}
            disabled={scanMutation.isPending}
            className="aeo-btn-primary"
          >
            <RefreshCw size={16} className={scanMutation.isPending ? 'animate-spin' : ''} />
            {scanMutation.isPending ? 'Scanning...' : 'Scan Citations'}
          </button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <Card>
          <CardHeader title="Citation Trend" description="Last 30 days" />
          {trend.length === 0 ? (
            <EmptyState
              icon={BarChart3}
              title="No citation data"
              description="Add tracked keywords in Settings, then scan"
            />
          ) : (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={trend} barSize={20}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                <XAxis dataKey="date" tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                <Tooltip
                  contentStyle={{ borderRadius: '12px', border: '1px solid #e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.08)' }}
                />
                <Bar dataKey="count" fill="url(#barGradient)" radius={[6, 6, 0, 0]} />
                <defs>
                  <linearGradient id="barGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#6366f1" />
                    <stop offset="100%" stopColor="#8b5cf6" />
                  </linearGradient>
                </defs>
              </BarChart>
            </ResponsiveContainer>
          )}
        </Card>

        <Card>
          <CardHeader title="Citations by Engine" description="AI engine breakdown" />
          {byEngine.length === 0 ? (
            <EmptyState icon={Globe} title="No engines tracked" description="Run a citation scan to populate data" />
          ) : (
            <div className="space-y-3">
              {byEngine.map((e, i) => {
                const total = byEngine.reduce((s, x) => s + Number(x.count), 0);
                const pct = total > 0 ? Math.round((Number(e.count) / total) * 100) : 0;
                return (
                  <div key={i}>
                    <div className="flex justify-between text-sm mb-1.5">
                      <span className="font-medium text-slate-700 capitalize">{e.engine.replace('_', ' ')}</span>
                      <span className="text-slate-500 tabular-nums">{e.count} <span className="text-slate-300">({pct}%)</span></span>
                    </div>
                    <div className="h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        className="h-full rounded-full transition-all duration-500"
                        style={{ width: `${pct}%`, backgroundColor: engineColors[e.engine] || '#6366f1' }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </Card>
      </div>

      <Card>
        <CardHeader
          title="Competitor Gap Analysis"
          description="Schema and FAQ gaps vs competitors"
          action={<Users size={18} className="text-slate-400" />}
        />
        {compData.length === 0 ? (
          <EmptyState
            icon={Users}
            title="No competitors configured"
            description="Add up to 5 competitor domains in Settings"
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {compData.map((comp, i) => (
              <div key={i} className="p-4 rounded-2xl border border-slate-100 hover:border-aeo-200 transition-colors">
                <p className="font-semibold text-slate-800">{comp.domain as string}</p>
                <p className="text-xs text-slate-400 mt-2">
                  Missing: {(comp.missing_types as string[])?.join(', ')}
                </p>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
