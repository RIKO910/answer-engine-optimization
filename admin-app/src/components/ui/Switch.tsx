import { cn } from '@/lib/utils';

export default function Switch({
  checked,
  onChange,
  label,
  disabled,
}: {
  checked: boolean;
  onChange: () => void;
  label: string;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={onChange}
      className={cn(
        'relative w-8 h-[18px] p-0 m-0 border-0 rounded-full cursor-pointer transition-colors flex-shrink-0 disabled:opacity-50 disabled:cursor-not-allowed',
        checked ? 'bg-aeo-600' : 'bg-slate-200'
      )}
    >
      <span
        className={cn(
          'absolute top-[2px] w-[14px] h-[14px] bg-white rounded-full transition-[left] duration-150',
          checked ? 'left-[16px]' : 'left-[2px]'
        )}
      />
    </button>
  );
}

/** A labelled row with a switch on the right. */
export function SwitchRow({
  label,
  hint,
  checked,
  onChange,
  disabled,
}: {
  label: string;
  hint?: React.ReactNode;
  checked: boolean;
  onChange: () => void;
  disabled?: boolean;
}) {
  return (
    <div className="flex items-center justify-between gap-4 px-2 py-2 min-h-10 rounded-md hover:bg-slate-50">
      <div className="min-w-0">
        <span className="text-sm text-slate-800">{label}</span>
        {hint && <p className="text-xs text-slate-500 mt-0.5">{hint}</p>}
      </div>
      <Switch checked={checked} onChange={onChange} label={label} disabled={disabled} />
    </div>
  );
}
