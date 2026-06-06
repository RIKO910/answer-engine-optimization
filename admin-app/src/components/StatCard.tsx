import { LucideIcon, TrendingUp } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
  title: string;
  value: string | number;
  subtitle?: string;
  icon: LucideIcon;
  accent?: 'indigo' | 'emerald' | 'violet' | 'amber' | 'rose';
  trend?: string;
}

const accents = {
  indigo: { icon: 'bg-indigo-50 text-indigo-600 ring-indigo-100', bar: 'bg-indigo-500' },
  emerald: { icon: 'bg-emerald-50 text-emerald-600 ring-emerald-100', bar: 'bg-emerald-500' },
  violet: { icon: 'bg-violet-50 text-violet-600 ring-violet-100', bar: 'bg-violet-500' },
  amber: { icon: 'bg-amber-50 text-amber-600 ring-amber-100', bar: 'bg-amber-500' },
  rose: { icon: 'bg-rose-50 text-rose-600 ring-rose-100', bar: 'bg-rose-500' },
};

export default function StatCard({ title, value, subtitle, icon: Icon, accent = 'indigo', trend }: Props) {
  const style = accents[accent];

  return (
    <div className="aeo-card-hover group relative overflow-hidden">
      <div className={cn('absolute top-0 left-0 w-full h-0.5', style.bar)} />
      <div className="flex items-start justify-between">
        <div className="space-y-2">
          <p className="text-sm font-medium text-slate-500">{title}</p>
          <p className="text-3xl font-bold tracking-tight text-slate-900">{value}</p>
          {subtitle && <p className="text-xs text-slate-400">{subtitle}</p>}
          {trend && (
            <p className="flex items-center gap-1 text-xs font-medium text-emerald-600">
              <TrendingUp size={12} /> {trend}
            </p>
          )}
        </div>
        <div className={cn('aeo-icon-box ring-1 transition-transform duration-200 group-hover:scale-110', style.icon)}>
          <Icon size={20} strokeWidth={2} />
        </div>
      </div>
    </div>
  );
}
