import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Play, Wrench, Search, AlertTriangle, AlertCircle, Lightbulb } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import type { AuditIssue } from '@/types';
import { cn } from '@/lib/utils';

const severityConfig = {
  critical: { icon: AlertCircle, color: 'text-rose-600', dot: 'bg-rose-500', label: 'Critical' },
  warning: { icon: AlertTriangle, color: 'text-amber-600', dot: 'bg-amber-500', label: 'Warning' },
  opportunity: { icon: Lightbulb, color: 'text-slate-500', dot: 'bg-slate-400', label: 'Opportunity' },
};

export default function SiteAudit() {
  const { data, refetch, isLoading } = useQuery({
    queryKey: ['audit'],
    queryFn: () => api.getAudit(),
  });

  const runMutation = useMutation({
    mutationFn: () => api.runAudit(),
    onSuccess: () => { refetch(); toast.success('Site audit complete'); },
    onError: () => toast.error('Audit failed'),
  });

  const fixMutation = useMutation({
    mutationFn: (issueId: string) => api.fixAuditIssue(issueId),
    onSuccess: (result) => { refetch(); toast.success((result as { message: string }).message); },
    onError: () => toast.error('Auto-fix failed'),
  });

  const summary = (data?.summary || {}) as Record<string, number>;
  const issues = (data?.issues || []) as AuditIssue[];

  return (
    <div>
      <PageHeader
        title="Site Audit"
        description="Scan every published page for AEO issues and fix common ones in one click."
        icon={Search}
        actions={
          <button
            onClick={() => runMutation.mutate()}
            disabled={runMutation.isPending}
            className="aeo-btn-primary"
          >
            <Play size={14} />
            {runMutation.isPending ? 'Scanning…' : 'Run audit'}
          </button>
        }
      />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        {(['critical', 'warning', 'opportunity'] as const).map((sev) => {
          const cfg = severityConfig[sev];
          return (
            <div key={sev} className="aeo-card">
              <div className="flex items-center gap-2">
                <span className={cn('aeo-dot', cfg.dot)} />
                <p className="text-[13px] font-medium text-slate-500">{cfg.label}</p>
              </div>
              <p className="mt-3 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{summary[sev] || 0}</p>
            </div>
          );
        })}
      </div>

      <Card padding={false}>
        {isLoading ? (
          <LoadingState message="Loading audit results…" />
        ) : issues.length === 0 ? (
          <EmptyState
            icon={Search}
            title="No audit data"
            description="Run a site audit to discover AEO issues and opportunities"
            action={
              <button onClick={() => runMutation.mutate()} className="aeo-btn-primary">
                <Play size={14} /> Run first audit
              </button>
            }
          />
        ) : (
          <table className="aeo-table">
            <thead>
              <tr>
                <th>Severity</th>
                <th>Issue</th>
                <th>Status</th>
                <th className="w-28"><span className="sr-only">Action</span></th>
              </tr>
            </thead>
            <tbody>
              {issues.map((issue) => {
                const cfg = severityConfig[issue.severity];
                return (
                  <tr key={issue.id}>
                    <td>
                      <span className="aeo-badge">
                        <cfg.icon size={12} className={cfg.color} />
                        {cfg.label}
                      </span>
                    </td>
                    <td className="text-slate-900">{issue.message}</td>
                    <td>
                      <span className="aeo-badge capitalize">
                        <span className={cn('aeo-dot', issue.status === 'fixed' ? 'bg-emerald-500' : 'bg-slate-300')} />
                        {issue.status}
                      </span>
                    </td>
                    <td>
                      {issue.autofix && issue.status === 'open' && (
                        <button
                          onClick={() => fixMutation.mutate(issue.id)}
                          disabled={fixMutation.isPending}
                          className="aeo-btn-secondary aeo-btn-sm"
                        >
                          <Wrench size={12} /> Auto-fix
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
