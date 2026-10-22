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
        title="Content"
        description="AEO score for every published post, with optional AI rewriting."
        icon={FileText}
        actions={
          <button onClick={() => refetch()} disabled={isFetching} className="aeo-btn-secondary">
            <RefreshCw size={14} className={isFetching ? 'animate-spin' : ''} /> Refresh
          </button>
        }
      />

      <Card padding={false}>
        {isLoading ? (
          <LoadingState message="Analyzing content…" />
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
                <th>AEO score</th>
                <th className="w-32"><span className="sr-only">Actions</span></th>
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
                      className="group inline-flex items-center gap-1.5 text-slate-900 hover:text-aeo-700 transition-colors"
                    >
                      {post.title}
                      <ExternalLink size={12} className="text-slate-300 group-hover:text-aeo-600" />
                    </a>
                  </td>
                  <td>
                    <span className="text-[13px] text-slate-500 capitalize">{post.type}</span>
                  </td>
                  <td><ScoreBadge score={post.score} /></td>
                  <td>
                    <button
                      onClick={() => { setSelectedPost(post.id); rewriteMutation.mutate(post.id); }}
                      disabled={rewriteMutation.isPending && selectedPost === post.id}
                      className="aeo-btn-secondary aeo-btn-sm"
                    >
                      <Wand2 size={12} />
                      {rewriteMutation.isPending && selectedPost === post.id ? 'Rewriting…' : 'AI rewrite'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      {rewriteResult && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
          <Card>
            <h3 className="text-[13px] font-medium text-slate-500 mb-3">Original</h3>
            <div className="text-sm text-slate-600 max-h-72 overflow-auto leading-relaxed prose-sm" dangerouslySetInnerHTML={{ __html: rewriteResult.original }} />
          </Card>
          <Card className="border-aeo-200">
            <h3 className="text-[13px] font-medium text-aeo-700 mb-3 flex items-center gap-1.5">
              <Wand2 size={13} /> AI optimized
            </h3>
            <div className="text-sm text-slate-600 max-h-72 overflow-auto leading-relaxed" dangerouslySetInnerHTML={{ __html: rewriteResult.rewritten }} />
          </Card>
        </div>
      )}
    </div>
  );
}
