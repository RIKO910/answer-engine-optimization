import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { RefreshCw, BarChart3, Globe, Users } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';


const engineLabels: Record<string, string> = {
  perplexity: 'Perplexity',
  bing_copilot: 'Bing Copilot',
  google_sge: 'Google AI Overviews',
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

  if (isLoading) return <LoadingState message="Loading analytics…" />;

  const citations = (overview?.citations || {}) as Record<string, unknown>;
  const trend = (citations.trend || []) as Array<{ date: string; count: number }>;
  const byEngine = (citations.by_engine || []) as Array<{ engine: string; count: number }>;
  const compData = (competitors?.competitors || []) as Array<Record<string, unknown>>;

  return (
    <div>
      <PageHeader
        title="Analytics"
        description="How AI engines discover and cite your content."
        icon={BarChart3}
        actions={
          <button
            onClick={() => scanMutation.mutate()}
            disabled={scanMutation.isPending}
            className="aeo-btn-primary"
          >
            <RefreshCw size={14} className={scanMutation.isPending ? 'animate-spin' : ''} />
            {scanMutation.isPending ? 'Scanning…' : 'Scan citations'}
          </button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <Card>
          <CardHeader title="Citations" description="Last 30 days" />
          {trend.length === 0 ? (
            <EmptyState
              icon={BarChart3}
              title="No citation data"
              description="Add tracked keywords in Settings, then scan"
            />
          ) : (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={trend} barSize={14} margin={{ top: 4, right: 0, left: -24, bottom: 0 }}>
                <CartesianGrid vertical={false} stroke="#f4f4f5" />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} />
                <Tooltip
                  cursor={{ fill: '#fafafa' }}
                  contentStyle={{ borderRadius: 8, border: '1px solid #e4e4e7', boxShadow: '0 8px 24px -8px rgba(0,0,0,0.12)', fontSize: 12 }}
                />
                <Bar dataKey="count" fill="#4f46e5" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </Card>

        <Card>
          <CardHeader title="By engine" description="Share of citations per AI engine" />
          {byEngine.length === 0 ? (
            <EmptyState icon={Globe} title="No engines tracked" description="Run a citation scan to populate data" />
          ) : (
            <div className="space-y-4">
              {byEngine.map((e, i) => {
                const total = byEngine.reduce((s, x) => s + Number(x.count), 0);
                const pct = total > 0 ? Math.round((Number(e.count) / total) * 100) : 0;
                return (
                  <div key={i}>
                    <div className="flex justify-between text-[13px] mb-1.5">
                      <span className="text-slate-800">{engineLabels[e.engine] || e.engine.replace(/_/g, ' ')}</span>
                      <span className="text-slate-500 tabular-nums">{e.count} · {pct}%</span>
                    </div>
                    <div className="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        className="h-full rounded-full bg-aeo-600 transition-[width] duration-500"
                        style={{ width: `${pct}%` }}
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
          title="Competitor gaps"
          description="Schema and FAQ gaps vs competitors"
        />
        {compData.length === 0 ? (
          <EmptyState
            icon={Users}
            title="No competitors configured"
            description="Add up to 5 competitor domains in Settings"
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {compData.map((comp, i) => (
              <div key={i} className="aeo-card-muted">
                <p className="text-sm font-medium text-slate-900">{comp.domain as string}</p>
                <p className="text-xs text-slate-500 mt-1">
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
