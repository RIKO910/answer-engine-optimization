import { cn, getScoreLabel } from '@/lib/utils';

interface Props {
  score: number;
  size?: 'sm' | 'md' | 'lg';
  showLabel?: boolean;
}

function dotColor(score: number) {
  if (score >= 71) return 'bg-emerald-500';
  if (score >= 41) return 'bg-amber-500';
  return 'bg-rose-500';
}

export default function ScoreBadge({ score, size = 'sm', showLabel = true }: Props) {
  return (
    <span
      className={cn(
        'aeo-badge tabular-nums',
        size === 'lg' && 'px-3 py-1 text-sm',
        size === 'md' && 'px-2.5 py-0.5 text-xs'
      )}
    >
      <span className={cn('aeo-dot', dotColor(score))} />
      <span className="font-semibold text-slate-900">{score}</span>
      {showLabel && <span className="text-slate-500">{getScoreLabel(score)}</span>}
    </span>
  );
}
