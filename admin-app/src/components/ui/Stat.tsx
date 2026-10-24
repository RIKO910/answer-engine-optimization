import { cn } from '@/lib/utils';

export default function Stat({
  label,
  value,
  hint,
  className,
}: {
  label: string;
  value: React.ReactNode;
  hint?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('aeo-card', className)}>
      <p className="text-xs text-slate-500">{label}</p>
      <p className="text-2xl font-semibold tracking-tight text-slate-900 tabular-nums mt-1">{value}</p>
      {hint && <p className="text-xs text-slate-500 mt-1">{hint}</p>}
    </div>
  );
}
