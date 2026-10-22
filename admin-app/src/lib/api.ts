const getConfig = () => window.aeoGeniusData;

async function request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const { apiUrl, nonce } = getConfig();
  // With plain permalinks apiUrl is "...?rest_route=/aeo-genius/v1", so a query string
  // on the endpoint must be joined with "&" instead of a second "?".
  const url = apiUrl.includes('?') ? `${apiUrl}${endpoint.replace('?', '&')}` : `${apiUrl}${endpoint}`;
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...options.headers,
    },
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: 'Request failed' }));
    throw new Error(error.message || `HTTP ${response.status}`);
  }

  return response.json();
}

export const api = {
  getDashboard: () => request<Record<string, unknown>>('/dashboard'),
  getSettings: () => request<Record<string, unknown>>('/settings'),
  updateSettings: (data: Record<string, unknown>) =>
    request('/settings', { method: 'POST', body: JSON.stringify(data) }),
  getContentPosts: () => request<Array<Record<string, unknown>>>('/content/posts'),
  getAeoScore: (postId: number) => request<Record<string, unknown>>(`/aeo-score/${postId}`),
  getSchema: (postId: number) => request<Record<string, unknown>>(`/schema/${postId}`),
  saveSchema: (postId: number, schema: Record<string, unknown>) =>
    request<{ success: boolean; warnings?: string[]; reset?: boolean }>(`/schema/${postId}`, {
      method: 'POST',
      body: JSON.stringify(schema),
    }),
  getSchemaTypes: () => request<string[]>('/schema/types'),
  getGlobalSchema: () => request<Record<string, unknown>>('/schema/global'),
  generateFaq: (data: { post_id?: number; content?: string; count?: number }) =>
    request<{ faqs: Array<{ question: string; answer: string }> }>('/generate-faq', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
  getAllFaqs: () => request<Array<Record<string, unknown>>>('/faqs'),
  rewriteContent: (postId: number) =>
    request<{ original: string; rewritten: string }>('/rewrite-content', {
      method: 'POST',
      body: JSON.stringify({ post_id: postId }),
    }),
  getAudit: () => request<Record<string, unknown>>('/audit/site'),
  runAudit: () => request<Record<string, unknown>>('/audit/site', { method: 'POST' }),
  fixAuditIssue: (issueId: string) =>
    request(`/audit/fix/${issueId}`, { method: 'POST' }),
  getCitations: () => request<Record<string, unknown>>('/analytics/citations'),
  scanCitations: () => request<import('@/types').CitationScan>('/analytics/citations', { method: 'POST' }),
  getAnalyticsOverview: () => request<Record<string, unknown>>('/analytics/overview'),
  getCompetitorAnalysis: (refresh = false) =>
    request<{ own_types: string[]; competitors: import('@/types').CompetitorRow[] }>(
      `/analytics/competitors${refresh ? '?refresh=1' : ''}`
    ),
  getBriefOpportunities: () => request<Array<Record<string, unknown>>>('/briefs/opportunities'),
  generateBrief: (topic: string) =>
    request<Record<string, unknown>>('/briefs/generate', {
      method: 'POST',
      body: JSON.stringify({ topic }),
    }),
  getLocations: () => request<Array<Record<string, unknown>>>('/local/locations'),
  saveLocations: (locations: unknown[]) =>
    request('/local/locations', { method: 'POST', body: JSON.stringify(locations) }),
  completeOnboarding: (data: Record<string, unknown>) =>
    request('/onboarding/complete', { method: 'POST', body: JSON.stringify(data) }),
  bulkSchema: (postIds: number[], template: Record<string, unknown>) =>
    request('/bulk/schema', {
      method: 'POST',
      body: JSON.stringify({ post_ids: postIds, template }),
    }),
};
