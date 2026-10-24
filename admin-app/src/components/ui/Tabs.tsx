import { cn } from '@/lib/utils';

export default function Tabs<T extends string>({
  tabs,
  value,
  onChange,
  className,
}: {
  tabs: Array<{ id: T; label: string; count?: number }>;
  value: T;
  onChange: (id: T) => void;
  className?: string;
}) {
  return (
    <div role="tablist" className={cn('flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto', className)}>
      {tabs.map((t) => (
        <button
          key={t.id}
          role="tab"
          type="button"
          aria-selected={value === t.id}
          onClick={() => onChange(t.id)}
          className={cn(
            'relative h-9 px-3 -mb-px m-0 bg-transparent border-0 border-b-2 text-sm cursor-pointer whitespace-nowrap transition-colors',
            value === t.id ? 'border-aeo-600 text-slate-900 font-medium' : 'border-transparent text-slate-500 hover:text-slate-800'
          )}
        >
          {t.label}
          {typeof t.count === 'number' && (
            <span className="ml-1.5 text-2xs tabular-nums text-slate-400">{t.count}</span>
          )}
        </button>
      ))}
    </div>
  );
}
