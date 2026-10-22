import { useQuery } from '@tanstack/react-query';
import { BarChart3, FileText, HelpCircle, Zap, ArrowRight, Search, Code2, HelpCircle as FAQ, Settings as SettingsIcon } from 'lucide-react';
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
  { to: '/audit', label: 'Run site audit', icon: Search, desc: 'Scan for AEO issues' },
  { to: '/schema', label: 'Configure schema', icon: Code2, desc: 'Build structured data' },
  { to: '/faqs', label: 'Manage FAQs', icon: FAQ, desc: 'AI-generated Q&A' },
  { to: '/settings', label: 'Settings', icon: SettingsIcon, desc: 'API keys & modules' },
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
        description="How ready your content is to be cited by AI answer engines."
        icon={BarChart3}
      />

      {/* Site score */}
      <div className="aeo-card mb-4">
        <div className="flex flex-col sm:flex-row sm:items-center gap-6">
          <ProgressRing score={aeoScore.average || 0} size={88} />
          <div className="flex-1 min-w-0">
            <h2 className="text-sm font-semibold text-slate-900">Site AEO score</h2>
            <p className="text-[13px] text-slate-500 mt-1">
              Average across {aeoScore.total_posts || 0} published posts. Aim for 80+ for strong AI citation readiness.
            </p>
            <div className="mt-4 h-1.5 w-full max-w-md rounded-full bg-slate-100 overflow-hidden">
              <div
                className="h-full rounded-full bg-aeo-600 transition-[width] duration-500"
                style={{ width: `${Math.min(100, aeoScore.average || 0)}%` }}
              />
            </div>
          </div>
          <Link to="/audit" className="aeo-btn-secondary self-start sm:self-center">
            Run audit <ArrowRight size={14} />
          </Link>
        </div>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <StatCard
          title="AEO score"
          value={aeoScore.average || 0}
          subtitle={`${aeoScore.total_posts || 0} posts scored`}
          icon={BarChart3}
          accent="indigo"
        />
        <StatCard
          title="Schema coverage"
          value={`${schemaCoverage.percent || 0}%`}
          subtitle={`${schemaCoverage.with_schema || 0} of ${schemaCoverage.total || 0} pages`}
          icon={FileText}
          accent="emerald"
        />
        <StatCard
          title="FAQ coverage"
          value={`${faqCoverage.percent || 0}%`}
          subtitle={`${faqCoverage.with_faq || 0} posts with FAQs`}
          icon={HelpCircle}
          accent="violet"
        />
        <StatCard
          title="Open issues"
          value={summary.total || 0}
          subtitle={`${summary.critical || 0} critical`}
          icon={Zap}
          accent="amber"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <Card className="lg:col-span-3">
          <CardHeader title="Top pages" description="Highest AEO scores across your site" />
          {topPages.length === 0 ? (
            <EmptyState
              icon={FileText}
              title="No scores yet"
              description="Publish content to start tracking AEO performance"
            />
          ) : (
            <ul className="-mx-2">
              {topPages.slice(0, 5).map((page, i) => (
                <li
                  key={i}
                  className="flex items-center gap-3 px-2 h-10 rounded-md hover:bg-slate-50 transition-colors"
                >
                  <span className="w-5 text-xs text-slate-400 tabular-nums">{i + 1}</span>
                  <span className="text-sm text-slate-800 truncate flex-1">{page.title}</span>
                  <ScoreBadge score={page.score} />
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader title="Quick actions" />
          <div className="-mx-2">
            {quickActions.map(({ to, label, icon: Icon, desc }) => (
              <Link
                key={to}
                to={to}
                className="flex items-center gap-3 px-2 py-2 rounded-md hover:bg-slate-50 transition-colors group"
              >
                <div className="aeo-icon-box">
                  <Icon size={15} strokeWidth={1.75} />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-slate-900">{label}</p>
                  <p className="text-xs text-slate-500">{desc}</p>
                </div>
                <ArrowRight size={14} className="text-slate-300 group-hover:text-slate-600 transition-colors" />
              </Link>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
