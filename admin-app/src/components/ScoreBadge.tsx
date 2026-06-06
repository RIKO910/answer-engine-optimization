import { cn, getScoreColor, getScoreLabel } from '@/lib/utils';

interface Props {
  score: number;
  size?: 'sm' | 'md' | 'lg';
  showLabel?: boolean;
}

export default function ScoreBadge({ score, size = 'sm', showLabel = true }: Props) {
  return (
    <span
      className={cn(
        'aeo-badge font-semibold tabular-nums',
        getScoreColor(score),
        size === 'lg' && 'px-4 py-1.5 text-sm',
        size === 'md' && 'px-3 py-1 text-xs',
        size === 'sm' && 'px-2.5 py-0.5 text-xs'
      )}
    >
      {score}{showLabel && ` · ${getScoreLabel(score)}`}
    </span>
  );
}
