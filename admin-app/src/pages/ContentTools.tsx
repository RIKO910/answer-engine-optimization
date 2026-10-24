import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { CalendarClock, Check, ExternalLink, HelpCircle, ImageOff, Pencil, Sparkles, Type, X } from 'lucide-react';
import { get, post } from '@/lib/api';
import { __, sprintf } from '@/lib/i18n';
import PageHeader from '@/components/ui/PageHeader';
import { Card } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import Tabs from '@/components/ui/Tabs';
import ProGate, { ProBadge } from '@/components/ProGate';
import { isPro } from '@/lib/pro';
import { cn } from '@/lib/utils';

// AI writing (titles, descriptions, alt text) is a Pro feature.
const canAi = () => window.aeoGeniusData?.caps?.ai !== false && isPro();

/* ---------------------------------------------------------------- Freshness */

interface StaleRow {
  id: number;
  title: string;
  url: string;
  edit_url: string;
  type: string;
  modified: string;
  months_old: number;
  reasons: string[];
  reviewed_at: string;
}

function Freshness() {
  const qc = useQueryClient();
  const [months, setMonths] = useState(12);
  const { data, isLoading } = useQuery({ queryKey: ['freshness', months], queryFn: () => get<StaleRow[]>(`/freshness?months=${months}`) });
  const review = useMutation({
    mutationFn: (id: number) => post('/freshness/review', { post_id: id }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['freshness'] });
      toast.success(__('Marked as reviewed — the modified date is now today'));
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <>
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <p className="text-[13px] text-slate-500 max-w-2xl">
          {__('AI engines prefer recent sources. These pages have not been updated for a while or mention a past year. Check the facts, update what changed, then mark them reviewed.')}
        </p>
        <select className="aeo-select w-auto" value={months} onChange={(e) => setMonths(Number(e.target.value))} aria-label={__('Older than')}>
          {[6, 12, 18, 24].map((m) => <option key={m} value={m}>{sprintf(__('Older than %d months'), m)}</option>)}
        </select>
      </div>
      <Card padding={false}>
        {isLoading ? (
          <LoadingState />
        ) : !data || data.length === 0 ? (
          <EmptyState icon={CalendarClock} title={__('Everything is fresh')} description={__('No published page is past the age limit or mentions an old year.')} />
        ) : (
          <table className="aeo-table">
            <thead><tr><th>{__('Page')}</th><th>{__('Last updated')}</th><th>{__('Why')}</th><th /></tr></thead>
            <tbody>
              {data.map((r) => (
                <tr key={r.id}>
                  <td>
                    <a href={r.url} target="_blank" rel="noreferrer" className="text-slate-900 hover:text-aeo-700">{r.title}</a>
                    <span className="text-slate-400 text-xs"> · {r.type}</span>
                  </td>
                  <td className="whitespace-nowrap tabular-nums text-xs">
                    {r.modified} <span className="text-slate-400">({sprintf(__('%d mo'), r.months_old)})</span>
                  </td>
                  <td className="text-xs text-slate-600">{r.reasons.join(' · ')}</td>
                  <td className="text-right whitespace-nowrap">
                    <a href={r.edit_url} className="aeo-btn-ghost aeo-btn-sm"><Pencil size={12} /> {__('Edit')}</a>
                    <button type="button" className="aeo-btn-secondary aeo-btn-sm ml-1" disabled={review.isPending} onClick={() => review.mutate(r.id)}>
                      <Check size={12} /> {__('Reviewed')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </>
  );
}

/* ---------------------------------------------------------------- FAQ queue */

interface PendingRow {
  id: number;
  title: string;
  url: string;
  suggestions: Array<{ question: string; answer: string }>;
  existing: number;
}

function FaqQueue() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['faq-queue'],
    queryFn: () => get<{ pending: PendingRow[]; progress: { items: number[]; done: number; finished: string } | null }>('/faq-queue'),
  });
  const [progress, setProgress] = useState<{ done: number; total: number } | null>(null);
  const [picked, setPicked] = useState<Record<string, boolean>>({});
  const running = useRef(false);

  const run = async () => {
    if (running.current) return;
    running.current = true;
    try {
      let p = await post<{ total: number; done: number; finished: string }>('/faq-queue/start', { without_faqs: true });
      setProgress(p);
      while (!p.finished && p.done < p.total) {
        p = await post('/faq-queue/continue');
        setProgress(p);
      }
      await qc.invalidateQueries({ queryKey: ['faq-queue'] });
      toast.success(p.total ? sprintf(__('Drafted FAQs for %d pages'), p.total) : __('Every page already has FAQs'));
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      running.current = false;
      setProgress(null);
    }
  };

  const approve = useMutation({
    mutationFn: (row: PendingRow) =>
      post<{ total: number }>('/faq-queue/approve', {
        post_id: row.id,
        items: row.suggestions.filter((_, i) => picked[`${row.id}-${i}`] !== false),
      }),
    onSuccess: (d) => {
      qc.invalidateQueries({ queryKey: ['faq-queue'] });
      toast.success(sprintf(__('Saved. The page now has %d FAQs.'), d.total));
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const reject = useMutation({
    mutationFn: (id: number) => post('/faq-queue/reject', { post_id: id }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['faq-queue'] }),
  });

  if (isLoading) return <LoadingState />;
  const pending = data?.pending || [];

  return (
    <>
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <p className="text-[13px] text-slate-500 max-w-2xl">
          {__('Draft FAQs with AI for every published page that has none, then approve the ones that are accurate. Nothing is published until you approve it.')}
        </p>
        <button type="button" className="aeo-btn-primary" onClick={run} disabled={!!progress || !canAi()}>
          <Sparkles size={14} /> {progress ? sprintf(__('Drafting %1$d / %2$d'), progress.done, progress.total) : __('Draft FAQs for pages without')}
        </button>
      </div>
      {pending.length === 0 ? (
        <Card><EmptyState icon={HelpCircle} title={__('Nothing to review')} description={__('Drafted FAQs wait here for your approval.')} /></Card>
      ) : (
        <div className="space-y-4">
          {pending.map((row) => (
            <Card key={row.id}>
              <div className="flex items-start justify-between gap-4 mb-3">
                <div className="min-w-0">
                  <a href={row.url} target="_blank" rel="noreferrer" className="text-sm font-semibold text-slate-900 hover:text-aeo-700">{row.title}</a>
                  <p className="text-xs text-slate-500">{sprintf(__('%1$d drafted · %2$d already on the page'), row.suggestions.length, row.existing)}</p>
                </div>
                <div className="flex gap-1 flex-shrink-0">
                  <button type="button" className="aeo-btn-ghost aeo-btn-sm" onClick={() => reject.mutate(row.id)}><X size={12} /> {__('Discard')}</button>
                  <button type="button" className="aeo-btn-primary aeo-btn-sm" onClick={() => approve.mutate(row)} disabled={approve.isPending}><Check size={12} /> {__('Approve selected')}</button>
                </div>
              </div>
              <ul className="space-y-2">
                {row.suggestions.map((f, i) => {
                  const k = `${row.id}-${i}`;
                  const on = picked[k] !== false;
                  return (
                    <li key={k} className={cn('flex gap-3 p-3 rounded-lg border', on ? 'border-slate-200 bg-white' : 'border-dashed border-slate-200 bg-slate-50 opacity-60')}>
                      <input type="checkbox" className="mt-1" checked={on} onChange={() => setPicked((p) => ({ ...p, [k]: !on }))} aria-label={__('Include')} />
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-slate-900">{f.question}</p>
                        <p className="text-[13px] text-slate-600 mt-0.5">{f.answer}</p>
                      </div>
                    </li>
                  );
                })}
              </ul>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}

/* ---------------------------------------------------------------- Meta */

interface MetaRow {
  id: number;
  post_title: string;
  url: string;
  title: string;
  description: string;
}

function MetaEditor() {
  const qc = useQueryClient();
  const [filter, setFilter] = useState<'missing' | 'all'>('missing');
  const { data, isLoading } = useQuery({ queryKey: ['meta', filter], queryFn: () => get<{ owner: string; rows: MetaRow[] }>(`/meta?filter=${filter}`) });
  const [edits, setEdits] = useState<Record<number, { title: string; description: string }>>({});
  const [busy, setBusy] = useState<number | null>(null);

  const suggest = async (id: number) => {
    setBusy(id);
    try {
      const s = await post<{ title: string; description: string }>('/meta/suggest', { post_id: id });
      setEdits((e) => ({ ...e, [id]: { title: s.title, description: s.description } }));
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  };
  const save = useMutation({
    mutationFn: (id: number) => post('/meta/save', { post_id: id, ...edits[id] }),
    onSuccess: (_d, id) => {
      setEdits((e) => {
        const n = { ...e };
        delete n[id];
        return n;
      });
      qc.invalidateQueries({ queryKey: ['meta'] });
      toast.success(__('Saved'));
    },
    onError: (e: Error) => toast.error(e.message),
  });

  if (isLoading || !data) return <LoadingState />;

  return (
    <>
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <p className="text-[13px] text-slate-500 max-w-2xl">
          {data.owner
            ? sprintf(__('Saved into %s fields, so your SEO plugin outputs them.'), data.owner)
            : __('No SEO plugin detected — this plugin outputs the title and description itself.')}
        </p>
        <div className="flex gap-1">
          {(['missing', 'all'] as const).map((f) => (
            <button key={f} type="button" onClick={() => setFilter(f)} className={cn('aeo-btn-sm', filter === f ? 'aeo-btn-secondary' : 'aeo-btn-ghost')}>
              {f === 'missing' ? __('Missing') : __('All')}
            </button>
          ))}
        </div>
      </div>
      <Card padding={false}>
        {data.rows.length === 0 ? (
          <EmptyState icon={Type} title={__('All pages have a title and description')} description={__('Switch to All to edit existing ones.')} />
        ) : (
          <table className="aeo-table">
            <thead><tr><th className="w-1/4">{__('Page')}</th><th>{__('SEO title')}</th><th>{__('Meta description')}</th><th /></tr></thead>
            <tbody>
              {data.rows.map((r) => {
                const e = edits[r.id] ?? { title: r.title, description: r.description };
                const changed = !!edits[r.id];
                return (
                  <tr key={r.id}>
                    <td className="align-top">
                      <a href={r.url} target="_blank" rel="noreferrer" className="text-slate-900 hover:text-aeo-700 text-sm">{r.post_title}</a>
                    </td>
                    <td className="align-top">
                      <input className="aeo-input text-xs" value={e.title} placeholder={r.post_title} onChange={(ev) => setEdits((x) => ({ ...x, [r.id]: { ...e, title: ev.target.value } }))} />
                      <p className={cn('text-2xs mt-1 tabular-nums', e.title.length > 60 ? 'text-amber-600' : 'text-slate-400')}>{e.title.length}/60</p>
                    </td>
                    <td className="align-top">
                      <textarea rows={2} className="aeo-textarea text-xs min-h-0 h-[58px]" value={e.description} onChange={(ev) => setEdits((x) => ({ ...x, [r.id]: { ...e, description: ev.target.value } }))} />
                      <p className={cn('text-2xs mt-1 tabular-nums', e.description.length > 155 ? 'text-amber-600' : 'text-slate-400')}>{e.description.length}/155</p>
                    </td>
                    <td className="align-top text-right whitespace-nowrap">
                      <button type="button" className="aeo-btn-ghost aeo-btn-sm" disabled={busy === r.id || !canAi()} onClick={() => suggest(r.id)} title={isPro() ? __('Write with AI') : __('Write with AI (Pro)')}>
                        <Sparkles size={12} className={busy === r.id ? 'animate-pulse' : ''} />
                      </button>
                      <button type="button" className="aeo-btn-secondary aeo-btn-sm ml-1" disabled={!changed || save.isPending} onClick={() => save.mutate(r.id)}>
                        {__('Save')}
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </Card>
    </>
  );
}

/* ---------------------------------------------------------------- Alt text */

interface AltRow {
  id: number;
  thumb: string;
  file: string;
  parent: string;
}

function AltText() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ['alt'], queryFn: () => get<AltRow[]>('/alt') });
  const [alts, setAlts] = useState<Record<number, string>>({});
  const [busy, setBusy] = useState<number | null>(null);

  const suggest = async (id: number) => {
    setBusy(id);
    try {
      const s = await post<{ alt: string } | string>('/alt/suggest', { id });
      setAlts((a) => ({ ...a, [id]: typeof s === 'string' ? s : s.alt }));
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  };
  const save = useMutation({
    mutationFn: (id: number) => post('/alt/save', { id, alt: alts[id] }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['alt'] });
      toast.success(__('Alt text saved'));
    },
    onError: (e: Error) => toast.error(e.message),
  });

  if (isLoading) return <LoadingState />;

  return (
    <>
      <p className="text-[13px] text-slate-500 max-w-2xl mb-4">
        {__('Images without alt text are invisible to AI engines and screen readers. AI writes a factual description from the image itself (needs a provider that reads images).')}
        {!isPro() && (
          <span className="ml-1 inline-flex items-center gap-1">
            {__('Writing with AI is in')} <ProBadge />
          </span>
        )}
      </p>
      <Card padding={false}>
        {!data || data.length === 0 ? (
          <EmptyState icon={ImageOff} title={__('Every image has alt text')} description={__('Nothing to fix in the media library.')} />
        ) : (
          <table className="aeo-table">
            <thead><tr><th>{__('Image')}</th><th>{__('Alt text')}</th><th /></tr></thead>
            <tbody>
              {data.map((r) => (
                <tr key={r.id}>
                  <td className="w-[260px]">
                    <div className="flex items-center gap-3">
                      {r.thumb ? <img src={r.thumb} alt="" className="w-12 h-12 rounded object-cover bg-slate-100 flex-shrink-0" /> : <div className="w-12 h-12 rounded bg-slate-100" />}
                      <div className="min-w-0">
                        <p className="text-xs text-slate-900 truncate">{r.file}</p>
                        {r.parent && <p className="text-2xs text-slate-500 truncate">{r.parent}</p>}
                      </div>
                    </div>
                  </td>
                  <td>
                    <input className="aeo-input text-xs" value={alts[r.id] ?? ''} placeholder={__('Describe what the image shows')} onChange={(e) => setAlts((a) => ({ ...a, [r.id]: e.target.value }))} />
                  </td>
                  <td className="text-right whitespace-nowrap">
                    <button type="button" className="aeo-btn-ghost aeo-btn-sm" disabled={busy === r.id || !canAi()} onClick={() => suggest(r.id)} title={isPro() ? __('Write with AI') : __('Write with AI (Pro)')}>
                      <Sparkles size={12} className={busy === r.id ? 'animate-pulse' : ''} />
                    </button>
                    <button type="button" className="aeo-btn-secondary aeo-btn-sm ml-1" disabled={!alts[r.id]?.trim() || save.isPending} onClick={() => save.mutate(r.id)}>
                      {__('Save')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </>
  );
}

export default function ContentTools() {
  const [tab, setTab] = useState<'fresh' | 'faq' | 'meta' | 'alt'>('fresh');
  return (
    <div>
      <PageHeader
        title={__('Content tools')}
        description={__('Bulk fixes that help answer engines trust and quote your pages.')}
        actions={
          <a className="aeo-btn-ghost" href={`${window.aeoGeniusData?.adminUrl || ''}edit.php`}>
            {__('All posts')} <ExternalLink size={13} />
          </a>
        }
      />
      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'fresh', label: __('Freshness') },
          { id: 'faq', label: isPro() ? __('FAQ review') : `${__('FAQ review')} · Pro` },
          { id: 'meta', label: __('Titles & descriptions') },
          { id: 'alt', label: __('Image alt text') },
        ]}
      />
      {tab === 'fresh' && <Freshness />}
      {tab === 'faq' && (
        <ProGate feature="bulk_ai" title={__('Bulk FAQ drafts with review')} description={__('Draft FAQs with AI for every page that has none, then approve the accurate ones. Single-page FAQ suggestions in the editor stay free.')}>
          <FaqQueue />
        </ProGate>
      )}
      {tab === 'meta' && <MetaEditor />}
      {tab === 'alt' && <AltText />}
    </div>
  );
}
