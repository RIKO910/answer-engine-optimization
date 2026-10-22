import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Lightbulb, Sparkles, Target, FileText } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import { cn } from '@/lib/utils';

export default function ContentBriefs() {
  const [topic, setTopic] = useState('');
  const [brief, setBrief] = useState<Record<string, unknown> | null>(null);

  const { data: opportunities = [] } = useQuery({
    queryKey: ['briefs'],
    queryFn: () => api.getBriefOpportunities(),
  });

  const generateMutation = useMutation({
    mutationFn: (t: string) => api.generateBrief(t),
    onSuccess: (data) => { setBrief(data); toast.success('Brief generated'); },
    onError: () => toast.error('Brief generation failed'),
  });

  const opps = opportunities as Array<{ topic: string; opportunity_score: number }>;

  return (
    <div>
      <PageHeader
        title="Briefs"
        description="Turn a topic into an AEO content brief with target questions and schema."
        icon={Lightbulb}
      />

      <Card className="mb-4">
        <CardHeader title="New brief" />
        <div className="flex flex-col sm:flex-row gap-2">
          <input
            type="text"
            value={topic}
            onChange={(e) => setTopic(e.target.value)}
            placeholder="e.g. WordPress SEO best practices"
            className="aeo-input flex-1"
            onKeyDown={(e) => e.key === 'Enter' && topic && generateMutation.mutate(topic)}
          />
          <button
            onClick={() => generateMutation.mutate(topic)}
            disabled={!topic || generateMutation.isPending}
            className="aeo-btn-primary flex-shrink-0"
          >
            <Sparkles size={14} />
            {generateMutation.isPending ? 'Generating…' : 'Generate'}
          </button>
        </div>
      </Card>

      {brief && (
        <Card className="mb-4">
          <div className="flex items-start justify-between gap-6">
            <div className="flex-1 min-w-0">
              <h3 className="text-base font-semibold text-slate-900">{brief.topic as string}</h3>
              <p className="text-[13px] text-slate-500 mt-1">
                Suggested schema <span className="aeo-badge font-mono ml-1">{brief.schema_type as string}</span>
              </p>
            </div>
            <div className="text-right flex-shrink-0">
              <p className="text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{brief.opportunity_score as number}</p>
              <p className="text-xs text-slate-500">Opportunity</p>
            </div>
          </div>
          <div className="mt-5 pt-4 border-t border-slate-200">
            <p className="text-[13px] font-medium text-slate-500 mb-2">Target questions</p>
            <ul className="space-y-1.5">
              {((brief.target_questions || []) as string[]).map((q, i) => (
                <li key={i} className="flex items-start gap-2 text-sm text-slate-800">
                  <Target size={14} className="text-slate-400 mt-0.5 flex-shrink-0" />
                  {q}
                </li>
              ))}
            </ul>
          </div>
        </Card>
      )}

      <Card>
        <CardHeader title="Opportunities" description="Topics with AEO citation potential. Click one to draft a brief." />
        <div className="-mx-2">
          {opps.map((opp, i) => (
            <button
              key={i}
              onClick={() => { setTopic(opp.topic); generateMutation.mutate(opp.topic); }}
              className="flex items-center gap-3 w-full px-2 h-11 rounded-md bg-transparent border-0 hover:bg-slate-50 transition-colors text-left cursor-pointer"
            >
              <FileText size={15} strokeWidth={1.75} className="text-slate-400 flex-shrink-0" />
              <span className="text-sm text-slate-900 flex-1 truncate">{opp.topic}</span>
              <span className="aeo-badge tabular-nums">
                <span className={cn('aeo-dot', opp.opportunity_score >= 80 ? 'bg-emerald-500' : opp.opportunity_score >= 60 ? 'bg-amber-500' : 'bg-slate-300')} />
                {opp.opportunity_score}
              </span>
            </button>
          ))}
        </div>
      </Card>
    </div>
  );
}
