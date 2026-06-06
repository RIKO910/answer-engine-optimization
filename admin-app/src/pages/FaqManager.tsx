import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Sparkles, HelpCircle, RefreshCw, MessageCircleQuestion } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';

export default function FaqManager() {
  const [content, setContent] = useState('');
  const [generatedFaqs, setGeneratedFaqs] = useState<Array<{ question: string; answer: string }>>([]);

  const { data: library = [], refetch, isFetching } = useQuery({
    queryKey: ['faqs'],
    queryFn: () => api.getAllFaqs(),
  });

  const generateMutation = useMutation({
    mutationFn: () => api.generateFaq({ content, count: 8 }),
    onSuccess: (data) => {
      setGeneratedFaqs(data.faqs);
      toast.success(`Generated ${data.faqs.length} FAQs`);
    },
    onError: () => toast.error('FAQ generation failed'),
  });

  const lib = library as Array<{ question: string; answer: string; post_title: string }>;

  return (
    <div>
      <PageHeader
        title="FAQ Manager"
        description="AI-powered FAQ generation and global library management"
        icon={HelpCircle}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader
            title="AI FAQ Generator"
            description="Paste content to generate optimized Q&A pairs"
          />
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            rows={5}
            placeholder="Paste your article content here..."
            className="aeo-textarea mb-4"
          />
          <button
            onClick={() => generateMutation.mutate()}
            disabled={!content || generateMutation.isPending}
            className="aeo-btn-primary"
          >
            <Sparkles size={16} />
            {generateMutation.isPending ? 'Generating...' : 'Generate FAQs'}
          </button>

          {generatedFaqs.length > 0 && (
            <div className="mt-6 space-y-3">
              {generatedFaqs.map((faq, i) => (
                <div key={i} className="p-4 rounded-xl bg-gradient-brand-subtle border border-aeo-100/60">
                  <div className="flex items-start gap-2">
                    <MessageCircleQuestion size={16} className="text-aeo-500 mt-0.5 flex-shrink-0" />
                    <div>
                      <p className="text-sm font-semibold text-slate-800">{faq.question}</p>
                      <p className="text-sm text-slate-500 mt-1 leading-relaxed">{faq.answer}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>

        <Card>
          <CardHeader
            title={`FAQ Library (${lib.length})`}
            description="All FAQs across your site"
            action={
              <button onClick={() => refetch()} className="aeo-btn-ghost !py-1.5 !px-2">
                <RefreshCw size={14} className={isFetching ? 'animate-spin' : ''} />
              </button>
            }
          />
          {lib.length === 0 ? (
            <EmptyState
              icon={HelpCircle}
              title="No FAQs yet"
              description="Add FAQs via the post editor or generate them with AI"
            />
          ) : (
            <div className="space-y-2 max-h-[480px] overflow-auto pr-1">
              {lib.map((faq, i) => (
                <div key={i} className="p-3 rounded-xl hover:bg-slate-50 transition-colors border border-transparent hover:border-slate-100">
                  <p className="text-sm font-medium text-slate-800">{faq.question}</p>
                  <p className="text-xs text-slate-400 mt-0.5">{faq.post_title}</p>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}
