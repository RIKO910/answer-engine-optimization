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
        description="Generate question-and-answer pairs and browse every FAQ on your site."
        icon={HelpCircle}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <CardHeader
            title="Generate FAQs"
            description="Paste content to get question-and-answer pairs"
          />
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            rows={5}
            placeholder="Paste your article content here…"
            className="aeo-textarea mb-3"
          />
          <button
            onClick={() => generateMutation.mutate()}
            disabled={!content || generateMutation.isPending}
            className="aeo-btn-primary"
          >
            <Sparkles size={14} />
            {generateMutation.isPending ? 'Generating…' : 'Generate FAQs'}
          </button>

          {generatedFaqs.length > 0 && (
            <div className="mt-5 divide-y divide-slate-200 border-t border-slate-200">
              {generatedFaqs.map((faq, i) => (
                <div key={i} className="py-3">
                  <div className="flex items-start gap-2.5">
                    <MessageCircleQuestion size={15} className="text-slate-400 mt-0.5 flex-shrink-0" />
                    <div>
                      <p className="text-sm font-medium text-slate-900">{faq.question}</p>
                      <p className="text-[13px] text-slate-500 mt-1 leading-relaxed">{faq.answer}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>

        <Card>
          <CardHeader
            title={`Library · ${lib.length}`}
            description="All FAQs across your site"
            action={
              <button onClick={() => refetch()} className="aeo-btn-ghost aeo-btn-sm" aria-label="Refresh">
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
            <div className="-mx-2 max-h-[480px] overflow-auto">
              {lib.map((faq, i) => (
                <div key={i} className="px-2 py-2 rounded-md hover:bg-slate-50 transition-colors">
                  <p className="text-sm text-slate-900">{faq.question}</p>
                  <p className="text-xs text-slate-500 mt-0.5">{faq.post_title}</p>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}
