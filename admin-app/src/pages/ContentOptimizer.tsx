import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { RefreshCw, Wand2, FileText, ExternalLink } from 'lucide-react';
import { api } from '@/lib/api';
import ScoreBadge from '@/components/ScoreBadge';
import PageHeader from '@/components/ui/PageHeader';
import { Card } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import type { PostScore } from '@/types';

export default function ContentOptimizer() {
  const [selectedPost, setSelectedPost] = useState<number | null>(null);
  const [rewriteResult, setRewriteResult] = useState<{ original: string; rewritten: string } | null>(null);

  const { data: posts = [], isLoading, refetch, isFetching } = useQuery({
    queryKey: ['content-posts'],
    queryFn: () => api.getContentPosts() as unknown as Promise<PostScore[]>,
  });

  const rewriteMutation = useMutation({
    mutationFn: (postId: number) => api.rewriteContent(postId),
    onSuccess: (data) => {
      setRewriteResult(data);
      toast.success('Content rewritten successfully');
    },
    onError: () => toast.error('Rewrite failed'),
  });

  return (
    <div>
      <PageHeader
        title="Content Optimizer"
        description="AEO scores and AI-powered content rewriting for maximum AI citation readiness"
        icon={FileText}
        actions={
          <button onClick={() => refetch()} disabled={isFetching} className="aeo-btn-secondary">
            <RefreshCw size={16} className={isFetching ? 'animate-spin' : ''} /> Refresh
          </button>
        }
      />

      <Card padding={false} className="overflow-hidden">
        {isLoading ? (
          <LoadingState message="Analyzing content..." />
        ) : posts.length === 0 ? (
          <EmptyState
            icon={FileText}
            title="No published content"
            description="Publish posts or pages to start optimizing"
          />
        ) : (
          <table className="aeo-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th>AEO Score</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {posts.map((post) => (
                <tr key={post.id}>
                  <td>
                    <a
                      href={post.url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1.5 font-medium text-aeo-600 hover:text-aeo-700 transition-colors"
                    >
                      {post.title}
                      <ExternalLink size={12} className="opacity-50" />
                    </a>
                  </td>
                  <td>
                    <span className="aeo-badge bg-slate-100 text-slate-600 capitalize">{post.type}</span>
                  </td>
                  <td><ScoreBadge score={post.score} /></td>
                  <td>
                    <button
                      onClick={() => { setSelectedPost(post.id); rewriteMutation.mutate(post.id); }}
                      disabled={rewriteMutation.isPending && selectedPost === post.id}
                      className="aeo-btn-secondary text-xs !py-1.5 !px-3"
                    >
                      <Wand2 size={14} />
                      {rewriteMutation.isPending && selectedPost === post.id ? 'Rewriting...' : 'AI Rewrite'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      {rewriteResult && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
          <Card>
            <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Original</h3>
            <div className="text-sm text-slate-600 max-h-72 overflow-auto leading-relaxed prose-sm" dangerouslySetInnerHTML={{ __html: rewriteResult.original }} />
          </Card>
          <Card className="ring-1 ring-emerald-200/60">
            <h3 className="text-sm font-semibold text-emerald-600 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Wand2 size={14} /> AI Optimized
            </h3>
            <div className="text-sm text-slate-600 max-h-72 overflow-auto leading-relaxed" dangerouslySetInnerHTML={{ __html: rewriteResult.rewritten }} />
          </Card>
        </div>
      )}
    </div>
  );
}
