export default function LoadingState({ message = 'Loading…' }: { message?: string }) {
  return (
    <div className="flex items-center justify-center py-24 gap-3" role="status" aria-live="polite">
      <span className="w-4 h-4 rounded-full border-2 border-slate-200 border-t-slate-600 animate-spin" />
      <p className="text-[13px] text-slate-500">{message}</p>
    </div>
  );
}
