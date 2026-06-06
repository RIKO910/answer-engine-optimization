import { useQuery } from '@tanstack/react-query';
import { BarChart3, FileText, HelpCircle, Zap, ArrowRight, Search, Code2, HelpCircle as FAQ } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import StatCard from '@/components/StatCard';
import ScoreBadge from '@/components/ScoreBadge';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import ProgressRing from '@/components/ui/ProgressRing';
import EmptyState from '@/components/ui/EmptyState';

const quickActions = [
  { to: '/audit', label: 'Run Site Audit', icon: Search, desc: 'Scan for AEO issues' },
  { to: '/schema', label: 'Configure Schema', icon: Code2, desc: 'Build structured data' },
  { to: '/faqs', label: 'Manage FAQs', icon: FAQ, desc: 'AI-generated Q&A' },
  { to: '/settings', label: 'Settings', icon: BarChart3, desc: 'API keys & modules' },
];

export default function Dashboard() {
  const { data, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => api.getDashboard(),
  });

  if (isLoading) return <LoadingState message="Loading your AEO dashboard..." />;

  const analytics = (data?.analytics || {}) as Record<string, unknown>;
  const aeoScore = (analytics.aeo_score || {}) as Record<string, number>;
  const schemaCoverage = (analytics.schema_coverage || {}) as Record<string, number>;
  const faqCoverage = (analytics.faq_coverage || {}) as Record<string, number>;
  const audit = (data?.audit || {}) as Record<string, unknown>;
  const summary = (audit.summary || {}) as Record<string, number>;
  const topPages = (analytics.top_pages || []) as Array<{ title: string; score: number }>;

  return (
    <div>
      <PageHeader
        title="Dashboard"
        description="Monitor your Answer Engine Optimization performance at a glance"
        icon={BarChart3}
      />

      {/* Hero score card */}
      <div className="aeo-card mb-6 bg-gradient-brand-subtle border-aeo-100/60 overflow-hidden relative">
        <div className="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-aeo-200/20 blur-2xl" />
        <div className="flex flex-col sm:flex-row items-center gap-6 relative">
          <ProgressRing score={aeoScore.average || 0} size={100} />
          <div className="text-center sm:text-left">
            <h2 className="text-lg font-bold text-slate-900">Site AEO Score</h2>
            <p className="text-sm text-slate-600 mt-1">
              Average across {aeoScore.total_posts || 0} published posts
            </p>
            <p className="text-xs text-slate-400 mt-2">
              Target: 80+ for optimal AI citation readiness
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          title="AEO Score"
          value={aeoScore.average || 0}
          subtitle={`${aeoScore.total_posts || 0} posts scored`}
          icon={BarChart3}
          accent="indigo"
        />
        <StatCard
          title="Schema Coverage"
          value={`${schemaCoverage.percent || 0}%`}
          subtitle={`${schemaCoverage.with_schema || 0} of ${schemaCoverage.total || 0} pages`}
          icon={FileText}
          accent="emerald"
        />
        <StatCard
          title="FAQ Coverage"
          value={`${faqCoverage.percent || 0}%`}
          subtitle={`${faqCoverage.with_faq || 0} posts with FAQs`}
          icon={HelpCircle}
          accent="violet"
        />
        <StatCard
          title="Open Issues"
          value={summary.total || 0}
          subtitle={`${summary.critical || 0} critical`}
          icon={Zap}
          accent="amber"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <Card className="lg:col-span-3">
          <CardHeader title="Top Performing Pages" description="Highest AEO scores across your site" />
          {topPages.length === 0 ? (
            <EmptyState
              icon={FileText}
              title="No scores yet"
              description="Publish content to start tracking AEO performance"
            />
          ) : (
            <ul className="space-y-1">
              {topPages.slice(0, 5).map((page, i) => (
                <li
                  key={i}
                  className="flex items-center gap-4 px-3 py-3 rounded-xl hover:bg-slate-50 transition-colors group"
                >
                  <span className="flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 text-xs font-bold text-slate-500 group-hover:bg-aeo-100 group-hover:text-aeo-600 transition-colors">
                    {i + 1}
                  </span>
                  <span className="text-sm text-slate-700 truncate flex-1 font-medium">{page.title}</span>
                  <ScoreBadge score={page.score} />
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader title="Quick Actions" description="Jump to key tasks" />
          <div className="space-y-2">
            {quickActions.map(({ to, label, icon: Icon, desc }) => (
              <Link
                key={to}
                to={to}
                className="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-aeo-200 hover:bg-aeo-50/50 transition-all group"
              >
                <div className="aeo-icon-box bg-slate-50 text-slate-500 group-hover:bg-aeo-100 group-hover:text-aeo-600 transition-colors">
                  <Icon size={16} />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-slate-800">{label}</p>
                  <p className="text-xs text-slate-400">{desc}</p>
                </div>
                <ArrowRight size={14} className="text-slate-300 group-hover:text-aeo-500 transition-colors" />
              </Link>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
