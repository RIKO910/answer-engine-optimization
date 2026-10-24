/**
 * Thin wrapper over wp.i18n so strings are translatable with WordPress language packs
 * (JSON files loaded by wp_set_script_translations for the "aeo-admin-app" handle).
 * Falls back to the source string outside WordPress (dev harness, tests).
 */
type WpI18n = {
  __: (text: string, domain?: string) => string;
  _n: (single: string, plural: string, n: number, domain?: string) => string;
  sprintf: (format: string, ...args: unknown[]) => string;
};

const DOMAIN = 'answer-engine-optimization';

function wpI18n(): WpI18n | undefined {
  return (window as unknown as { wp?: { i18n?: WpI18n } }).wp?.i18n;
}

export function __(text: string): string {
  return wpI18n()?.__(text, DOMAIN) ?? text;
}

export function _n(single: string, plural: string, n: number): string {
  const i = wpI18n();
  return i ? i._n(single, plural, n, DOMAIN) : n === 1 ? single : plural;
}

/** Minimal %s / %d / %1$s replacement (wp.i18n.sprintf when available). */
export function sprintf(format: string, ...args: unknown[]): string {
  const i = wpI18n();
  if (i) return i.sprintf(format, ...args);
  let idx = 0;
  return format.replace(/%(\d+\$)?[sd]/g, (_m, pos) => {
    const v = pos ? args[parseInt(pos, 10) - 1] : args[idx++];
    return String(v ?? '');
  });
}
