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
  critical: { icon: AlertCircle, color: 'text-rose-600', bg: 'bg-rose-50 ring-rose-200/60', label: 'Critical' },
  warning: { icon: AlertTriangle, color: 'text-amber-600', bg: 'bg-amber-50 ring-amber-200/60', label: 'Warning' },
  opportunity: { icon: Lightbulb, color: 'text-blue-600', bg: 'bg-blue-50 ring-blue-200/60', label: 'Opportunity' },
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
        description="Full-site AEO scan with one-click auto-fix for common issues"
        icon={Search}
        actions={
          <button
            onClick={() => runMutation.mutate()}
            disabled={runMutation.isPending}
            className="aeo-btn-primary"
          >
            <Play size={16} />
            {runMutation.isPending ? 'Scanning...' : 'Run Audit'}
          </button>
        }
      />

      <div className="grid grid-cols-3 gap-4 mb-6">
        {(['critical', 'warning', 'opportunity'] as const).map((sev) => {
          const cfg = severityConfig[sev];
          return (
            <div key={sev} className="aeo-card-hover text-center">
              <div className={cn('aeo-icon-box mx-auto mb-3 ring-1', cfg.bg)}>
                <cfg.icon size={20} className={cfg.color} />
              </div>
              <p className="text-3xl font-bold text-slate-900 tabular-nums">{summary[sev] || 0}</p>
              <p className="text-xs font-medium text-slate-500 mt-1 uppercase tracking-wider">{cfg.label}</p>
            </div>
          );
        })}
      </div>

      <Card padding={false} className="overflow-hidden">
        {isLoading ? (
          <LoadingState message="Loading audit results..." />
        ) : issues.length === 0 ? (
          <EmptyState
            icon={Search}
            title="No audit data"
            description="Run a site audit to discover AEO issues and opportunities"
            action={
              <button onClick={() => runMutation.mutate()} className="aeo-btn-primary">
                <Play size={16} /> Run First Audit
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
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {issues.map((issue) => {
                const cfg = severityConfig[issue.severity];
                return (
                  <tr key={issue.id}>
                    <td>
                      <span className={cn('aeo-badge ring-1', cfg.bg, cfg.color)}>
                        <cfg.icon size={12} />
                        {cfg.label}
                      </span>
                    </td>
                    <td className="font-medium">{issue.message}</td>
                    <td>
                      <span className={cn(
                        'aeo-badge',
                        issue.status === 'fixed' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200/60' : 'bg-slate-100 text-slate-600'
                      )}>
                        {issue.status}
                      </span>
                    </td>
                    <td>
                      {issue.autofix && issue.status === 'open' && (
                        <button
                          onClick={() => fixMutation.mutate(issue.id)}
                          disabled={fixMutation.isPending}
                          className="aeo-btn-secondary text-xs !py-1.5 !px-3"
                        >
                          <Wrench size={12} /> Auto-Fix
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
