export interface ProFeature {
  id: string;
  label: string;
  description: string;
}

export interface ProState {
  plugin_active: boolean;
  licensed: boolean;
  license: boolean;
  version: string;
  sales_url: string;
  license_url: string;
  features: ProFeature[];
}

const fallback: ProState = {
  plugin_active: false,
  licensed: false,
  license: false,
  version: '',
  sales_url: 'https://tarikul.top/plugins/answer-engine-optimization/',
  license_url: '',
  features: [],
};

/** Pro state as of page load (the License page reloads the page after changes). */
export function pro(): ProState {
  return { ...fallback, ...((window.aeoGeniusData as unknown as { pro?: ProState })?.pro || {}) };
}

export const isPro = () => pro().licensed;

export function proFeature(id: string): ProFeature | undefined {
  return pro().features.find((f) => f.id === id);
}
