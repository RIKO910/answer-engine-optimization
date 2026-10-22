import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Save, Wand2, Code2, Braces } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';

export default function SchemaBuilder() {
  const [selectedPost, setSelectedPost] = useState('');
  const [schemaType, setSchemaType] = useState('Article');
  const [schemaJson, setSchemaJson] = useState('');

  const { data: types = [] } = useQuery({
    queryKey: ['schema-types'],
    queryFn: () => api.getSchemaTypes(),
  });

  const { data: posts = [] } = useQuery({
    queryKey: ['content-posts'],
    queryFn: () => api.getContentPosts(),
  });

  const loadSchema = async (postId: string) => {
    if (!postId) return;
    const schema = await api.getSchema(Number(postId));
    setSchemaType((schema['@type'] as string) || 'Article');
    setSchemaJson(JSON.stringify(schema, null, 2));
  };

  const saveMutation = useMutation({
    mutationFn: () => api.saveSchema(Number(selectedPost), JSON.parse(schemaJson)),
    onSuccess: () => toast.success('Schema saved successfully'),
    onError: () => toast.error('Invalid schema JSON'),
  });

  const autoGenerate = () => {
    setSchemaJson(JSON.stringify({
      '@context': 'https://schema.org',
      '@type': schemaType,
      headline: 'Auto-generated schema',
    }, null, 2));
  };

  return (
    <div>
      <PageHeader
        title="Schema Builder"
        description="Edit the JSON-LD structured data for any post."
        icon={Code2}
      />

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <Card className="lg:col-span-4">
          <CardHeader title="Configuration" />
          <div className="space-y-4">
            <div>
              <label className="aeo-label">Post</label>
              <select
                value={selectedPost}
                onChange={(e) => { setSelectedPost(e.target.value); loadSchema(e.target.value); }}
                className="aeo-select"
              >
                <option value="">Choose a post…</option>
                {(posts as Array<{ id: number; title: string }>).map((p) => (
                  <option key={p.id} value={p.id}>{p.title}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="aeo-label">Schema type</label>
              <select value={schemaType} onChange={(e) => setSchemaType(e.target.value)} className="aeo-select">
                {types.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
              <p className="aeo-hint">{types.length} types available</p>
            </div>
            <div className="flex flex-col gap-2 pt-1">
              <button onClick={autoGenerate} className="aeo-btn-secondary w-full">
                <Wand2 size={14} /> Generate template
              </button>
              <button
                onClick={() => saveMutation.mutate()}
                disabled={!selectedPost || saveMutation.isPending}
                className="aeo-btn-primary w-full"
              >
                <Save size={14} /> {saveMutation.isPending ? 'Saving…' : 'Save schema'}
              </button>
            </div>
          </div>
        </Card>

        <Card className="lg:col-span-8" padding={false}>
          <div className="flex items-center gap-2 px-4 h-10 bg-slate-50 border-b border-slate-200">
            <Braces size={14} className="text-slate-400" />
            <span className="text-xs font-medium text-slate-600">JSON-LD</span>
            {schemaType && <span className="ml-auto aeo-badge font-mono">{schemaType}</span>}
          </div>
          <textarea
            value={schemaJson}
            onChange={(e) => setSchemaJson(e.target.value)}
            rows={22}
            className="block w-full max-w-none font-mono text-xs bg-white text-slate-800 p-4 m-0 border-0 rounded-none shadow-none focus:outline-none resize-y leading-relaxed"
            placeholder='{"@context": "https://schema.org", "@type": "Article", ...}'
            spellCheck={false}
          />
        </Card>
      </div>
    </div>
  );
}
