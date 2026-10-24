import { useEffect, useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend } from 'recharts';
import { Link } from 'react-router-dom';
import { RefreshCw, BarChart3, Users, Check, Minus, AlertTriangle, Plus, Sparkles, Save, FileDown } from 'lucide-react';
import { api, get, post } from '@/lib/api';
import { __, _n, sprintf } from '@/lib/i18n';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import Stat from '@/components/ui/Stat';
import Modal from '@/components/ui/Modal';
import ProGate, { ProBadge } from '@/components/ProGate';
import { isPro } from '@/lib/pro';
import type { EngineResult, ScanProgress, VisibilitySummary } from '@/types';
import { cn } from '@/lib/utils';

interface VisibilityData {
  engines: string[];
  all: Array<{ engine: string; label: string; locked?: boolean }>;
  max_prompts?: number;
  summary: VisibilitySummary | null;
  history: Array<{ scan_id: string; date: string; cited_rate: number; mentioned_rate: number }>;
  progress: ScanProgress | null;
  brands: string[];
  own_host: string;
  is_local: boolean;
  domain: string;
}

interface AnswerRow {
  keyword: string;
  engine: string;
  answer: string;
  cited: string | number;
  mentioned: string | number;
  position: string | number;
  sentiment: string;
  sources: string[] | null;
  cited_urls: string[] | null;
  competitors: string[] | null;
  error: string;
  created_at: string;
}

const pct = (a: number, b: number) => (b ? Math.round((a / b) * 100) : 0);

function Cell({ r, onOpen }: { r?: EngineResult; onOpen: (id: number) => void }) {
  if (!r) return <span className="text-slate-300">—</span>;
  if (r.error) {
    return (
      <span className="inline-flex items-center gap-1 text-xs text-amber-600" title={r.error}>
        <AlertTriangle size={13} /> {__('Error')}
      </span>
    );
  }
  return (
    <button
      type="button"
      onClick={() => onOpen(r.id)}
      className={cn(
        'inline-flex items-center gap-1 h-7 px-2 m-0 rounded-md border-0 text-xs cursor-pointer',
        r.cited ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : r.mentioned ? 'bg-aeo-50 text-aeo-700 hover:bg-aeo-100' : 'bg-slate-50 text-slate-500 hover:bg-slate-100'
      )}
      title={__('View the answer')}
    >
      {r.cited ? <Check size={13} /> : <Minus size={13} />}
      {r.cited ? (r.position ? sprintf(__('Cited #%d'), r.position) : __('Cited')) : r.mentioned ? __('Mentioned') : __('Not cited')}
    </button>
  );
}

export default function Analytics() {
  const qc = useQueryClient();
  const caps = window.aeoGeniusData?.caps;
  const vis = useQuery({ queryKey: ['visibility'], queryFn: () => get<VisibilityData>('/visibility') });
  const settings = useQuery({ queryKey: ['settings'], queryFn: () => api.getSettings() });
  const competitors = useQuery({ queryKey: ['competitors'], queryFn: () => api.getCompetitorAnalysis() });
  const [progress, setProgress] = useState<ScanProgress | null>(null);
  const [answerId, setAnswerId] = useState<number | null>(null);
  const running = useRef(false);

  // Prompt + brand editing.
  const s = (settings.data || {}) as Record<string, unknown>;
  const [keywords, setKeywords] = useState<string>('');
  const [brands, setBrands] = useState<string>('');
  const [engines, setEngines] = useState<string[]>([]);
  const [domain, setDomain] = useState('');
  useEffect(() => {
    if (vis.data) setDomain(vis.data.domain || '');
  }, [vis.data]);
  useEffect(() => {
    if (!settings.data) return;
    setKeywords(((s.tracked_keywords as string[]) || []).join('\n'));
    setBrands(((s.brand_names as string[]) || []).join('\n'));
    setEngines((s.citation_engines as string[]) || []);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [settings.data]);

  const suggestions = useQuery({ queryKey: ['prompt-suggestions'], queryFn: () => get<Array<{ prompt: string; source: string }>>('/prompts/suggest'), enabled: false });
  const answer = useQuery({
    queryKey: ['answer', answerId],
    queryFn: () => get<AnswerRow>(`/visibility/answer/${answerId}`),
    enabled: answerId !== null,
  });

  const saveTracking = useMutation({
    mutationFn: () => {
      const lines = (t: string) => t.split('\n').map((l) => l.trim()).filter(Boolean);
      return api.updateSettings({ tracked_keywords: lines(keywords), brand_names: lines(brands), citation_engines: engines, visibility_domain: domain });
    },
    onSuccess: (d) => {
      qc.setQueryData(['settings'], d);
      qc.invalidateQueries({ queryKey: ['visibility'] });
      toast.success(__('Tracking saved'));
    },
    onError: (e: Error) => toast.error(e.message),
  });

  // Each check is a live web search (up to a minute or two), so the scan runs in
  // small server requests. A failed request is retried; a scan interrupted by a
  // closed tab or a server timeout can be resumed where it stopped.
  const continueWithRetry = async (): Promise<ScanProgress> => {
    let lastError: unknown;
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        return await post<ScanProgress>('/visibility/continue');
      } catch (e) {
        lastError = e;
        await new Promise((r) => setTimeout(r, 2000 * (attempt + 1)));
      }
    }
    throw lastError;
  };

  const runScan = async (resume = false) => {
    if (running.current) return;
    running.current = true;
    try {
      let p = resume ? await continueWithRetry() : await post<ScanProgress>('/visibility/start');
      setProgress(p);
      while (!p.finished && p.done < p.total) {
        // Another request (e.g. one still running from a closed tab) holds the scan.
        if (p.busy) await new Promise((r) => setTimeout(r, 4000));
        p = await continueWithRetry();
        setProgress(p);
      }
      await qc.invalidateQueries({ queryKey: ['visibility'] });
      toast.success(__('Scan finished'));
    } catch (e) {
      await qc.invalidateQueries({ queryKey: ['visibility'] });
      toast.error(sprintf(__('The scan stopped: %s. Use Resume to continue where it stopped.'), (e as Error).message || __('server error')));
    } finally {
      running.current = false;
      setProgress(null);
    }
  };

  const report = useMutation({
    mutationFn: () => get<{ url: string }>('/report-url'),
    onSuccess: (d) => window.open(d.url, '_blank', 'noopener'),
    onError: (e: Error) => toast.error(e.message),
  });

  if (vis.isLoading) return <LoadingState message={__('Loading AI visibility…')} />;
  if (vis.isError) {
    return (
      <div>
        <PageHeader title={__('AI visibility')} />
        <div className="rounded-lg border border-red-200 bg-red-50 p-4 flex items-center justify-between gap-4">
          <p className="text-[13px] text-red-800">
            {sprintf(__('Could not load AI visibility data: %s'), (vis.error as Error)?.message || __('server error'))}
          </p>
          <button type="button" className="aeo-btn-secondary aeo-btn-sm" onClick={() => vis.refetch()}>{__('Try again')}</button>
        </div>
      </div>
    );
  }

  const data = vis.data;
  const available = data?.engines || [];
  // Only a real scan result counts; older versions sent [] for "no scan yet".
  const rawSummary = data?.summary as VisibilitySummary | null | undefined;
  const summary: VisibilitySummary | null =
    rawSummary && !Array.isArray(rawSummary) && rawSummary.scan_id
      ? {
          ...rawSummary,
          engines: rawSummary.engines || [],
          keywords: rawSummary.keywords || [],
          share_of_voice: rawSummary.share_of_voice || [],
          sentiment: rawSummary.sentiment || { positive: 0, neutral: 0, negative: 0 },
        }
      : null;
  const history = Array.isArray(data?.history) ? data!.history : [];
  const labels = Object.fromEntries((data?.all || []).map((e) => [e.engine, e.label]));
  const scanEngines = summary?.engines?.map((e) => e.engine) || [];
  const compData = competitors.data?.competitors || [];
  const keywordCount = keywords.split('\n').filter((l) => l.trim()).length;
  const saved = data?.progress;
  const canResume = !progress && !!saved && !saved.finished && saved.done < saved.total;
  const noScanYet = !summary || !summary.scan_id;

  return (
    <div>
      <PageHeader
        title={__('AI visibility')}
        description={__('Whether ChatGPT, Perplexity, Gemini and Claude cite or mention you for the questions your customers ask.')}
        actions={
          <>
            {isPro() ? (
              <button type="button" className="aeo-btn-secondary" onClick={() => report.mutate()} disabled={report.isPending}>
                <FileDown size={14} /> {__('Client report')}
              </button>
            ) : (
              <Link to="/license" className="aeo-btn-secondary" title={__('Client reports are a Pro feature')}>
                <FileDown size={14} /> {__('Client report')} <ProBadge />
              </Link>
            )}
            {canResume && (
              <button type="button" className="aeo-btn-secondary" onClick={() => runScan(true)} disabled={caps?.ai === false}>
                {sprintf(__('Resume (%1$d / %2$d)'), saved!.done, saved!.total)}
              </button>
            )}
            <button
              onClick={() => runScan(false)}
              disabled={!!progress || available.length === 0 || caps?.ai === false}
              className="aeo-btn-primary"
              title={available.length ? '' : __('Add an API key for at least one engine in Settings')}
            >
              <RefreshCw size={14} className={progress ? 'animate-spin' : ''} />
              {progress ? sprintf(__('Scanning %1$d / %2$d'), progress.done, progress.total) : __('Run scan')}
            </button>
          </>
        }
      />

      {progress && (
        <div className="mb-4 h-1.5 rounded-full bg-slate-100 overflow-hidden" role="progressbar" aria-valuenow={pct(progress.done, progress.total)}>
          <div className="h-full bg-aeo-600 transition-[width] duration-300" style={{ width: `${pct(progress.done, progress.total)}%` }} />
        </div>
      )}

      {data?.is_local && !data.domain && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 flex flex-col md:flex-row md:items-center gap-3 justify-between">
          <p className="text-[13px] text-amber-900 max-w-2xl">
            {sprintf(
              __('This site runs at %s, which AI engines can never link to, so every answer would count as "not cited". Enter the domain of your live site to measure it from here.'),
              data.own_host || 'localhost'
            )}
          </p>
          <div className="flex gap-2 flex-shrink-0">
            <input className="aeo-input w-56" placeholder="example.com" value={domain} onChange={(e) => setDomain(e.target.value)} aria-label={__('Live domain')} />
            <button type="button" className="aeo-btn-primary" disabled={!domain.trim() || saveTracking.isPending} onClick={() => saveTracking.mutate()}>
              {__('Save')}
            </button>
          </div>
        </div>
      )}

      {available.length === 0 && (
        <div className="aeo-card-muted mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <p className="text-[13px] text-slate-600">
            {isPro()
              ? __('Scans ask each engine your tracked questions with live web search and record whether the answer links to or names your site. Add a Perplexity, OpenAI, Gemini or Anthropic key to start.')
              : __('Scans ask Perplexity your tracked questions with live web search and record whether the answer links to or names your site. Add a Perplexity API key to start. ChatGPT, Gemini and Claude tracking is in Pro.')}
          </p>
          <Link to="/settings" className="aeo-btn-secondary aeo-btn-sm self-start sm:self-center">
            {__('Add a key')}
          </Link>
        </div>
      )}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <Stat label={__('Cited')} value={summary ? `${pct(summary.cited, summary.checked)}%` : '—'} hint={summary && !noScanYet ? sprintf(__('%1$d of %2$d answers link to %3$s'), summary.cited, summary.checked, data?.own_host || __('you')) : __('No scan yet')} />
        <Stat label={__('Mentioned')} value={summary ? `${pct(summary.mentioned, summary.checked)}%` : '—'} hint={__('Brand named, with or without a link')} />
        <Stat
          label={__('Sentiment')}
          value={summary ? `${summary.sentiment.positive}/${summary.sentiment.neutral}/${summary.sentiment.negative}` : '—'}
          hint={__('Positive / neutral / negative mentions')}
        />
        <Stat label={__('Last scan')} value={<span className="text-base">{summary?.scanned_at?.slice(0, 16) || '—'}</span>} hint={sprintf(_n('%d engine connected', '%d engines connected', available.length), available.length)} />
      </div>

      {summary && summary.engines.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          {summary.engines.map((e) => (
            <div key={e.engine} className="aeo-card">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-900">{e.label}</p>
                {e.errors > 0 && (
                  <span className="text-2xs text-amber-600" title={__('Some checks failed')}>
                    {sprintf(__('%d errors'), e.errors)}
                  </span>
                )}
              </div>
              <p className="text-2xl font-semibold tabular-nums mt-2">{pct(e.cited, e.checked)}%</p>
              {e.checked === 0 && e.error ? (
                <p className="text-xs text-amber-700 mt-1 break-words" title={e.error}>{e.error.length > 120 ? `${e.error.slice(0, 120)}…` : e.error}</p>
              ) : null}
              <p className="text-xs text-slate-500">
                {sprintf(__('%1$d/%2$d cited'), e.cited, e.checked)}
                {e.avg_position ? ` · ${sprintf(__('avg. position %s'), String(e.avg_position))}` : ''}
              </p>
            </div>
          ))}
        </div>
      )}

      <Card padding={false} className="mb-4">
        <div className="p-5 pb-0">
          <CardHeader title={__('Questions × engines')} description={__('Click a result to read the full answer and its sources.')} />
        </div>
        {!summary || summary.keywords.length === 0 ? (
          <EmptyState icon={BarChart3} title={__('No results yet')} description={__('Add the questions customers ask below, then run a scan.')} />
        ) : (
          <table className="aeo-table">
            <thead>
              <tr>
                <th>{__('Question')}</th>
                {scanEngines.map((e) => (
                  <th key={e}>{labels[e] || e}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {summary.keywords.map((k) => (
                <tr key={k.keyword}>
                  <td className="text-slate-900 min-w-[220px]">{k.keyword}</td>
                  {scanEngines.map((e) => (
                    <td key={e}>
                      <Cell r={k.engines[e]} onOpen={setAnswerId} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <Card>
          <CardHeader title={__('Share of voice')} description={__('Who the engines cite for your questions')} />
          {!isPro() ? (
            <ProGate feature="insights" compact />
          ) : !summary || summary.share_of_voice.length === 0 ? (
            <EmptyState icon={Users} title={__('No sources yet')} description={__('Appears after a scan.')} />
          ) : (
            <ul className="space-y-2.5">
              {summary.share_of_voice.slice(0, 8).map((r) => (
                <li key={r.name}>
                  <div className="flex items-center justify-between text-xs mb-1">
                    <span className={cn('truncate', r.is_you ? 'font-semibold text-aeo-700' : 'text-slate-700')}>
                      {r.name} {r.is_you && `· ${__('you')}`}
                    </span>
                    <span className="tabular-nums text-slate-500">{r.percent}%</span>
                  </div>
                  <div className="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div className={cn('h-full rounded-full', r.is_you ? 'bg-aeo-600' : 'bg-slate-400')} style={{ width: `${r.percent}%` }} />
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card>
          <CardHeader title={__('Trend')} description={__('Cited and mentioned rate per scan')} />
          {!isPro() ? (
            <ProGate feature="insights" compact />
          ) : history.length < 2 ? (
            <EmptyState icon={BarChart3} title={__('Not enough scans')} description={__('The trend appears after two or more scans.')} />
          ) : (
            <ResponsiveContainer width="100%" height={220}>
              <LineChart data={history} margin={{ top: 4, right: 8, left: -24, bottom: 0 }}>
                <CartesianGrid vertical={false} stroke="#f4f4f5" />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} />
                <YAxis domain={[0, 100]} tick={{ fontSize: 11, fill: '#a1a1aa' }} axisLine={false} tickLine={false} unit="%" />
                <Tooltip contentStyle={{ borderRadius: 8, border: '1px solid #e4e4e7', fontSize: 12 }} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Line type="linear" dataKey="cited_rate" name={__('Cited')} stroke="#4f46e5" strokeWidth={2} dot={{ r: 3 }} />
                <Line type="linear" dataKey="mentioned_rate" name={__('Mentioned')} stroke="#a1a1aa" strokeWidth={2} dot={{ r: 3 }} />
              </LineChart>
            </ResponsiveContainer>
          )}
        </Card>
      </div>

      <Card className="mb-4">
        <CardHeader
          title={__('What to track')}
          description={sprintf(__('%1$d questions · up to %2$d are checked per scan on each engine'), keywordCount, data?.max_prompts || 5) + (isPro() ? '' : ` · ${__('Pro checks up to 25')}`)}
          action={
            <button type="button" className="aeo-btn-primary aeo-btn-sm" onClick={() => saveTracking.mutate()} disabled={saveTracking.isPending || caps?.manage === false}>
              <Save size={12} /> {__('Save')}
            </button>
          }
        />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2">
            <label className="aeo-label" htmlFor="aeo-track">{__('Questions and prompts')}</label>
            <textarea id="aeo-track" rows={7} className="aeo-textarea" value={keywords} onChange={(e) => setKeywords(e.target.value)} placeholder={__('One per line, phrased the way people ask an assistant')} />
            <div className="mt-2 flex items-center gap-2">
              <button type="button" className="aeo-btn-ghost aeo-btn-sm" onClick={() => suggestions.refetch()} disabled={suggestions.isFetching}>
                <Sparkles size={12} /> {suggestions.isFetching ? __('Finding…') : __('Suggest prompts')}
              </button>
              <span className="text-xs text-slate-400">{__('Built from your business details, FAQs and question headings.')}</span>
            </div>
            {suggestions.data && suggestions.data.length > 0 && (
              <div className="flex flex-wrap gap-1.5 mt-3">
                {suggestions.data.map((sug) => {
                  const added = keywords.split('\n').some((l) => l.trim().toLowerCase() === sug.prompt.toLowerCase());
                  return (
                    <button
                      key={sug.prompt}
                      type="button"
                      disabled={added}
                      onClick={() => setKeywords((k) => (k.trim() ? `${k.trim()}\n${sug.prompt}` : sug.prompt))}
                      className="aeo-badge cursor-pointer disabled:opacity-40 disabled:cursor-default m-0"
                    >
                      <Plus size={11} /> {sug.prompt}
                    </button>
                  );
                })}
              </div>
            )}
          </div>
          <div className="space-y-4">
            <div>
              <label className="aeo-label" htmlFor="aeo-brands">{__('Brand names')}</label>
              <textarea id="aeo-brands" rows={3} className="aeo-textarea" value={brands} onChange={(e) => setBrands(e.target.value)} placeholder={String(data?.brands?.[0] || '')} />
              <p className="aeo-hint">{__('Counted as a mention when an answer names you. Add local spellings too.')}</p>
            </div>
            <div>
              <label className="aeo-label" htmlFor="aeo-domain">{__('Your domain')}</label>
              <input id="aeo-domain" className="aeo-input" value={domain} placeholder={data?.own_host || ''} onChange={(e) => setDomain(e.target.value)} />
              <p className="aeo-hint">{__('Links to this domain count as citations. Leave empty to use this site\'s address.')}</p>
            </div>
            <div>
              <p className="aeo-label">{__('Engines')}</p>
              <div className="space-y-1.5">
                {(data?.all || []).map((e) => {
                  const hasKey = available.includes(e.engine) || (engines.length > 0 && engines.includes(e.engine));
                  const on = !e.locked && (engines.length === 0 ? available.includes(e.engine) : engines.includes(e.engine));
                  return (
                    <label key={e.engine} className={cn('flex items-center gap-2 text-sm', e.locked ? 'text-slate-400' : 'text-slate-700')}>
                      <input
                        type="checkbox"
                        checked={on}
                        disabled={e.locked}
                        onChange={() => {
                          const base = engines.length === 0 ? available : engines;
                          setEngines(on ? base.filter((x) => x !== e.engine) : [...base, e.engine]);
                        }}
                      />
                      {e.label}
                      {e.locked ? <ProBadge /> : !hasKey && <span className="text-2xs text-slate-400">· {__('no key')}</span>}
                    </label>
                  );
                })}
              </div>
            </div>
          </div>
        </div>
      </Card>

      <Card>
        <CardHeader
          title={__('Competitors')}
          description={__('Structured data on their homepage and whether they publish llms.txt')}
          action={
            compData.length > 0 && (
              <button
                onClick={() => api.getCompetitorAnalysis(true).then((d) => qc.setQueryData(['competitors'], d)).catch((e: Error) => toast.error(e.message))}
                className="aeo-btn-ghost aeo-btn-sm"
              >
                <RefreshCw size={12} /> {__('Re-check')}
              </button>
            )
          }
        />
        {competitors.isLoading ? (
          <LoadingState message={__('Checking competitor sites…')} />
        ) : compData.length === 0 ? (
          <EmptyState icon={Users} title={__('No competitors added')} description={__('Add up to 5 competitor domains in Settings.')} />
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
                      {__('Schema types:')} {comp.schema_types.length ? comp.schema_types.join(', ') : __('none found')}
                    </p>
                    <p className="text-xs mt-1">
                      <span className="text-slate-500">{__("They use, you don't:")} </span>
                      <span className={comp.missing_types.length ? 'text-slate-900' : 'text-slate-500'}>
                        {comp.missing_types.length ? comp.missing_types.join(', ') : __('nothing')}
                      </span>
                    </p>
                  </>
                )}
              </div>
            ))}
          </div>
        )}
      </Card>

      {answerId !== null && (
        <Modal title={answer.data ? `${labels[answer.data.engine] || answer.data.engine} · ${answer.data.keyword}` : __('Answer')} onClose={() => setAnswerId(null)} wide>
          {answer.isLoading || !answer.data ? (
            <LoadingState />
          ) : (
            <div className="space-y-4">
              <div className="flex flex-wrap gap-1.5">
                <span className={cn('aeo-badge', Number(answer.data.cited) ? 'text-emerald-700' : '')}>{Number(answer.data.cited) ? __('Cited') : __('Not cited')}</span>
                {Number(answer.data.position) > 0 && <span className="aeo-badge">{sprintf(__('Position %d'), Number(answer.data.position))}</span>}
                {Number(answer.data.mentioned) > 0 && <span className="aeo-badge">{__('Mentioned')}</span>}
                {answer.data.sentiment && <span className="aeo-badge">{answer.data.sentiment}</span>}
                <span className="aeo-badge">{answer.data.created_at}</span>
              </div>
              {answer.data.error && <p className="text-sm text-amber-700">{answer.data.error}</p>}
              <div className="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed bg-slate-50 rounded-lg p-4 max-h-[40vh] overflow-y-auto">
                {answer.data.answer || __('(empty answer)')}
              </div>
              {(answer.data.sources || []).length > 0 && (
                <div>
                  <p className="aeo-label">{__('Sources used')}</p>
                  <ol className="text-xs text-slate-600 space-y-1 list-decimal pl-5" style={{ listStyle: 'decimal' }}>
                    {(answer.data.sources || []).map((src, i) => (
                      <li key={i} className="break-all">{src}</li>
                    ))}
                  </ol>
                </div>
              )}
            </div>
          )}
        </Modal>
      )}
    </div>
  );
}
