export default function LoadingState({ message = 'Loading...' }: { message?: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 gap-4">
      <div className="relative">
        <div className="w-10 h-10 rounded-full border-2 border-aeo-100" />
        <div className="absolute inset-0 w-10 h-10 rounded-full border-2 border-transparent border-t-aeo-500 animate-spin" />
      </div>
      <p className="text-sm text-slate-500 font-medium">{message}</p>
    </div>
  );
}
