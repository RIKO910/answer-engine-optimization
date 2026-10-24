import { Link } from 'react-router-dom';
import { Lock, Sparkles, ArrowUpRight } from 'lucide-react';
import { __ } from '@/lib/i18n';
import { isPro, pro, proFeature } from '@/lib/pro';
import { cn } from '@/lib/utils';

/** Small "Pro" pill. */
export function ProBadge({ className }: { className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-0.5 h-[18px] px-1.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-aeo-50 text-aeo-700 border border-aeo-100',
        className
      )}
    >
      Pro
    </span>
  );
}

/** What to tell the user, depending on how far they are from Pro. */
function nextStep() {
  const p = pro();
  if (!p.plugin_active) {
    return { text: __('Get Pro'), hint: __('Install the Answer Engine Optimization Pro add-on and activate your license.') };
  }
  return { text: __('Activate license'), hint: __('The Pro add-on is installed. Activate your license to switch this on.') };
}

/**
 * Renders children with Pro, otherwise an upsell card.
 * `compact` fits inside an existing card; the default fills a page section.
 */
export default function ProGate({
  feature,
  title,
  description,
  children,
  compact,
  className,
}: {
  feature?: string;
  title?: string;
  description?: string;
  children?: React.ReactNode;
  compact?: boolean;
  className?: string;
}) {
  if (isPro()) return <>{children}</>;
  const f = feature ? proFeature(feature) : undefined;
  const p = pro();
  const step = nextStep();
  const heading = title || f?.label || __('Pro feature');
  const text = description || f?.description || '';

  return (
    <div
      className={cn(
        'rounded-xl border border-dashed border-aeo-200 bg-gradient-to-b from-aeo-50/60 to-white',
        compact ? 'p-4' : 'p-6 md:p-8',
        className
      )}
    >
      <div className={cn('flex gap-3', compact ? 'items-start' : 'flex-col items-start md:flex-row md:items-center md:justify-between')}>
        <div className="flex gap-3 min-w-0">
          <span className="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-aeo-100 text-aeo-600">
            <Lock size={15} />
          </span>
          <div className="min-w-0">
            <p className="text-sm font-semibold text-slate-900 flex items-center gap-2">
              {heading} <ProBadge />
            </p>
            {text && <p className="text-[13px] text-slate-600 mt-0.5 max-w-xl">{text}</p>}
            {!compact && <p className="text-xs text-slate-500 mt-1">{step.hint}</p>}
          </div>
        </div>
        <div className={cn('flex gap-2 flex-shrink-0', compact && 'ml-auto')}>
          {p.plugin_active ? (
            <Link to="/license" className="aeo-btn-primary aeo-btn-sm">
              <Sparkles size={12} /> {step.text}
            </Link>
          ) : (
            <>
              <a href={p.sales_url} target="_blank" rel="noreferrer" className="aeo-btn-primary aeo-btn-sm">
                <Sparkles size={12} /> {__('Get Pro')} <ArrowUpRight size={12} />
              </a>
              {!compact && (
                <Link to="/license" className="aeo-btn-secondary aeo-btn-sm">
                  {__('I have a license')}
                </Link>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
