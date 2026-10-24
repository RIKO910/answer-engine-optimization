import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ArrowRightLeft, Copy, Download, ExternalLink, Save, ShieldCheck, Upload } from 'lucide-react';
import { downloadJson, get, post } from '@/lib/api';
import { __, sprintf } from '@/lib/i18n';
import { useSettingsDraft } from '@/lib/useSettings';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import Tabs from '@/components/ui/Tabs';
import { SwitchRow } from '@/components/ui/Switch';
import { cn } from '@/lib/utils';

const sections = [
  { id: 'settings', label: __('Settings'), hint: __('API keys and Google credentials are never exported') },
  { id: 'global_schema', label: __('Organization / website schema') },
  { id: 'locations', label: __('Locations') },
  { id: 'templates', label: __('Schema templates') },
  { id: 'posts', label: __('Per-page FAQs, schema, target questions and direct answers') },
];

type ImportReport = Record<string, number | boolean | string[]>;

function ImportExport() {
  const [exp, setExp] = useState<string[]>(sections.map((s) => s.id));
  const [imp, setImp] = useState<string[]>(sections.map((s) => s.id));
  const [file, setFile] = useState<{ name: string; data: Record<string, unknown> } | null>(null);
  const [report, setReport] = useState<ImportReport | null>(null);
  const qc = useQueryClient();

  const doExport = useMutation({
    mutationFn: () => get(`/tools/export?sections=${exp.join(',')}`),
    onSuccess: (d) => {
      const host = (window.aeoGeniusData?.siteUrl || 'site').replace(/^https?:\/\//, '').replace(/[^a-z0-9.-]/gi, '-');
      downloadJson(d, `aeo-export-${host}-${new Date().toISOString().slice(0, 10)}.json`);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const doImport = useMutation({
    mutationFn: (dry: boolean) => post<ImportReport>('/tools/import', { data: file?.data, sections: imp, dry_run: dry }),
    onSuccess: (d) => {
      setReport(d);
      if (!d.dry_run) {
        qc.invalidateQueries();
        toast.success(__('Import complete'));
      }
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const onFile = (f?: File) => {
    if (!f) return;
    f.text()
      .then((t) => {
        setFile({ name: f.name, data: JSON.parse(t) });
        setReport(null);
      })
      .catch(() => toast.error(__('That file is not valid JSON.')));
  };

  const checklist = (value: string[], set: (v: string[]) => void, prefix: string) => (
    <div className="space-y-2">
      {sections.map((s) => (
        <label key={s.id} className="flex items-start gap-2 text-sm text-slate-700" htmlFor={`${prefix}-${s.id}`}>
          <input
            id={`${prefix}-${s.id}`}
            type="checkbox"
            className="mt-0.5"
            checked={value.includes(s.id)}
            onChange={() => set(value.includes(s.id) ? value.filter((x) => x !== s.id) : [...value, s.id])}
          />
          <span>
            {s.label}
            {s.hint && <span className="block text-xs text-slate-400">{s.hint}</span>}
          </span>
        </label>
      ))}
    </div>
  );

  const sourceSite = (file?.data?.site as string) || '';

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card>
        <CardHeader title={__('Export')} description={__('Move your setup to another site, or keep a backup before big changes.')} />
        {checklist(exp, setExp, 'exp')}
        <button type="button" className="aeo-btn-primary mt-5" disabled={!exp.length || doExport.isPending} onClick={() => doExport.mutate()}>
          <Download size={14} /> {__('Download JSON')}
        </button>
      </Card>
      <Card>
        <CardHeader title={__('Import')} description={__('Pages are matched by post type and slug, then by URL path, so a staging export imports into production.')} />
        <label className="flex items-center justify-center gap-2 h-20 rounded-lg border border-dashed border-slate-300 text-sm text-slate-500 cursor-pointer hover:bg-slate-50 mb-4">
          <Upload size={15} /> {file ? file.name : __('Choose an export file (.json)')}
          <input type="file" accept="application/json,.json" className="sr-only" onChange={(e) => onFile(e.target.files?.[0])} />
        </label>
        {file && (
          <>
            {sourceSite && <p className="text-xs text-slate-500 mb-3">{sprintf(__('Exported from %1$s on %2$s'), sourceSite, String(file.data.exported || '').slice(0, 10))}</p>}
            {checklist(imp, setImp, 'imp')}
            <div className="flex gap-2 mt-5">
              <button type="button" className="aeo-btn-secondary" disabled={doImport.isPending} onClick={() => doImport.mutate(true)}>
                {__('Check first')}
              </button>
              <button type="button" className="aeo-btn-primary" disabled={doImport.isPending || !imp.length} onClick={() => doImport.mutate(false)}>
                {__('Import')}
              </button>
            </div>
          </>
        )}
        {report && (
          <div className="aeo-card-muted mt-4 text-xs text-slate-600 space-y-1">
            <p className="font-medium text-slate-900">{report.dry_run ? __('Would import:') : __('Imported:')}</p>
            {Object.entries(report)
              .filter(([k]) => !['dry_run', 'unmatched'].includes(k))
              .map(([k, v]) => <p key={k}>{k}: {String(v)}</p>)}
            {Array.isArray(report.unmatched) && report.unmatched.length > 0 && (
              <p className="text-amber-700">{sprintf(__('No matching page for: %s'), report.unmatched.join(', '))}</p>
            )}
          </div>
        )}
      </Card>
    </div>
  );
}

function Migration() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ['faq-migration'], queryFn: () => get<{ posts: Array<{ id: number; title: string; edit_url: string; count: number; existing: number }> }>('/tools/faq-migration') });
  const run = useMutation({
    mutationFn: () => post<{ posts: number; faqs: number }>('/tools/faq-migration', {}),
    onSuccess: (d) => {
      qc.invalidateQueries({ queryKey: ['faq-migration'] });
      toast.success(sprintf(__('Copied %1$d FAQs on %2$d pages'), d.faqs, d.posts));
    },
    onError: (e: Error) => toast.error(e.message),
  });
  if (isLoading) return <LoadingState />;
  const rows = data?.posts || [];
  return (
    <Card padding={false}>
      <div className="p-5 pb-0">
        <CardHeader
          title={__('Yoast and Rank Math FAQ blocks')}
          description={__('Copy questions from their FAQ blocks into this plugin so they join your connected schema graph, llms.txt and the AEO score. The blocks stay on the page. Turn off FAQ schema in the SEO plugin afterwards to avoid duplicates.')}
          action={
            <button type="button" className="aeo-btn-primary aeo-btn-sm" disabled={!rows.length || run.isPending} onClick={() => run.mutate()}>
              <ArrowRightLeft size={12} /> {__('Copy all')}
            </button>
          }
        />
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={ArrowRightLeft} title={__('Nothing to migrate')} description={__('No Yoast or Rank Math FAQ blocks found.')} />
      ) : (
        <table className="aeo-table">
          <thead><tr><th>{__('Page')}</th><th className="text-right">{__('In block')}</th><th className="text-right">{__('Already here')}</th></tr></thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td><a href={r.edit_url} className="text-slate-900 hover:text-aeo-700">{r.title}</a></td>
                <td className="text-right tabular-nums">{r.count}</td>
                <td className="text-right tabular-nums">{r.existing}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Card>
  );
}

interface McpInfo {
  enabled: boolean;
  url: string;
  abilities_api: boolean;
  app_passwords: boolean;
  profile_url: string;
  config: Record<string, unknown>;
  abilities: Array<{ name: string; label: string; description: string; readonly: boolean }>;
}

function Mcp() {
  const { data, isLoading, refetch } = useQuery({ queryKey: ['mcp'], queryFn: () => get<McpInfo>('/tools/mcp') });
  const { flag, toggle, save } = useSettingsDraft();
  if (isLoading || !data) return <LoadingState />;
  const config = JSON.stringify(data.config, null, 2);
  const copy = (t: string) => navigator.clipboard?.writeText(t).then(() => toast.success(__('Copied')));

  return (
    <div className="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-4">
      <Card>
        <CardHeader
          title={__('Connect an AI assistant')}
          description={__('Let Claude, ChatGPT, Cursor or any MCP client read scores, schema and AI visibility, and update FAQs or direct answers — with the same permissions as the WordPress user it signs in as.')}
        />
        <div className="-mx-2 mb-4">
          <SwitchRow
            label={__('Enable the MCP endpoint')}
            hint={__('Off by default. Requests must authenticate with an Application Password.')}
            checked={flag('mcp_enabled', false)}
            onChange={() => {
              toggle('mcp_enabled', false);
              save.mutate({ mcp_enabled: flag('mcp_enabled', false) ? 0 : 1 }, { onSuccess: () => refetch() });
            }}
          />
        </div>
        <ol className="text-[13px] text-slate-600 space-y-3" style={{ listStyle: 'decimal', paddingLeft: 18 }}>
          <li>
            {__('Create an Application Password for a user with the right AEO role.')}{' '}
            <a href={data.profile_url} className="text-aeo-700 inline-flex items-center gap-0.5">{__('Your profile')} <ExternalLink size={11} /></a>
            {!data.app_passwords && <span className="block text-xs text-amber-700 mt-1">{__('Application Passwords are unavailable here — WordPress requires HTTPS for them (or WP_ENVIRONMENT_TYPE set to local).')}</span>}
          </li>
          <li>
            {__('Server URL:')}{' '}
            <code className="text-xs bg-slate-100 rounded px-1.5 py-0.5 break-all">{data.url}</code>{' '}
            <button type="button" className="aeo-btn-ghost aeo-btn-sm" onClick={() => copy(data.url)} aria-label={__('Copy URL')}><Copy size={11} /></button>
          </li>
          <li>
            {__('Clients that only speak stdio can use mcp-remote. Put base64 of username:application-password in AUTH:')}
            <pre className="text-xs bg-slate-50 rounded-lg p-3 overflow-auto mt-2">{config}</pre>
            <button type="button" className="aeo-btn-secondary aeo-btn-sm mt-2" onClick={() => copy(config)}><Copy size={11} /> {__('Copy config')}</button>
          </li>
        </ol>
        <p className="aeo-hint mt-4">
          {data.abilities_api
            ? __('The same tools are registered with the WordPress Abilities API, so the official WordPress MCP adapter can expose them too.')
            : __('WordPress 6.9+ also exposes these tools through the Abilities API.')}
        </p>
      </Card>
      <Card>
        <CardHeader title={sprintf(__('%d tools'), data.abilities.length)} />
        <ul className="space-y-2.5">
          {data.abilities.map((a) => (
            <li key={a.name}>
              <p className="text-xs font-medium text-slate-900 flex items-center gap-1.5">
                <span className={cn('aeo-dot', a.readonly ? 'bg-slate-300' : 'bg-amber-500')} title={a.readonly ? __('Read only') : __('Makes changes')} />
                {a.label}
              </p>
              <p className="text-2xs text-slate-500 font-mono">{a.name}</p>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}

function Roles() {
  const { draft, set, save, query } = useSettingsDraft();
  if (query.isLoading) return <LoadingState />;
  const roles = (draft.roles || []) as Array<{ slug: string; name: string }>;
  const labels = (draft.cap_labels || {}) as Record<string, string>;
  const map = (draft.role_caps || {}) as Record<string, string[]>;
  const capNames: Record<string, string> = {
    aeo_manage_settings: __('Manage settings'),
    aeo_view_reports: __('View reports'),
    aeo_edit_schema: __('Edit schema & FAQs'),
    aeo_use_ai: __('Use AI'),
  };

  return (
    <Card padding={false}>
      <div className="p-5 pb-0">
        <CardHeader
          title={__('Who can do what')}
          description={__('Administrators always have every permission. AI features spend API credits, so grant them deliberately.')}
          action={
            <button type="button" className="aeo-btn-primary aeo-btn-sm" disabled={save.isPending} onClick={() => save.mutate({ role_caps: map })}>
              <Save size={12} /> {__('Save')}
            </button>
          }
        />
      </div>
      <table className="aeo-table">
        <thead>
          <tr>
            <th>{__('Role')}</th>
            {Object.keys(labels).map((c) => <th key={c} className="text-center" title={labels[c]}>{capNames[c] || c}</th>)}
          </tr>
        </thead>
        <tbody>
          {roles.map((r) => (
            <tr key={r.slug}>
              <td className="text-slate-900">{r.name}</td>
              {Object.keys(labels).map((c) => {
                const on = r.slug === 'administrator' || (map[c] || []).includes(r.slug);
                return (
                  <td key={c} className="text-center">
                    <input
                      type="checkbox"
                      checked={on}
                      disabled={r.slug === 'administrator'}
                      aria-label={`${r.name}: ${capNames[c] || c}`}
                      onChange={() =>
                        set({ role_caps: { ...map, [c]: on ? (map[c] || []).filter((x) => x !== r.slug) : [...(map[c] || []), r.slug] } })
                      }
                    />
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
      <p className="aeo-hint px-5 py-3 flex items-center gap-1.5"><ShieldCheck size={12} /> {__('Editing schema or FAQs on a page also requires permission to edit that page.')}</p>
    </Card>
  );
}

export default function Tools() {
  const [tab, setTab] = useState<'io' | 'migrate' | 'mcp' | 'roles'>('io');
  return (
    <div>
      <PageHeader title={__('Tools')} description={__('Import and export, migrate from other plugins, connect AI assistants, and set permissions.')} />
      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'io', label: __('Import / export') },
          { id: 'migrate', label: __('Migrate FAQs') },
          { id: 'mcp', label: __('MCP & AI agents') },
          { id: 'roles', label: __('Roles') },
        ]}
      />
      {tab === 'io' && <ImportExport />}
      {tab === 'migrate' && <Migration />}
      {tab === 'mcp' && <Mcp />}
      {tab === 'roles' && <Roles />}
    </div>
  );
}
