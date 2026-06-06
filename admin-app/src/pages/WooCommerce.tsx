import { useQuery } from '@tanstack/react-query';
import { ShoppingCart, CheckCircle2, Package } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';

const features = [
  'Product Schema Auto-generation',
  'Real-time Price & Availability',
  'Aggregate Rating Schema',
  'Brand, GTIN, MPN, SKU Mapping',
  'Product Variant Schema',
  'AI Product FAQs',
];

export default function WooCommerce() {
  const { data: settings } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.getSettings(),
  });

  const active = (settings as Record<string, boolean>)?.woocommerce_active;

  return (
    <div>
      <PageHeader
        title="WooCommerce AEO"
        description="Product schema and AI shopping optimization for your store"
        icon={ShoppingCart}
      />

      {!active ? (
        <Card>
          <EmptyState
            icon={ShoppingCart}
            title="WooCommerce Not Detected"
            description="Install and activate WooCommerce to enable product AEO features including auto schema, pricing, and review markup"
          />
        </Card>
      ) : (
        <div className="space-y-6">
          <div className="aeo-card bg-gradient-to-r from-emerald-50 to-teal-50 border-emerald-200/60">
            <div className="flex items-center gap-4">
              <div className="aeo-icon-box bg-emerald-100 text-emerald-600 ring-1 ring-emerald-200">
                <CheckCircle2 size={22} />
              </div>
              <div>
                <p className="font-semibold text-emerald-800">WooCommerce Active</p>
                <p className="text-sm text-emerald-600/80">Product AEO is enabled and running on your store</p>
              </div>
            </div>
          </div>

          <Card>
            <CardHeader title="Active Features" description="Automatically applied to product pages" />
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {features.map((f) => (
                <div key={f} className="flex items-center gap-3 p-3 rounded-xl bg-slate-50/80">
                  <CheckCircle2 size={16} className="text-emerald-500 flex-shrink-0" />
                  <span className="text-sm font-medium text-slate-700">{f}</span>
                </div>
              ))}
            </div>
          </Card>

          <Card>
            <CardHeader
              title="How It Works"
              description="Automatic schema injection"
              action={<Package size={18} className="text-slate-400" />}
            />
            <p className="text-sm text-slate-600 leading-relaxed">
              Product schema is automatically injected on all WooCommerce product pages,
              including real-time price, stock availability, customer ratings, and brand data.
              Toggle the WooCommerce module in Settings to control this behavior.
            </p>
          </Card>
        </div>
      )}
    </div>
  );
}
