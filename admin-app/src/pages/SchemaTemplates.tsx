import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Copy, Eye, LayoutTemplate, Plus, Save, Trash2 } from 'lucide-react';
import { api, get, post } from '@/lib/api';
import { __, sprintf } from '@/lib/i18n';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import Switch from '@/components/ui/Switch';
import ProGate from '@/components/ProGate';
import { isPro } from '@/lib/pro';
import { cn } from '@/lib/utils';

interface Template {
  id: string;
  name: string;
  enabled: boolean;
  schema: string;
  rules: { post_types: string[]; terms: string[]; post_ids: number[] };
}

interface TemplatesResponse {
  templates: Template[];
  post_types: string[];
  taxonomies: Array<{ name: string; label: string }>;
  acf: boolean;
  metabox: boolean;
}

const starters: Record<string, { name: string; schema: object }> = {
  event: {
    name: __('Event'),
    schema: {
      '@type': 'Event',
      name: '{{title}}',
      description: '{{excerpt}}',
      startDate: '{{meta:event_start}}',
      endDate: '{{meta:event_end}}',
      eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
      location: { '@type': 'Place', name: '{{meta:venue}}', address: '{{meta:venue_address}}' },
      image: '{{image}}',
      url: '{{url}}',
    },
  },
  course: {
    name: __('Course'),
    schema: {
      '@type': 'Course',
      name: '{{title}}',
      description: '{{excerpt}}',
      provider: { '@type': 'Organization', name: '{{site_name}}', sameAs: '{{site_url}}' },
    },
  },
  recipe: {
    name: __('Recipe'),
    schema: {
      '@type': 'Recipe',
      name: '{{title}}',
      image: '{{image}}',
      description: '{{excerpt}}',
      recipeCuisine: '{{term:cuisine}}',
      totalTime: '{{meta:total_time}}',
      recipeYield: '{{meta:servings}}',
      author: { '@type': 'Person', name: '{{author}}' },
    },
  },
  service: {
    name: __('Service'),
    schema: {
      '@type': 'Service',
      name: '{{title}}',
      description: '{{excerpt}}',
      provider: { '@type': 'LocalBusiness', name: '{{site_name}}' },
      areaServed: '{{meta:area_served}}',
      offers: { '@type': 'Offer', price: '{{meta:price}}', priceCurrency: 'BDT' },
    },
  },
  tour: {
    name: __('Tour / trip'),
    schema: {
      '@type': 'TouristTrip',
      name: '{{title}}',
      description: '{{excerpt}}',
      touristType: '{{terms:trip_type}}',
      offers: { '@type': 'Offer', price: '{{meta:price}}', priceCurrency: 'BDT' },
    },
  },
};

const placeholders = [
  '{{title}}', '{{excerpt}}', '{{content}}', '{{url}}', '{{image}}', '{{date_published}}', '{{date_modified}}',
  '{{author}}', '{{site_name}}', '{{site_url}}', '{{id}}', '{{meta:key}}', '{{acf:field}}', '{{mb:field}}', '{{term:taxonomy}}', '{{terms:taxonomy}}',
];

const blank = (): Template => ({
  id: '',
  name: __('New template'),
  enabled: true,
  schema: JSON.stringify({ '@type': 'Thing', name: '{{title}}', url: '{{url}}' }, null, 2),
  rules: { post_types: [], terms: [], post_ids: [] },
});

function SchemaTemplatesInner() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ['schema-templates'], queryFn: () => get<TemplatesResponse>('/schema-templates') });
  const posts = useQuery({ queryKey: ['content-posts'], queryFn: () => api.getContentPosts() });
  const [list, setList] = useState<Template[]>([]);
  const [active, setActive] = useState(0);
  const [previewId, setPreviewId] = useState<number>(0);
  const [preview, setPreview] = useState<{ node: object; validation: { errors?: string[]; warnings?: string[] } } | null>(null);

  useEffect(() => {
    if (data) setList(data.templates);
  }, [data]);

  const save = useMutation({
    mutationFn: () => post<Template[]>('/schema-templates', { templates: list }),
    onSuccess: (d) => {
      setList(d);
      qc.setQueryData(['schema-templates'], { ...(data as TemplatesResponse), templates: d });
      toast.success(__('Templates saved'));
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const runPreview = useMutation({
    mutationFn: () => post<{ node: object; validation: { errors?: string[]; warnings?: string[] } }>('/schema-templates/preview', { schema: list[active]?.schema, post_id: previewId }),
    onSuccess: setPreview,
    onError: (e: Error) => toast.error(e.message),
  });

  if (isLoading || !data) return <LoadingState />;

  const t = list[active];
  const update = (patch: Partial<Template>) => setList((l) => l.map((x, i) => (i === active ? { ...x, ...patch } : x)));
  const updateRules = (patch: Partial<Template['rules']>) => t && update({ rules: { ...t.rules, ...patch } });
  let jsonError = '';
  if (t) {
    try {
      const parsed = JSON.parse(t.schema);
      if (!parsed['@type']) jsonError = __('Add an @type.');
    } catch (e) {
      jsonError = (e as Error).message;
    }
  }
  const postOptions = (posts.data || []) as Array<{ id: number; title: string; type: string }>;

  return (
    <div>
      <PageHeader
        title={__('Schema templates')}
        description={__('Write schema once and apply it to every page that matches: a post type, a category, or specific pages. Fill fields from custom fields, ACF or Meta Box.')}
        actions={
          <button type="button" className="aeo-btn-primary" onClick={() => save.mutate()} disabled={save.isPending || !!jsonError}>
            <Save size={14} /> {save.isPending ? __('Saving…') : __('Save templates')}
          </button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-4">
        <div className="space-y-2">
          {list.map((x, i) => (
            <button
              key={`${x.id}-${i}`}
              type="button"
              onClick={() => {
                setActive(i);
                setPreview(null);
              }}
              className={cn(
                'w-full text-left p-3 m-0 rounded-lg border cursor-pointer bg-white',
                i === active ? 'border-aeo-600 ring-1 ring-aeo-600' : 'border-slate-200 hover:border-slate-300'
              )}
            >
              <p className="text-sm font-medium text-slate-900 truncate">{x.name}</p>
              <p className="text-2xs text-slate-500 truncate">
                {x.enabled ? '' : `${__('Off')} · `}
                {[...x.rules.post_types, ...x.rules.terms].join(', ') || (x.rules.post_ids.length ? sprintf(__('%d pages'), x.rules.post_ids.length) : __('No rules'))}
              </p>
            </button>
          ))}
          <button type="button" className="aeo-btn-secondary w-full" onClick={() => { setList((l) => [...l, blank()]); setActive(list.length); }}>
            <Plus size={13} /> {__('Blank template')}
          </button>
          <select
            className="aeo-select"
            value=""
            onChange={(e) => {
              const s = starters[e.target.value];
              if (!s) return;
              setList((l) => [...l, { ...blank(), name: s.name, schema: JSON.stringify(s.schema, null, 2) }]);
              setActive(list.length);
            }}
            aria-label={__('Start from a preset')}
          >
            <option value="">{__('Start from a preset…')}</option>
            {Object.entries(starters).map(([k, s]) => <option key={k} value={k}>{s.name}</option>)}
          </select>
        </div>

        {!t ? (
          <Card>
            <EmptyState icon={LayoutTemplate} title={__('No templates yet')} description={__('Start from a preset or a blank template.')} />
          </Card>
        ) : (
          <div className="space-y-4">
            <Card>
              <div className="flex items-center gap-3 mb-4">
                <input className="aeo-input flex-1" value={t.name} onChange={(e) => update({ name: e.target.value })} aria-label={__('Template name')} />
                <span className="flex items-center gap-2 text-xs text-slate-500">
                  {t.enabled ? __('On') : __('Off')}
                  <Switch checked={t.enabled} label={__('Enabled')} onChange={() => update({ enabled: !t.enabled })} />
                </span>
                <button type="button" className="aeo-btn-ghost aeo-btn-sm" title={__('Duplicate')} onClick={() => { setList((l) => [...l, { ...t, id: '', name: `${t.name} (copy)` }]); setActive(list.length); }}>
                  <Copy size={13} />
                </button>
                <button type="button" className="aeo-btn-ghost aeo-btn-sm text-red-600" title={__('Delete')} onClick={() => { setList((l) => l.filter((_, i) => i !== active)); setActive(0); }}>
                  <Trash2 size={13} />
                </button>
              </div>

              <CardHeader title={__('Apply to')} description={__('All filled rules must match. Within a rule, any value matches.')} className="mb-3" />
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <p className="aeo-label">{__('Post types')}</p>
                  <div className="flex flex-wrap gap-1.5">
                    {data.post_types.map((pt) => {
                      const on = t.rules.post_types.includes(pt);
                      return (
                        <button key={pt} type="button" onClick={() => updateRules({ post_types: on ? t.rules.post_types.filter((x) => x !== pt) : [...t.rules.post_types, pt] })} className={cn('aeo-badge cursor-pointer m-0', on && 'bg-slate-900 text-white border-slate-900')}>
                          {pt}
                        </button>
                      );
                    })}
                  </div>
                </div>
                <div>
                  <label className="aeo-label" htmlFor="tpl-terms">{__('Terms')}</label>
                  <input
                    id="tpl-terms"
                    className="aeo-input text-xs font-mono"
                    value={t.rules.terms.join(', ')}
                    placeholder="category:news, qhb_room_type:suite"
                    onChange={(e) => updateRules({ terms: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) })}
                  />
                  <p className="aeo-hint">{sprintf(__('taxonomy:slug — available: %s'), data.taxonomies.map((x) => x.name).join(', '))}</p>
                </div>
                <div>
                  <label className="aeo-label" htmlFor="tpl-ids">{__('Specific page IDs')}</label>
                  <input
                    id="tpl-ids"
                    className="aeo-input text-xs font-mono"
                    value={t.rules.post_ids.join(', ')}
                    placeholder="12, 48"
                    onChange={(e) => updateRules({ post_ids: e.target.value.split(',').map((s) => parseInt(s, 10)).filter((n) => n > 0) })}
                  />
                </div>
              </div>
            </Card>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
              <Card>
                <CardHeader title="JSON-LD" description={jsonError ? '' : __('Properties that come out empty are dropped.')} />
                <textarea
                  rows={18}
                  spellCheck={false}
                  className={cn('aeo-textarea font-mono text-xs leading-relaxed', jsonError && 'border-red-300')}
                  value={t.schema}
                  onChange={(e) => update({ schema: e.target.value })}
                />
                {jsonError && <p className="text-xs text-red-600 mt-1">{jsonError}</p>}
                <div className="flex flex-wrap gap-1 mt-3">
                  {placeholders.map((p) => (
                    <code key={p} className="text-2xs bg-slate-100 text-slate-600 rounded px-1.5 py-0.5">{p}</code>
                  ))}
                </div>
                {(data.acf || data.metabox) && (
                  <p className="aeo-hint mt-2">{[data.acf && 'ACF', data.metabox && 'Meta Box'].filter(Boolean).join(' + ')} {__('detected.')}</p>
                )}
              </Card>
              <Card>
                <CardHeader title={__('Preview')} description={__('Rendered with a real page\'s values.')} />
                <div className="flex gap-2 mb-3">
                  <select className="aeo-select text-xs flex-1 min-w-0" value={previewId} onChange={(e) => setPreviewId(Number(e.target.value))} aria-label={__('Preview on')}>
                    <option value={0}>{__('Choose a page…')}</option>
                    {postOptions.map((p) => <option key={p.id} value={p.id}>{p.title} ({p.type})</option>)}
                  </select>
                  <button type="button" className="aeo-btn-secondary" disabled={!previewId || !!jsonError || runPreview.isPending} onClick={() => runPreview.mutate()}>
                    <Eye size={13} /> {__('Render')}
                  </button>
                </div>
                {preview ? (
                  <>
                    {(preview.validation.errors || []).map((e) => <p key={e} className="text-xs text-red-600">{e}</p>)}
                    {(preview.validation.warnings || []).map((e) => <p key={e} className="text-xs text-amber-600">{e}</p>)}
                    <pre className="text-xs bg-slate-50 rounded-lg p-3 overflow-auto max-h-[420px] mt-2">{JSON.stringify(preview.node, null, 2)}</pre>
                  </>
                ) : (
                  <p className="text-[13px] text-slate-500">{__('Pick a page to see the rendered schema with its real values.')}</p>
                )}
              </Card>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default function SchemaTemplates() {
  if (isPro()) return <SchemaTemplatesInner />;
  return (
    <div>
      <PageHeader
        title={__('Schema templates')}
        description={__('Write schema once and apply it to every page that matches: a post type, a category, or specific pages. Fill fields from custom fields, ACF or Meta Box.')}
      />
      <ProGate feature="templates" />
    </div>
  );
}
