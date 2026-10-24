import { useEffect } from 'react';
import { X } from 'lucide-react';

export default function Modal({
  title,
  onClose,
  children,
  footer,
  wide,
}: {
  title: string;
  onClose: () => void;
  children: React.ReactNode;
  footer?: React.ReactNode;
  wide?: boolean;
}) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label={title}>
      <div className="absolute inset-0 bg-slate-900/30" onClick={onClose} />
      <div className={`relative bg-white rounded-xl border border-slate-200 shadow-xl w-full ${wide ? 'max-w-3xl' : 'max-w-lg'} max-h-[85vh] flex flex-col`}>
        <div className="flex items-center justify-between gap-4 px-5 h-12 border-b border-slate-200 flex-shrink-0">
          <h3 className="text-sm font-semibold text-slate-900 truncate">{title}</h3>
          <button type="button" onClick={onClose} className="aeo-btn-ghost aeo-btn-sm" aria-label="Close">
            <X size={14} />
          </button>
        </div>
        <div className="p-5 overflow-y-auto">{children}</div>
        {footer && <div className="px-5 py-3 border-t border-slate-200 flex justify-end gap-2 flex-shrink-0">{footer}</div>}
      </div>
    </div>
  );
}
