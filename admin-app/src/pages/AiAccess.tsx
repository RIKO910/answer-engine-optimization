import { useMemo, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Save, ExternalLink, Send, Bot } from 'lucide-react';
import { post } from '@/lib/api';
import { __, sprintf } from '@/lib/i18n';
import { useSettingsDraft } from '@/lib/useSettings';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import Switch, { SwitchRow } from '@/components/ui/Switch';
import type { BotInfo } from '@/types';
import { cn } from '@/lib/utils';

const presets = [
  { id: 'allow_all', label: __('Allow all'), hint: __('Every AI crawler can read the site.') },
  { id: 'block_training', label: __('Block training only'), hint: __('Stay visible in AI search and answers, opt out of model training.') },
  { id: 'block_all', label: __('Block all'), hint: __('No AI crawler may read the site. You will not be cited.') },
  { id: 'custom', label: __('Custom'), hint: __('Choose per crawler below.') },
] as const;

const purposeLabel: Record<string, string> = {
  training: __('Training'),
  search: __('AI search'),
  user: __('User fetch'),
};

const signals = [
  { key: 'search', label: __('Search'), hint: __('Show the site in search results with links') },
  { key: 'ai-input', label: __('AI answers'), hint: __('Use content as input for AI answers (RAG, grounding)') },
  { key: 'ai-train', label: __('AI training'), hint: __('Use content to train or fine-tune models') },
] as const;

export default function AiAccess() {
  const { query, draft, set, flag, toggle, save, settings } = useSettingsDraft();
  const [filter, setFilter] = useState<'all' | 'training' | 'search' | 'user'>('all');

  const submit = useMutation({
    mutationFn: () => post<{ sent?: number; code?: number }>('/indexnow/submit'),
    onSuccess: (d) => {
      toast.success(sprintf(__('Sent %d URLs to IndexNow'), d.sent ?? 0));
      query.refetch();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const bots = (draft.bots || []) as BotInfo[];
  const preset = (draft.crawler_preset as string) || 'custom';
  const rules = (draft.crawler_settings || {}) as Record<string, string>;
  const contentSignals = (draft.content_signals || {}) as Record<string, string>;
  const postTypes = (draft.post_type_options || []) as Array<{ name: string; label: string }>;
  const llmsTypes = (draft.llms_post_types as string[] | undefined) ?? postTypes.map((p) => p.name);
  const urls = (draft.urls || {}) as Record<string, string>;
  const last = settings.indexnow_last as { time?: string; sent?: number; code?: number; ok?: boolean; error?: string } | null;

  const effective = (b: BotInfo) => {
    if (preset === 'allow_all') return 'allow';
    if (preset === 'block_all') return 'block';
    if (preset === 'block_training') return b.purpose === 'training' ? 'block' : 'allow';
    return rules[b.key] ?? b.rule ?? 'allow';
  };

  const shown = useMemo(() => bots.filter((b) => filter === 'all' || b.purpose === filter), [bots, filter]);
  const blocked = bots.filter((b) => effective(b) === 'block').length;

  if (query.isLoading) return <LoadingState message={__('Loading…')} />;

  const doSave = () =>
    save.mutate({
      crawler_preset: preset,
      crawler_settings: Object.fromEntries(bots.map((b) => [b.key, rules[b.key] ?? b.rule ?? 'allow'])),
      content_signals: contentSignals,
      llms_post_types: llmsTypes,
      llms_limit: Number(draft.llms_limit ?? 100),
      llms_intro: draft.llms_intro ?? '',
      llms_extra: draft.llms_extra ?? '',
      llms_full: flag('llms_full', true) ? 1 : 0,
      markdown_pages: flag('markdown_pages', true) ? 1 : 0,
      indexnow: flag('indexnow', true) ? 1 : 0,
      traffic_logging: flag('traffic_logging', true) ? 1 : 0,
    });

  return (
    <div>
      <PageHeader
        title={__('AI access')}
        description={__('Control which AI crawlers can read the site, and what they get: robots.txt rules, content signals, llms.txt and Markdown pages.')}
        actions={
          <button onClick={doSave} disabled={save.isPending} className="aeo-btn-primary">
            <Save size={14} /> {save.isPending ? __('Saving…') : __('Save changes')}
          </button>
        }
      />

      <Card className="mb-4">
        <CardHeader
          title={__('AI crawlers')}
          description={sprintf(__('%1$d of %2$d known crawlers blocked in robots.txt'), blocked, bots.length)}
          action={
            urls.robots && (
              <a href={urls.robots} target="_blank" rel="noreferrer" className="aeo-btn-ghost aeo-btn-sm">
                robots.txt <ExternalLink size={12} />
              </a>
            )
          }
        />
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-5">
          {presets.map((p) => (
            <button
              key={p.id}
              type="button"
              onClick={() => set({ crawler_preset: p.id })}
              className={cn(
                'text-left p-3 m-0 rounded-lg border cursor-pointer bg-white transition-colors',
                preset === p.id ? 'border-aeo-600 ring-1 ring-aeo-600' : 'border-slate-200 hover:border-slate-300'
              )}
            >
              <p className="text-sm font-medium text-slate-900">{p.label}</p>
              <p className="text-xs text-slate-500 mt-0.5">{p.hint}</p>
            </button>
          ))}
        </div>

        <div className="flex gap-1 mb-2">
          {(['all', 'training', 'search', 'user'] as const).map((f) => (
            <button
              key={f}
              type="button"
              onClick={() => setFilter(f)}
              className={cn('aeo-btn-sm', filter === f ? 'aeo-btn-secondary' : 'aeo-btn-ghost')}
            >
              {f === 'all' ? __('All') : purposeLabel[f]}
            </button>
          ))}
        </div>
        <div className="-mx-5 overflow-x-auto">
          <table className="aeo-table">
            <thead>
              <tr>
                <th>{__('Crawler')}</th>
                <th>{__('Company')}</th>
                <th>{__('Purpose')}</th>
                <th className="text-right">{__('Access')}</th>
              </tr>
            </thead>
            <tbody>
              {shown.map((b) => {
                const allowed = effective(b) !== 'block';
                return (
                  <tr key={b.key}>
                    <td className="font-medium text-slate-900">
                      {b.name}
                      {b.token_only && (
                        <span className="ml-2 aeo-badge" title={__('Robots.txt token only — it does not crawl, it controls use of content Google/Apple already fetched.')}>
                          {__('token')}
                        </span>
                      )}
                    </td>
                    <td>{b.owner}</td>
                    <td>
                      <span className="aeo-badge">
                        <span className={cn('aeo-dot', b.purpose === 'training' ? 'bg-amber-500' : b.purpose === 'search' ? 'bg-aeo-500' : 'bg-slate-400')} />
                        {purposeLabel[b.purpose]}
                      </span>
                    </td>
                    <td className="text-right">
                      <span className="inline-flex items-center gap-2">
                        <span className={cn('text-xs', allowed ? 'text-slate-500' : 'text-red-600')}>
                          {allowed ? __('Allowed') : __('Blocked')}
                        </span>
                        <Switch
                          checked={allowed}
                          label={sprintf(__('Allow %s'), b.name)}
                          onChange={() =>
                            set({
                              crawler_preset: 'custom',
                              crawler_settings: {
                                ...Object.fromEntries(bots.map((x) => [x.key, effective(x)])),
                                [b.key]: allowed ? 'block' : 'allow',
                              },
                            })
                          }
                        />
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <p className="aeo-hint mt-3">
          {__('Changing a single crawler switches the preset to Custom. Blocking search crawlers (OAI-SearchBot, PerplexityBot, Claude-SearchBot) removes you from those AI answers.')}
        </p>
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <Card>
          <CardHeader
            title={__('Content signals')}
            description={__('Added to robots.txt as a Content-Signal line (contentsignals.org). Leave unset to express no preference.')}
          />
          <div className="space-y-3">
            {signals.map((s) => (
              <div key={s.key} className="flex items-center justify-between gap-4">
                <div className="min-w-0">
                  <p className="text-sm text-slate-800">{s.label}</p>
                  <p className="text-xs text-slate-500">{s.hint}</p>
                </div>
                <div className="flex rounded-lg border border-slate-200 overflow-hidden flex-shrink-0">
                  {(['', 'yes', 'no'] as const).map((v) => (
                    <button
                      key={v || 'unset'}
                      type="button"
                      onClick={() => set({ content_signals: { ...contentSignals, [s.key]: v } })}
                      className={cn(
                        'h-8 px-3 m-0 border-0 text-xs cursor-pointer',
                        (contentSignals[s.key] || '') === v ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'
                      )}
                    >
                      {v === '' ? __('Unset') : v === 'yes' ? __('Yes') : __('No')}
                    </button>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <CardHeader title={__('Instant indexing')} description={__('IndexNow tells Bing, Yandex, Seznam and Naver (and through Bing, ChatGPT search and Copilot) when a page is published or updated.')} />
          <div className="-mx-2">
            <SwitchRow
              label={__('Ping IndexNow on publish and update')}
              hint={settings.indexnow_local ? __('This looks like a local site, so nothing is sent until it is live.') : undefined}
              checked={flag('indexnow', true)}
              onChange={() => toggle('indexnow', true)}
            />
            <SwitchRow
              label={__('Log AI crawler visits and AI referrals')}
              hint={__('Feeds the AI traffic report. No IP addresses are stored.')}
              checked={flag('traffic_logging', true)}
              onChange={() => toggle('traffic_logging', true)}
            />
          </div>
          <div className="mt-4 pt-4 border-t border-slate-200 flex items-center justify-between gap-3">
            <p className="text-xs text-slate-500">
              {last?.time
                ? sprintf(__('Last submission %1$s · %2$d URLs · HTTP %3$s'), last.time, last.sent ?? 0, last.error || String(last.code ?? '—'))
                : __('Nothing submitted yet')}
            </p>
            <button
              type="button"
              className="aeo-btn-secondary aeo-btn-sm"
              disabled={submit.isPending || !!settings.indexnow_local}
              onClick={() => submit.mutate()}
            >
              <Send size={12} /> {submit.isPending ? __('Sending…') : __('Submit recent URLs')}
            </button>
          </div>
        </Card>
      </div>

      <Card>
        <CardHeader
          title="llms.txt"
          description={__('A Markdown index of your best pages for AI agents, plus a full-text version and a .md version of every page.')}
          action={
            <div className="flex gap-1">
              {urls.llms && (
                <a href={urls.llms} target="_blank" rel="noreferrer" className="aeo-btn-ghost aeo-btn-sm">
                  llms.txt <ExternalLink size={12} />
                </a>
              )}
              {urls.llms_full && (
                <a href={urls.llms_full} target="_blank" rel="noreferrer" className="aeo-btn-ghost aeo-btn-sm">
                  llms-full.txt <ExternalLink size={12} />
                </a>
              )}
              {urls.schemamap && (
                <a href={urls.schemamap} target="_blank" rel="noreferrer" className="aeo-btn-ghost aeo-btn-sm">
                  {__('Schema map')} <ExternalLink size={12} />
                </a>
              )}
            </div>
          }
        />
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="space-y-4">
            <div>
              <p className="aeo-label">{__('Include')}</p>
              <div className="flex flex-wrap gap-2">
                {postTypes.map((pt) => {
                  const on = llmsTypes.includes(pt.name);
                  return (
                    <label key={pt.name} className={cn('aeo-badge cursor-pointer', on && 'bg-slate-900 text-white border-slate-900')}>
                      <input
                        type="checkbox"
                        className="sr-only"
                        checked={on}
                        onChange={() =>
                          set({ llms_post_types: on ? llmsTypes.filter((t) => t !== pt.name) : [...llmsTypes, pt.name] })
                        }
                      />
                      {pt.label}
                    </label>
                  );
                })}
              </div>
            </div>
            <div>
              <label className="aeo-label" htmlFor="aeo-llms-limit">{__('Pages per type')}</label>
              <input
                id="aeo-llms-limit"
                type="number"
                min={1}
                max={1000}
                className="aeo-input w-32"
                value={String(draft.llms_limit ?? 100)}
                onChange={(e) => set({ llms_limit: e.target.value })}
              />
              <p className="aeo-hint">{__('Highest-scoring pages first.')}</p>
            </div>
            <div className="-mx-2">
              <SwitchRow label={__('Publish llms-full.txt')} hint={__('Full text of the included pages, capped at 2 MB.')} checked={flag('llms_full', true)} onChange={() => toggle('llms_full', true)} />
              <SwitchRow label={__('Markdown version of every page')} hint={__('Add .md to any URL, or request it with Accept: text/markdown.')} checked={flag('markdown_pages', true)} onChange={() => toggle('markdown_pages', true)} />
            </div>
          </div>
          <div className="space-y-4">
            <div>
              <label className="aeo-label" htmlFor="aeo-llms-intro">{__('Summary')}</label>
              <textarea
                id="aeo-llms-intro"
                rows={3}
                className="aeo-textarea"
                value={String(draft.llms_intro ?? '')}
                placeholder={__('One or two sentences: who you are, where, and what you offer. Defaults to the site tagline.')}
                onChange={(e) => set({ llms_intro: e.target.value })}
              />
            </div>
            <div>
              <label className="aeo-label" htmlFor="aeo-llms-extra">{__('Extra section (Markdown)')}</label>
              <textarea
                id="aeo-llms-extra"
                rows={5}
                className="aeo-textarea font-mono text-xs"
                value={String(draft.llms_extra ?? '')}
                placeholder={'## Policies\n- [Cancellation policy](https://…): Free cancellation up to 48 hours before arrival'}
                onChange={(e) => set({ llms_extra: e.target.value })}
              />
            </div>
          </div>
        </div>
      </Card>

      <p className="text-xs text-slate-400 mt-6 flex items-center gap-1.5">
        <Bot size={12} /> {__('robots.txt rules are a request. Well-behaved crawlers follow them; they do not stop scrapers that ignore robots.txt.')}
      </p>
    </div>
  );
}
