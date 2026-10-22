import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { Link } from 'react-router-dom';
import { RefreshCw, BarChart3, Users, Check, X } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import type { CitationScan } from '@/types';
import { cn } from '@/lib/utils';

export default function Analytics() {
  const queryClient = useQueryClient();
  const { data: overview, isLoading } = useQuery({
    queryKey: ['analytics'],
    queryFn: () => api.getAnalyticsOverview(),
  });

  const competitors = useQuery({
    queryKey: ['competitors'],
    queryFn: () => api.getCompetitorAnalysis(),
  });

  const scanMutation = useMutation({
    mutationFn: () => api.scanCitations(),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['analytics'] });
      toast.success(`${data.cited} of ${data.checked} keywords cite your site`);
    },
    onError: (e: Error) => toast.error(e.message || 'Citation scan failed'),
  });

  const refreshCompetitors = useMutation({
    mutationFn: () => api.getCompetitorAnalysis(true),
    onSuccess: (data) => queryClient.setQueryData(['competitors'], data),
    onError: (e: Error) => toast.error(e.message),
  });

  if (isLoading) return <LoadingState message="Loading analytics…" />;

  const citations = (overview?.citations || {}) as Record<string, unknown>;
  const trend = (citations.trend || []) as Array<{ date: string; count: number }>;
  const available = !!citations.available;
  const lastScan = (citations.last_scan || {}) as Partial<CitationScan>;
  const scanResults = lastScan.results || [];
  const compData = competitors.data?.competitors || [];

  return (
    <div>
      <PageHeader
        title="Analytics"
        description="Whether AI answer engines cite your site, and how competitors are set up."
        icon={BarChart3}
        actions={
          <button
            onClick={() => scanMutation.mutate()}
            disabled={scanMutation.isPending || !available}
            className="aeo-btn-primary"
            title={available ? '' : 'Add a Perplexity API key in Settings'}
          >
            <RefreshCw size={14} className={scanMutation.isPending ? 'animate-spin' : ''} />
            {scanMutation.isPending ? 'Scanning…' : 'Scan citations'}
          </button>
        }
      />

      {!available && (
        <div className="aeo-card-muted mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <p className="text-[13px] text-slate-600">
            Citation tracking asks Perplexity your tracked keywords and records answers that link to your site.
            It needs a Perplexity API key.
          </p>
          <Link to="/settings" className="aeo-btn-secondary aeo-btn-sm self-start sm:self-center">
            Add key
          </Link>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <Card>
          <CardHeader
            title="Last scan"
            description={
              lastScan.scanned_at
                ? `${lastScan.cited ?? 0} of ${lastScan.checked ?? 0} keywords cited your site · ${lastScan.scanned_at}`
                : 'No scan yet'
            }
          />
          {scanResults.length === 0 ? (
            <EmptyState
              icon={BarChart3}
              title="No results yet"
              description={available ? 'Add tracked keywords in Settings, then run a scan.' : 'Add a Perplexity API key and tracked keywords in Settings.'}
            />
          ) : (
            <ul className="-mx-2">
              {scanResults.map((r) => (
                <li key={r.keyword} className="flex items-start gap-3 px-2 py-2 rounded-md hover:bg-slate-50">
                  <span className={cn('mt-0.5 flex-shrink-0', r.cited ? 'text-emerald-600' : 'text-slate-300')}>
                    {r.cited ? <Check size={15} /> : <X size={15} />}
                  </span>
                  <div className="min-w-0">
                    <p className="text-sm text-slate-900">{r.keyword}</p>
                    <p className="text-xs text-slate-500 truncate">
                      {r.error ? r.error : r.cited ? r.urls.join(', ') : r.sources.length ? `Sources used: ${r.sources.join(', ')}` : 'No sources returned'}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card>
          <CardHeader title="Citations over time" description="Answers citing your site, last 30 days" />
          {trend.length === 0 ? (
            <EmptyState icon={BarChart3} title="No citations recorded" description="Results appear here after scans find your site." />
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
                <Bar dataKey="count" name="Citations" fill="#4f46e5" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </Card>
      </div>

      <Card>
        <CardHeader
          title="Competitors"
          description="Structured data on their homepage and whether they publish llms.txt"
          action={
            compData.length > 0 && (
              <button
                onClick={() => refreshCompetitors.mutate()}
                disabled={refreshCompetitors.isPending}
                className="aeo-btn-ghost aeo-btn-sm"
              >
                <RefreshCw size={12} className={refreshCompetitors.isPending ? 'animate-spin' : ''} /> Re-check
              </button>
            )
          }
        />
        {competitors.isLoading ? (
          <LoadingState message="Checking competitor sites…" />
        ) : compData.length === 0 ? (
          <EmptyState icon={Users} title="No competitors added" description="Add up to 5 competitor domains in Settings." />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {compData.map((comp) => (
              <div key={comp.domain} className="aeo-card-muted">
                <div className="flex items-center justify-between gap-2">
                  <p className="text-sm font-medium text-slate-900">{comp.domain}</p>
                  <span className="aeo-badge">
                    <span className={cn('aeo-dot', comp.has_llms_txt ? 'bg-emerald-500' : 'bg-slate-300')} />
                    llms.txt
                  </span>
                </div>
                {comp.error ? (
                  <p className="text-xs text-red-600 mt-2">{comp.error}</p>
                ) : (
                  <>
                    <p className="text-xs text-slate-500 mt-2">
                      Schema types: {comp.schema_types.length ? comp.schema_types.join(', ') : 'none found'}
                    </p>
                    <p className="text-xs mt-1">
                      <span className="text-slate-500">They use, you don't: </span>
                      <span className={comp.missing_types.length ? 'text-slate-900' : 'text-slate-500'}>
                        {comp.missing_types.length ? comp.missing_types.join(', ') : 'nothing'}
                      </span>
                    </p>
                  </>
                )}
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
