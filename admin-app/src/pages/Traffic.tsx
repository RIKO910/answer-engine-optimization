import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend } from 'recharts';
import { Activity, Bot, ExternalLink, Link2, RefreshCw, Upload, Unplug } from 'lucide-react';
import { get, post } from '@/lib/api';
import { __, sprintf } from '@/lib/i18n';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import Stat from '@/components/ui/Stat';
import Tabs from '@/components/ui/Tabs';
import ProGate from '@/components/ProGate';
import { isPro } from '@/lib/pro';

interface TrafficReport {
  days: number;
  bots: Array<{ agent: string; source: string; hits: number; last_seen: string }>;
  referrals: Array<{ source: string; visits: number }>;
  daily: Array<{ date: string; bots: number; referrals: number }>;
  top_bot_pages: Array<{ url: string; hits: number }>;
  top_referred: Array<{ url: string; visits: number }>;
  bot_errors: number;
}

interface GoogleStatus {
  configured: boolean;
  connected: boolean;
  redirect_uri: string;
  auth_url: string;
  gsc_site: string;
  ga4_property: string;
}

type Row = { key: string; clicks: number; impressions: number; ctr: number; position: number };

interface GoogleReports {
  gsc: { queries: Row[]; questions: Row[]; pages: Row[] } | null;
  gsc_error: string;
  ga4: { sources: Array<{ source: string; assistant: string; sessions: number; engaged: number; key_events: number }>; pages: Array<{ page: string; sessions: number }> } | null;
  ga4_error: string;
}

interface BingReport {
  imported_at?: string;
  rows?: number;
  total?: number;
  pages?: Array<{ page: string; citations: number }>;
  queries?: Array<{ query: string; citations: number }>;
  daily?: Array<{ date: string; citations: number }>;
}

const chartTooltip = { borderRadius: 8, border: '1px solid #e4e4e7', fontSize: 12 };
const sum = <T,>(rows: T[], k: keyof T) => rows.reduce((a, r) => a + Number(r[k] || 0), 0);
const path = (u: string) => {
  try {
    return new URL(u).pathname;
  } catch {
    return u;
  }
};

function AiTraffic() {
  const [days, setDays] = useState(30);
  const { data, isLoading } = useQuery({ queryKey: ['traffic', days], queryFn: () => get<TrafficReport>(`/traffic?days=${days}`) });
  if (isLoading || !data) return <LoadingState />;

  const totalBots = sum(data.bots, 'hits');
  const totalRefs = sum(data.referrals, 'visits');

  return (
    <>
      <div className="flex justify-end mb-4">
        <select className="aeo-select w-auto" value={days} onChange={(e) => setDays(Number(e.target.value))} aria-label={__('Period')}>
          {[7, 30, 90].map((d) => (
            <option key={d} value={d}>{sprintf(__('Last %d days'), d)}</option>
          ))}
        </select>
      </div>
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <Stat label={__('AI crawler visits')} value={totalBots} hint={sprintf(__('%d different crawlers'), data.bots.length)} />
        <Stat label={__('Visitors from AI assistants')} value={totalRefs} hint={__('ChatGPT, Perplexity, Gemini, Copilot, Claude…')} />
        <Stat label={__('Crawler errors')} value={data.bot_errors} hint={__('4xx/5xx responses served to AI crawlers')} />
        <Stat label={__('Top assistant')} value={<span className="text-base">{data.referrals[0]?.source || '—'}</span>} />
      </div>

      <Card className="mb-4">
        <CardHeader title={__('Daily activity')} />
        {data.daily.length === 0 ? (
          <EmptyState icon={Activity} title={__('No AI traffic logged yet')} description={__('Crawler visits and AI referrals appear here as they happen. Logging can be turned off under AI access.')} />
        ) : (
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={data.daily} barGap={2} margin={{ top: 4, right: 0, left: -24, bottom: 0 }}>
              <CartesianGrid vertical={false} stroke="#f4f4f5" />
              <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} />
              <Tooltip cursor={{ fill: '#fafafa' }} contentStyle={chartTooltip} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="bots" name={__('Crawler visits')} fill="#a1a1aa" radius={[3, 3, 0, 0]} />
              <Bar dataKey="referrals" name={__('AI referrals')} fill="#4f46e5" radius={[3, 3, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card padding={false}>
          <div className="p-5 pb-0"><CardHeader title={__('Crawlers')} /></div>
          {data.bots.length === 0 ? (
            <EmptyState icon={Bot} title={__('None yet')} description={__('No AI crawler has visited in this period.')} />
          ) : (
            <table className="aeo-table">
              <thead><tr><th>{__('Crawler')}</th><th className="text-right">{__('Visits')}</th><th>{__('Last seen')}</th></tr></thead>
              <tbody>
                {data.bots.map((b) => (
                  <tr key={b.agent}>
                    <td><span className="text-slate-900">{b.agent}</span> <span className="text-slate-400">· {b.source}</span></td>
                    <td className="text-right tabular-nums">{b.hits}</td>
                    <td className="text-xs text-slate-500">{b.last_seen}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
        <Card padding={false}>
          <div className="p-5 pb-0"><CardHeader title={__('Visits from AI assistants')} /></div>
          {data.referrals.length === 0 ? (
            <EmptyState icon={Link2} title={__('None yet')} description={__('Visitors who click a link in an AI answer are counted here (no cookies, no IP).')} />
          ) : (
            <table className="aeo-table">
              <thead><tr><th>{__('Assistant')}</th><th className="text-right">{__('Visits')}</th></tr></thead>
              <tbody>
                {data.referrals.map((r) => (
                  <tr key={r.source}><td className="text-slate-900">{r.source}</td><td className="text-right tabular-nums">{r.visits}</td></tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
        <Card padding={false}>
          <div className="p-5 pb-0"><CardHeader title={__('Most crawled pages')} /></div>
          <table className="aeo-table">
            <tbody>
              {data.top_bot_pages.map((r) => (
                <tr key={r.url}><td className="break-all">{r.url}</td><td className="text-right tabular-nums">{r.hits}</td></tr>
              ))}
              {data.top_bot_pages.length === 0 && <tr><td className="text-slate-400">—</td></tr>}
            </tbody>
          </table>
        </Card>
        <Card padding={false}>
          <div className="p-5 pb-0"><CardHeader title={__('Pages AI visitors land on')} /></div>
          <table className="aeo-table">
            <tbody>
              {data.top_referred.map((r) => (
                <tr key={r.url}><td className="break-all">{r.url}</td><td className="text-right tabular-nums">{r.visits}</td></tr>
              ))}
              {data.top_referred.length === 0 && <tr><td className="text-slate-400">—</td></tr>}
            </tbody>
          </table>
        </Card>
      </div>
    </>
  );
}

function Google() {
  const qc = useQueryClient();
  const status = useQuery({ queryKey: ['google-status'], queryFn: () => get<GoogleStatus>('/google/status') });
  const [clientId, setClientId] = useState('');
  const [secret, setSecret] = useState('');
  const st = status.data;
  const sites = useQuery({
    queryKey: ['google-sites'],
    queryFn: () => get<{ sites: string[] | { message: string }; properties: Array<{ id: string; name: string }> | { message: string } }>('/google/sites'),
    enabled: !!st?.connected,
  });
  const reports = useQuery({
    queryKey: ['google-reports'],
    queryFn: () => get<GoogleReports>('/google/reports'),
    enabled: !!st?.connected && !!(st.gsc_site || st.ga4_property),
  });

  const creds = useMutation({
    mutationFn: () => post<GoogleStatus>('/google/credentials', { client_id: clientId, client_secret: secret }),
    onSuccess: (d) => {
      qc.setQueryData(['google-status'], d);
      setSecret('');
      toast.success(__('Credentials saved'));
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const select = useMutation({
    mutationFn: (p: { gsc_site: string; ga4_property: string }) => post<GoogleStatus>('/google/select', p),
    onSuccess: (d) => {
      qc.setQueryData(['google-status'], d);
      qc.invalidateQueries({ queryKey: ['google-reports'] });
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const disconnect = useMutation({
    mutationFn: () => post<GoogleStatus>('/google/disconnect'),
    onSuccess: (d) => qc.setQueryData(['google-status'], d),
  });
  const refresh = useMutation({
    mutationFn: () => get<GoogleReports>('/google/reports?refresh=1'),
    onSuccess: (d) => qc.setQueryData(['google-reports'], d),
    onError: (e: Error) => toast.error(e.message),
  });

  if (status.isLoading || !st) return <LoadingState />;

  if (!st.connected) {
    return (
      <Card>
        <CardHeader
          title={__('Connect Google Search Console and Analytics')}
          description={__('Uses your own Google Cloud OAuth client, so data goes straight from Google to this site. Read-only access.')}
        />
        <ol className="text-[13px] text-slate-600 space-y-1.5 mb-5" style={{ listStyle: 'decimal', paddingLeft: 18 }}>
          <li>{__('In Google Cloud Console, enable the Search Console API and the Google Analytics Data and Admin APIs.')}</li>
          <li>{__('Create an OAuth client ID of type "Web application".')}</li>
          <li>
            {__('Add this authorized redirect URI:')} <code className="text-xs bg-slate-100 rounded px-1.5 py-0.5 break-all">{st.redirect_uri}</code>
          </li>
          <li>{__('Paste the client ID and secret below, save, then connect.')}</li>
        </ol>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <label className="aeo-label" htmlFor="g-id">{__('Client ID')}</label>
            <input id="g-id" className="aeo-input font-mono text-xs" value={clientId} onChange={(e) => setClientId(e.target.value)} placeholder={st.configured ? __('Saved (enter to replace)') : '…apps.googleusercontent.com'} />
          </div>
          <div>
            <label className="aeo-label" htmlFor="g-secret">{__('Client secret')}</label>
            <input id="g-secret" type="password" autoComplete="off" className="aeo-input font-mono text-xs" value={secret} onChange={(e) => setSecret(e.target.value)} placeholder={st.configured ? '••••••••' : 'GOCSPX-…'} />
          </div>
        </div>
        <div className="flex gap-2">
          <button type="button" className="aeo-btn-secondary" disabled={!clientId || creds.isPending} onClick={() => creds.mutate()}>
            {__('Save credentials')}
          </button>
          {st.auth_url && (
            <a className="aeo-btn-primary" href={st.auth_url}>
              {__('Connect Google account')} <ExternalLink size={13} />
            </a>
          )}
        </div>
      </Card>
    );
  }

  const siteList = Array.isArray(sites.data?.sites) ? sites.data.sites : [];
  const propList = Array.isArray(sites.data?.properties) ? sites.data.properties : [];
  const r = reports.data;

  return (
    <>
      <Card className="mb-4">
        <div className="flex flex-col lg:flex-row lg:items-end gap-4">
          <div className="flex-1">
            <label className="aeo-label" htmlFor="g-site">{__('Search Console property')}</label>
            <select id="g-site" className="aeo-select" value={st.gsc_site} onChange={(e) => select.mutate({ gsc_site: e.target.value, ga4_property: st.ga4_property })}>
              <option value="">{__('— Choose —')}</option>
              {siteList.map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </div>
          <div className="flex-1">
            <label className="aeo-label" htmlFor="g-prop">{__('GA4 property')}</label>
            <select id="g-prop" className="aeo-select" value={st.ga4_property} onChange={(e) => select.mutate({ gsc_site: st.gsc_site, ga4_property: e.target.value })}>
              <option value="">{__('— Choose —')}</option>
              {propList.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
          <div className="flex gap-2">
            <button type="button" className="aeo-btn-secondary" onClick={() => refresh.mutate()} disabled={refresh.isPending}>
              <RefreshCw size={13} className={refresh.isPending ? 'animate-spin' : ''} /> {__('Refresh')}
            </button>
            <button type="button" className="aeo-btn-ghost" onClick={() => disconnect.mutate()}>
              <Unplug size={13} /> {__('Disconnect')}
            </button>
          </div>
        </div>
      </Card>

      {reports.isLoading && <LoadingState message={__('Loading Google data…')} />}
      {r && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <Card padding={false}>
            <div className="p-5 pb-0">
              <CardHeader title={__('Question-style searches')} description={__('Long and question queries, the kind AI Overviews answer. Last 28 days.')} />
              {r.gsc_error && <p className="text-xs text-red-600 mb-3">{r.gsc_error}</p>}
            </div>
            <table className="aeo-table">
              <thead><tr><th>{__('Query')}</th><th className="text-right">{__('Clicks')}</th><th className="text-right">{__('Impr.')}</th><th className="text-right">{__('Pos.')}</th></tr></thead>
              <tbody>
                {(r.gsc?.questions || []).map((q) => (
                  <tr key={q.key}><td className="text-slate-900">{q.key}</td><td className="text-right tabular-nums">{q.clicks}</td><td className="text-right tabular-nums">{q.impressions}</td><td className="text-right tabular-nums">{q.position}</td></tr>
                ))}
                {(r.gsc?.questions || []).length === 0 && <tr><td colSpan={4} className="text-slate-400">—</td></tr>}
              </tbody>
            </table>
          </Card>
          <Card padding={false}>
            <div className="p-5 pb-0">
              <CardHeader title={__('Sessions from AI assistants (GA4)')} description={__('Sources such as chatgpt.com, perplexity.ai, gemini.google.com. Last 28 days.')} />
              {r.ga4_error && <p className="text-xs text-red-600 mb-3">{r.ga4_error}</p>}
            </div>
            <table className="aeo-table">
              <thead><tr><th>{__('Source')}</th><th className="text-right">{__('Sessions')}</th><th className="text-right">{__('Engaged')}</th><th className="text-right">{__('Key events')}</th></tr></thead>
              <tbody>
                {(r.ga4?.sources || []).map((s) => (
                  <tr key={s.source}><td><span className="text-slate-900">{s.assistant || s.source}</span> <span className="text-slate-400 text-xs">{s.source}</span></td><td className="text-right tabular-nums">{s.sessions}</td><td className="text-right tabular-nums">{s.engaged}</td><td className="text-right tabular-nums">{s.key_events}</td></tr>
                ))}
                {(r.ga4?.sources || []).length === 0 && <tr><td colSpan={4} className="text-slate-400">—</td></tr>}
              </tbody>
            </table>
          </Card>
          <Card padding={false} className="lg:col-span-2">
            <div className="p-5 pb-0"><CardHeader title={__('Top pages in Google search')} /></div>
            <table className="aeo-table">
              <thead><tr><th>{__('Page')}</th><th className="text-right">{__('Clicks')}</th><th className="text-right">{__('Impressions')}</th><th className="text-right">CTR</th><th className="text-right">{__('Position')}</th></tr></thead>
              <tbody>
                {(r.gsc?.pages || []).map((p) => (
                  <tr key={p.key}><td className="break-all">{path(p.key)}</td><td className="text-right tabular-nums">{p.clicks}</td><td className="text-right tabular-nums">{p.impressions}</td><td className="text-right tabular-nums">{p.ctr}%</td><td className="text-right tabular-nums">{p.position}</td></tr>
                ))}
              </tbody>
            </table>
          </Card>
        </div>
      )}
    </>
  );
}

function Bing() {
  const qc = useQueryClient();
  const report = useQuery({ queryKey: ['bing'], queryFn: () => get<BingReport>('/bing') });
  const imp = useMutation({
    mutationFn: (csv: string) => post<BingReport>('/bing/import', { csv }),
    onSuccess: (d) => {
      qc.setQueryData(['bing'], d);
      toast.success(sprintf(__('Imported %d rows'), d.rows ?? 0));
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const onFile = (f?: File) => {
    if (!f) return;
    f.text().then((t) => imp.mutate(t));
  };
  const d = report.data;

  return (
    <>
      <Card className="mb-4">
        <CardHeader
          title={__('Bing Webmaster Tools: AI Performance')}
          description={__('Bing reports how often Copilot and Bing AI answers cite your pages, but has no API for it yet. Export the AI Performance report as CSV and import it here.')}
          action={
            <label className="aeo-btn-primary aeo-btn-sm cursor-pointer">
              <Upload size={12} /> {imp.isPending ? __('Importing…') : __('Import CSV')}
              <input type="file" accept=".csv,text/csv" className="sr-only" onChange={(e) => onFile(e.target.files?.[0])} />
            </label>
          }
        />
        {d?.imported_at ? (
          <p className="text-xs text-slate-500">{sprintf(__('Last import %1$s · %2$d rows · %3$d citations'), d.imported_at, d.rows ?? 0, d.total ?? 0)}</p>
        ) : (
          <p className="text-xs text-slate-500">{__('Nothing imported yet.')}</p>
        )}
      </Card>
      {d?.imported_at && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <Card>
            <CardHeader title={__('Citations per day')} />
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={d.daily || []} margin={{ top: 4, right: 0, left: -24, bottom: 0 }}>
                <CartesianGrid vertical={false} stroke="#f4f4f5" />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} />
                <Tooltip cursor={{ fill: '#fafafa' }} contentStyle={chartTooltip} />
                <Bar dataKey="citations" name={__('Citations')} fill="#4f46e5" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </Card>
          <Card padding={false}>
            <div className="p-5 pb-0"><CardHeader title={__('Most cited pages')} /></div>
            <table className="aeo-table">
              <tbody>
                {(d.pages || []).map((p) => (
                  <tr key={p.page}><td className="break-all">{path(p.page)}</td><td className="text-right tabular-nums">{p.citations}</td></tr>
                ))}
              </tbody>
            </table>
          </Card>
        </div>
      )}
    </>
  );
}

const googleReturn = new URLSearchParams(window.location.search).get('aeo_google');

export default function Traffic() {
  const [tab, setTab] = useState<'ai' | 'google' | 'bing'>(googleReturn ? 'google' : 'ai');
  useEffect(() => {
    if (googleReturn === 'connected') toast.success(__('Google account connected'));
    else if (googleReturn) toast.error(__('Google connection was not completed'));
  }, []);
  return (
    <div>
      <PageHeader title={__('Traffic')} description={__('AI crawler visits, visitors arriving from AI assistants, and Google and Bing data in one place.')} />
      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'ai', label: __('AI crawlers & referrals') },
          { id: 'google', label: isPro() ? 'Google' : 'Google · Pro' },
          { id: 'bing', label: isPro() ? 'Bing' : 'Bing · Pro' },
        ]}
      />
      {tab === 'ai' && <AiTraffic />}
      {tab === 'google' && (
        <ProGate feature="google">
          <Google />
        </ProGate>
      )}
      {tab === 'bing' && (
        <ProGate feature="bing">
          <Bing />
        </ProGate>
      )}
    </div>
  );
}
