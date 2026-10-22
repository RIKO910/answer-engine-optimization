import { LucideIcon, TrendingUp } from 'lucide-react';

interface Props {
  title: string;
  value: string | number;
  subtitle?: string;
  icon: LucideIcon;
  /** Kept for API compatibility; the minimal theme renders all stats neutrally. */
  accent?: 'indigo' | 'emerald' | 'violet' | 'amber' | 'rose';
  trend?: string;
}

export default function StatCard({ title, value, subtitle, icon: Icon, trend }: Props) {
  return (
    <div className="aeo-card">
      <div className="flex items-center justify-between">
        <p className="text-[13px] font-medium text-slate-500">{title}</p>
        <Icon size={16} strokeWidth={1.75} className="text-slate-400" />
      </div>
      <p className="mt-3 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{value}</p>
      {(subtitle || trend) && (
        <div className="mt-1 flex items-center gap-2 text-xs">
          {trend && (
            <span className="inline-flex items-center gap-1 font-medium text-emerald-600">
              <TrendingUp size={12} /> {trend}
            </span>
          )}
          {subtitle && <span className="text-slate-500">{subtitle}</span>}
        </div>
      )}
    </div>
  );
}
