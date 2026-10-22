import { useQuery } from '@tanstack/react-query';
import { ShoppingCart, CheckCircle2 } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';

// Only list what the plugin actually outputs today (see WooCommerce_Integration.php).
const features = [
  'Product schema on every product page',
  'Price, currency and stock availability',
  'Aggregate rating from reviews',
  'SKU and brand (from the "brand" attribute)',
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
        title="WooCommerce"
        description="Product structured data for AI shopping assistants."
        icon={ShoppingCart}
      />

      {!active ? (
        <Card>
          <EmptyState
            icon={ShoppingCart}
            title="WooCommerce not detected"
            description="Install and activate WooCommerce to add Product schema with price, stock and ratings."
          />
        </Card>
      ) : (
        <div className="space-y-4">
          <div className="aeo-card flex items-center gap-3">
            <span className="aeo-dot bg-emerald-500" />
            <p className="text-sm text-slate-900 font-medium">WooCommerce is active</p>
            <p className="text-[13px] text-slate-500">Product schema is being added to your store.</p>
          </div>

          <Card>
            <CardHeader title="What gets added" description="Applied automatically to product pages" />
            <ul className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2.5">
              {features.map((f) => (
                <li key={f} className="flex items-center gap-2.5">
                  <CheckCircle2 size={15} strokeWidth={1.75} className="text-slate-400 flex-shrink-0" />
                  <span className="text-sm text-slate-800">{f}</span>
                </li>
              ))}
            </ul>
          </Card>

          <Card>
            <CardHeader
              title="How it works"
            />
            <p className="text-sm text-slate-600 leading-relaxed max-w-2xl">
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
