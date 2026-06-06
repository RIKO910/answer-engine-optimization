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
        title="Content Briefs"
        description="AI-powered content opportunities and brief generation"
        icon={Lightbulb}
      />

      <Card className="mb-6">
        <CardHeader title="Generate Brief" description="Enter a topic to create an AEO content brief" />
        <div className="flex gap-3">
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
            <Sparkles size={16} />
            {generateMutation.isPending ? 'Generating...' : 'Generate'}
          </button>
        </div>
      </Card>

      {brief && (
        <Card className="mb-6 border-aeo-200/60 bg-gradient-brand-subtle">
          <div className="flex items-start gap-6">
            <div className="text-center flex-shrink-0">
              <div className="w-20 h-20 rounded-2xl bg-white shadow-card flex items-center justify-center">
                <span className="text-2xl font-bold aeo-gradient-text">{brief.opportunity_score as number}</span>
              </div>
              <p className="text-[10px] text-slate-400 mt-1 uppercase tracking-wider">Opportunity</p>
            </div>
            <div className="flex-1">
              <h3 className="text-lg font-bold text-slate-900">{brief.topic as string}</h3>
              <p className="text-sm text-slate-500 mt-1">
                Schema: <span className="font-medium text-aeo-600">{brief.schema_type as string}</span>
              </p>
              <div className="mt-4">
                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Target Questions</p>
                <ul className="space-y-1.5">
                  {((brief.target_questions || []) as string[]).map((q, i) => (
                    <li key={i} className="flex items-start gap-2 text-sm text-slate-700">
                      <Target size={14} className="text-aeo-500 mt-0.5 flex-shrink-0" />
                      {q}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        </Card>
      )}

      <Card>
        <CardHeader title="Content Opportunities" description="Topics with high AEO citation potential" />
        <div className="space-y-2">
          {opps.map((opp, i) => (
            <button
              key={i}
              onClick={() => { setTopic(opp.topic); generateMutation.mutate(opp.topic); }}
              className="flex items-center gap-4 w-full p-4 rounded-xl border border-slate-100 hover:border-aeo-200 hover:bg-aeo-50/30 transition-all text-left group"
            >
              <div className="aeo-icon-box bg-slate-50 text-slate-400 group-hover:bg-aeo-100 group-hover:text-aeo-600 transition-colors">
                <FileText size={16} />
              </div>
              <span className="text-sm font-medium text-slate-800 flex-1">{opp.topic}</span>
              <span className={cn(
                'aeo-badge tabular-nums',
                opp.opportunity_score >= 80 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200/60' :
                opp.opportunity_score >= 60 ? 'bg-amber-50 text-amber-700 ring-amber-200/60' :
                'bg-slate-100 text-slate-600'
              )}>
                {opp.opportunity_score}
              </span>
            </button>
          ))}
        </div>
      </Card>
    </div>
  );
}
