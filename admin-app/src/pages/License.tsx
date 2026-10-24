import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ArrowUpRight, Check, KeyRound, Lock, RefreshCw, ShieldCheck, Trash2, X } from 'lucide-react';
import { get, post } from '@/lib/api';
import { __, sprintf } from '@/lib/i18n';
import { pro } from '@/lib/pro';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import { ProBadge } from '@/components/ProGate';
import { cn } from '@/lib/utils';

interface LicenseInfo {
  has_key: boolean;
  key: string;
  status: string;
  active: boolean;
  expiry: string;
  lifetime: boolean;
  activations: { active: number; total: number } | null;
  last_check: number;
  last_error: string;
  domain: string;
  product: string;
}

interface LicenseResponse {
  success: boolean;
  message?: string;
  license: LicenseInfo;
  pro: { plugin_active: boolean; licensed: boolean; sales_url: string; version: string };
}

const fmtDate = (s: string) => {
  const d = new Date(s.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? s : d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
};

export default function License() {
  const { data, isLoading, refetch } = useQuery({ queryKey: ['license'], queryFn: () => get<LicenseResponse>('/license') });
  const [key, setKey] = useState('');

  // Pro features boot on the next request, so reload after a change.
  const after = (d: LicenseResponse) => {
    if (d.success) {
      toast.success(d.message || __('Done'));
      setTimeout(() => window.location.reload(), 900);
    } else {
      toast.error(d.message || __('That did not work.'));
      refetch();
    }
  };
  const activate = useMutation({ mutationFn: () => post<LicenseResponse>('/license/activate', { license_key: key.trim() }), onSuccess: after, onError: (e: Error) => toast.error(e.message) });
  const check = useMutation({
    mutationFn: () => post<LicenseResponse>('/license/check'),
    onSuccess: (d) => {
      const wasPro = pro().licensed;
      if (d.pro.licensed !== wasPro) return after({ ...d, success: true });
      d.success ? toast.success(d.message || __('License is active.')) : toast.error(d.message || __('License is not active.'));
      refetch();
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const remove = useMutation({ mutationFn: () => post<LicenseResponse>('/license/deactivate'), onSuccess: after, onError: (e: Error) => toast.error(e.message) });

  if (isLoading || !data) return <LoadingState />;
  const l = data.license;
  const p = data.pro;
  const features = pro().features;
  const busy = activate.isPending || check.isPending || remove.isPending;

  const status = l.active
    ? { label: __('Active'), cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
    : l.has_key
      ? { label: l.status === 'inactive' && l.expiry && new Date(l.expiry) < new Date() ? __('Expired') : __('Inactive'), cls: 'bg-red-50 text-red-700 border-red-200' }
      : { label: __('No license'), cls: 'bg-slate-50 text-slate-600 border-slate-200' };

  return (
    <div>
      <PageHeader
        title={__('License')}
        description={__('Activate Answer Engine Optimization Pro on this site. The free plugin keeps working without a license.')}
      />

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-4 items-start">
        <div className="space-y-4">
          {!p.plugin_active && (
            <div className="rounded-xl border border-aeo-200 bg-aeo-50/60 p-5 flex flex-col md:flex-row md:items-center gap-4 justify-between">
              <div>
                <p className="text-sm font-semibold text-slate-900">{__('The Pro add-on is not installed')}</p>
                <p className="text-[13px] text-slate-600 mt-0.5 max-w-xl">
                  {__('Pro is a separate plugin, "Answer Engine Optimization Pro". Buy it, upload the ZIP under Plugins → Add New, activate it, then enter your license key here. You can also activate the key first.')}
                </p>
              </div>
              <a href={p.sales_url} target="_blank" rel="noreferrer" className="aeo-btn-primary flex-shrink-0">
                {__('Get Pro')} <ArrowUpRight size={14} />
              </a>
            </div>
          )}

          <Card>
            <CardHeader
              title={__('License key')}
              description={sprintf(__('Product: %1$s · Site: %2$s'), l.product, l.domain)}
              action={<span className={cn('inline-flex items-center h-6 px-2 rounded-md border text-xs font-medium', status.cls)}>{status.label}</span>}
            />

            {l.has_key ? (
              <div className="space-y-4">
                <div className="flex items-center gap-3 p-3 rounded-lg bg-slate-50 border border-slate-200">
                  <KeyRound size={16} className="text-slate-400 flex-shrink-0" />
                  <code className="text-sm text-slate-800 tracking-wider break-all">{l.key}</code>
                </div>
                <dl className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                  <div>
                    <dt className="text-xs text-slate-500">{__('Expires')}</dt>
                    <dd className="text-slate-900 mt-0.5">{l.lifetime ? __('Never (lifetime)') : l.expiry ? fmtDate(l.expiry) : '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">{__('Sites')}</dt>
                    <dd className="text-slate-900 mt-0.5">{l.activations ? sprintf(__('%1$d of %2$d used'), l.activations.active, l.activations.total) : '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">{__('Last checked')}</dt>
                    <dd className="text-slate-900 mt-0.5">{l.last_check ? new Date(l.last_check * 1000).toLocaleString() : '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">{__('Pro add-on')}</dt>
                    <dd className="text-slate-900 mt-0.5">{p.plugin_active ? sprintf(__('Installed (v%s)'), p.version) : __('Not installed')}</dd>
                  </div>
                </dl>
                {l.last_error && <p className="text-xs text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">{l.last_error}</p>}
                <div className="flex flex-wrap gap-2 pt-1">
                  <button type="button" className="aeo-btn-secondary" disabled={busy} onClick={() => check.mutate()}>
                    <RefreshCw size={13} className={check.isPending ? 'animate-spin' : ''} /> {__('Check now')}
                  </button>
                  <button
                    type="button"
                    className="aeo-btn-ghost text-red-600"
                    disabled={busy}
                    onClick={() => {
                      if (window.confirm(__('Remove the license from this site? Pro features switch off and the activation is released so you can use it on another site.'))) remove.mutate();
                    }}
                  >
                    <Trash2 size={13} /> {__('Remove license')}
                  </button>
                </div>
              </div>
            ) : (
              <form
                className="space-y-3"
                onSubmit={(e) => {
                  e.preventDefault();
                  if (key.trim()) activate.mutate();
                }}
              >
                <label className="aeo-label" htmlFor="aeo-license-key">{__('Enter your license key')}</label>
                <div className="flex flex-col sm:flex-row gap-2">
                  <input
                    id="aeo-license-key"
                    className="aeo-input font-mono flex-1"
                    autoComplete="off"
                    spellCheck={false}
                    placeholder="XXXX-XXXX-XXXX-XXXX"
                    value={key}
                    onChange={(e) => setKey(e.target.value)}
                  />
                  <button type="submit" className="aeo-btn-primary" disabled={!key.trim() || busy}>
                    <ShieldCheck size={14} /> {activate.isPending ? __('Activating…') : __('Activate license')}
                  </button>
                </div>
                <p className="aeo-hint">{__('You find the key in your purchase email and in your account on the store. One key works on the number of sites in your plan.')}</p>
                {l.last_error && <p className="text-xs text-red-700">{l.last_error}</p>}
              </form>
            )}
          </Card>

          <Card>
            <CardHeader title={__('How licensing works')} />
            <ul className="space-y-2 text-[13px] text-slate-600">
              <li className="flex gap-2"><Check size={14} className="text-slate-400 mt-0.5 flex-shrink-0" />{__('The free plugin works fully on its own; Pro adds the features listed on the right.')}</li>
              <li className="flex gap-2"><Check size={14} className="text-slate-400 mt-0.5 flex-shrink-0" />{__('The license is checked once a day. If the license server cannot be reached, Pro keeps working.')}</li>
              <li className="flex gap-2"><Check size={14} className="text-slate-400 mt-0.5 flex-shrink-0" />{__('With an active license, Pro updates appear under Dashboard → Updates like any other plugin.')}</li>
              <li className="flex gap-2"><Check size={14} className="text-slate-400 mt-0.5 flex-shrink-0" />{__('When a license expires, Pro features switch off; your data and settings stay.')}</li>
            </ul>
          </Card>
        </div>

        <Card>
          <CardHeader title={__('What Pro adds')} action={<ProBadge />} />
          <ul className="space-y-3">
            {features.map((f) => (
              <li key={f.id} className="flex gap-2.5">
                {p.licensed ? (
                  <Check size={15} className="text-emerald-600 mt-0.5 flex-shrink-0" />
                ) : (
                  <Lock size={14} className="text-slate-300 mt-0.5 flex-shrink-0" />
                )}
                <div className="min-w-0">
                  <p className="text-sm text-slate-900">{f.label}</p>
                  <p className="text-xs text-slate-500">{f.description}</p>
                </div>
              </li>
            ))}
          </ul>
          {!p.licensed && (
            <a href={p.sales_url} target="_blank" rel="noreferrer" className="aeo-btn-primary w-full mt-5">
              {__('Get Pro')} <ArrowUpRight size={14} />
            </a>
          )}
          {p.licensed && <p className="text-xs text-emerald-700 mt-4 flex items-center gap-1.5"><Check size={13} /> {__('All Pro features are on.')}</p>}
          {!p.plugin_active && l.active && (
            <p className="text-xs text-amber-700 mt-4 flex items-center gap-1.5"><X size={13} /> {__('License is active, but the Pro add-on is not installed yet.')}</p>
          )}
        </Card>
      </div>
    </div>
  );
}
