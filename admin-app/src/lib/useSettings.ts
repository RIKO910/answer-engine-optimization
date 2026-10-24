import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { __ } from '@/lib/i18n';

export type SettingsMap = Record<string, unknown>;

/**
 * Settings query + a local draft for a page that edits a subset of settings.
 * `flag(key, fallback)` reads a 0/1 setting with the same default the PHP side uses.
 */
export function useSettingsDraft() {
  const qc = useQueryClient();
  const query = useQuery({ queryKey: ['settings'], queryFn: () => api.getSettings() });
  const [draft, setDraft] = useState<SettingsMap>({});
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    if (query.data && !dirty) setDraft(query.data as SettingsMap);
  }, [query.data, dirty]);

  const save = useMutation({
    mutationFn: (patch: SettingsMap) => api.updateSettings(patch) as Promise<SettingsMap>,
    onSuccess: (data) => {
      qc.setQueryData(['settings'], data);
      setDirty(false);
      setDraft(data);
      toast.success(__('Settings saved'));
    },
    onError: (e: Error) => toast.error(e.message || __('Could not save settings')),
  });

  const set = (patch: SettingsMap) => {
    setDraft((d) => ({ ...d, ...patch }));
    setDirty(true);
  };
  const flag = (key: string, fallback = false) =>
    draft[key] === undefined || draft[key] === null ? fallback : !!draft[key];
  const toggle = (key: string, fallback = false) => set({ [key]: flag(key, fallback) ? 0 : 1 });

  return { query, settings: (query.data || {}) as SettingsMap, draft, set, flag, toggle, save, dirty };
}
