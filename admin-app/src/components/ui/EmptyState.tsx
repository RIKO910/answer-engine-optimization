import { LucideIcon } from 'lucide-react';

interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  description: string;
  action?: React.ReactNode;
}

export default function EmptyState({ icon: Icon, title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center py-12 px-6 text-center">
      <div className="aeo-icon-box w-10 h-10 mb-4">
        <Icon size={18} strokeWidth={1.75} />
      </div>
      <h4 className="text-sm font-medium text-slate-900">{title}</h4>
      <p className="text-[13px] text-slate-500 mt-1 max-w-sm">{description}</p>
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}
