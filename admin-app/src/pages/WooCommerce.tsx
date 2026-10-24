import { ShoppingCart, CheckCircle2, Save } from 'lucide-react';
import { __ } from '@/lib/i18n';
import { useSettingsDraft } from '@/lib/useSettings';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import LoadingState from '@/components/ui/LoadingState';
import { SwitchRow } from '@/components/ui/Switch';

// Only list what the plugin actually outputs (see WooCommerce_Integration.php).
const features = [
  __('Product schema on every product page'),
  __('Price, currency and stock availability'),
  __('Aggregate rating from reviews'),
  __('SKU, GTIN and brand'),
  __('Return policy and shipping details (below)'),
  __('Price valid-until date for sale prices'),
];

interface Woo {
  return_days: number;
  return_fees: 'free' | 'customer';
  return_method: 'mail' | 'store' | 'kiosk';
  return_country: string;
  ship_country: string;
  ship_rate: string;
  handling_min: number;
  handling_max: number;
  transit_min: number;
  transit_max: number;
}

const defaults: Woo = {
  return_days: 0,
  return_fees: 'free',
  return_method: 'mail',
  return_country: '',
  ship_country: '',
  ship_rate: '',
  handling_min: 0,
  handling_max: 1,
  transit_min: 1,
  transit_max: 5,
};

export default function WooCommerce() {
  const { query, draft, set, flag, toggle, save } = useSettingsDraft();
  if (query.isLoading) return <LoadingState />;

  const active = !!draft.woocommerce_active;
  const woo: Woo = { ...defaults, ...((draft.woo as Partial<Woo>) || {}) };
  const setWoo = (patch: Partial<Woo>) => set({ woo: { ...woo, ...patch } });
  const num = (k: keyof Woo, label: string, hint?: string) => (
    <div>
      <label className="aeo-label" htmlFor={`woo-${k}`}>{label}</label>
      <input id={`woo-${k}`} type="number" min={0} className="aeo-input" value={String(woo[k] ?? '')} onChange={(e) => setWoo({ [k]: e.target.value } as Partial<Woo>)} />
      {hint && <p className="aeo-hint">{hint}</p>}
    </div>
  );

  return (
    <div>
      <PageHeader
        title="WooCommerce"
        description={__('Product structured data for AI shopping assistants: price, stock, ratings, returns and shipping.')}
        icon={ShoppingCart}
        actions={
          active && (
            <button type="button" className="aeo-btn-primary" disabled={save.isPending} onClick={() => save.mutate({ woo, woo_product_group: flag('woo_product_group') ? 1 : 0 })}>
              <Save size={14} /> {__('Save')}
            </button>
          )
        }
      />

      {!active ? (
        <Card>
          <EmptyState
            icon={ShoppingCart}
            title={__('WooCommerce not detected')}
            description={__('Install and activate WooCommerce to add Product schema with price, stock and ratings.')}
          />
        </Card>
      ) : (
        <div className="space-y-4">
          <Card>
            <CardHeader title={__('What gets added')} description={__('Applied automatically to product pages')} />
            <ul className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2.5">
              {features.map((f) => (
                <li key={f} className="flex items-center gap-2.5">
                  <CheckCircle2 size={15} strokeWidth={1.75} className="text-slate-400 flex-shrink-0" />
                  <span className="text-sm text-slate-800">{f}</span>
                </li>
              ))}
            </ul>
            <div className="-mx-2 mt-4 pt-3 border-t border-slate-200">
              <SwitchRow
                label={__('Variable products as ProductGroup')}
                hint={__('Lists each variation (size, colour…) with its own price and stock, the format Google and AI shopping results read for variants.')}
                checked={flag('woo_product_group')}
                onChange={() => toggle('woo_product_group')}
              />
            </div>
          </Card>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Card>
              <CardHeader title={__('Return policy')} description={__('Set days to 0 to say returns are not accepted. Leave country empty to use the store country.')} />
              <div className="grid grid-cols-2 gap-4">
                {num('return_days', __('Return window (days)'))}
                <div>
                  <label className="aeo-label" htmlFor="woo-fees">{__('Return shipping')}</label>
                  <select id="woo-fees" className="aeo-select" value={woo.return_fees} onChange={(e) => setWoo({ return_fees: e.target.value as Woo['return_fees'] })}>
                    <option value="free">{__('Free for the customer')}</option>
                    <option value="customer">{__('Customer pays')}</option>
                  </select>
                </div>
                <div>
                  <label className="aeo-label" htmlFor="woo-method">{__('How')}</label>
                  <select id="woo-method" className="aeo-select" value={woo.return_method} onChange={(e) => setWoo({ return_method: e.target.value as Woo['return_method'] })}>
                    <option value="mail">{__('By mail / courier')}</option>
                    <option value="store">{__('In store')}</option>
                    <option value="kiosk">{__('At a drop-off point')}</option>
                  </select>
                </div>
                <div>
                  <label className="aeo-label" htmlFor="woo-rc">{__('Country (ISO code)')}</label>
                  <input id="woo-rc" className="aeo-input uppercase" maxLength={2} placeholder="BD" value={woo.return_country} onChange={(e) => setWoo({ return_country: e.target.value })} />
                </div>
              </div>
            </Card>
            <Card>
              <CardHeader title={__('Shipping')} description={__('A typical rate and delivery time. Leave the rate empty to skip shipping details.')} />
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="aeo-label" htmlFor="woo-sc">{__('Ships to (ISO code)')}</label>
                  <input id="woo-sc" className="aeo-input uppercase" maxLength={2} placeholder="BD" value={woo.ship_country} onChange={(e) => setWoo({ ship_country: e.target.value })} />
                </div>
                <div>
                  <label className="aeo-label" htmlFor="woo-rate">{__('Rate (store currency)')}</label>
                  <input id="woo-rate" type="number" min={0} step="0.01" className="aeo-input" placeholder="60" value={woo.ship_rate} onChange={(e) => setWoo({ ship_rate: e.target.value })} />
                </div>
                {num('handling_min', __('Handling, min days'))}
                {num('handling_max', __('Handling, max days'))}
                {num('transit_min', __('Transit, min days'))}
                {num('transit_max', __('Transit, max days'))}
              </div>
            </Card>
          </div>
        </div>
      )}
    </div>
  );
}
