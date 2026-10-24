import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Link } from 'react-router-dom';
import { BedDouble, Check, ExternalLink, HelpCircle, Hotel as HotelIcon, Save, Star } from 'lucide-react';
import { api, get, post } from '@/lib/api';
import { __, sprintf } from '@/lib/i18n';
import { useSettingsDraft } from '@/lib/useSettings';
import PageHeader from '@/components/ui/PageHeader';
import { Card, CardHeader } from '@/components/ui/Card';
import LoadingState from '@/components/ui/LoadingState';
import EmptyState from '@/components/ui/EmptyState';
import { SwitchRow } from '@/components/ui/Switch';
import { cn } from '@/lib/utils';

interface HotelData {
  quotel: boolean;
  hotel: boolean;
  faq_template: Array<{ question: string; answer: string }>;
  rating: { ratingValue: number; reviewCount: number } | null;
  rooms: Array<{ id: number; title: string; url: string; type: string; price: number; props: string[] }>;
  currency: string;
}

const propLabel: Record<string, string> = {
  description: __('Description'),
  image: __('Photo'),
  bed: __('Bed'),
  occupancy: __('Occupancy'),
  floorSize: __('Size'),
  amenityFeature: __('Amenities'),
  petsAllowed: __('Pets'),
  smokingAllowed: __('Smoking'),
  offers: __('Price'),
  aggregateRating: __('Rating'),
  numberOfRooms: __('Units'),
};
const wanted = ['description', 'image', 'bed', 'occupancy', 'floorSize', 'amenityFeature', 'offers'];
const hasProp = (r: { props: string[]; price: number }, p: string) => (p === 'offers' ? r.price > 0 : r.props.includes(p));

export default function Hotel() {
  const { data, isLoading } = useQuery({ queryKey: ['hotel'], queryFn: () => get<HotelData>('/hotel') });
  const posts = useQuery({ queryKey: ['content-posts'], queryFn: () => api.getContentPosts() });
  const { flag, toggle, save } = useSettingsDraft();
  const [faqs, setFaqs] = useState<Array<{ question: string; answer: string; on: boolean }>>([]);
  const [target, setTarget] = useState(0);

  useEffect(() => {
    if (data) setFaqs(data.faq_template.map((f) => ({ ...f, on: true })));
  }, [data]);

  const addFaqs = useMutation({
    mutationFn: () => post<{ total: number }>('/faqs/add', { post_id: target, items: faqs.filter((f) => f.on && f.answer.trim()).map(({ question, answer }) => ({ question, answer })) }),
    onSuccess: (d) => toast.success(sprintf(__('Added. That page now has %d FAQs.'), d.total)),
    onError: (e: Error) => toast.error(e.message),
  });

  if (isLoading || !data) return <LoadingState />;

  const ready = faqs.filter((f) => f.on && f.answer.trim()).length;
  const pages = (posts.data || []) as Array<{ id: number; title: string; type: string }>;
  const currency = data.currency || 'BDT';

  return (
    <div>
      <PageHeader
        title={__('Hotel')}
        description={__('Hotel and room schema built from Quotel Hotel Booking and your Local Business details, so AI answers can quote rooms, prices, check-in times and amenities correctly.')}
        actions={
          <button
            type="button"
            className="aeo-btn-primary"
            disabled={save.isPending}
            onClick={() => save.mutate({ hotel_rooms_schema: flag('hotel_rooms_schema', true) ? 1 : 0, review_ratings: flag('review_ratings', false) ? 1 : 0 })}
          >
            <Save size={14} /> {__('Save')}
          </button>
        }
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div className="aeo-card">
          <p className="text-xs text-slate-500">Quotel Hotel Booking</p>
          <p className={cn('text-sm font-medium mt-1 flex items-center gap-1.5', data.quotel ? 'text-emerald-700' : 'text-slate-500')}>
            {data.quotel ? <><Check size={14} /> {sprintf(__('%d rooms found'), data.rooms.length)}</> : __('Not detected')}
          </p>
        </div>
        <div className="aeo-card">
          <p className="text-xs text-slate-500">{__('Hotel details')}</p>
          {data.hotel ? (
            <p className="text-sm font-medium mt-1 flex items-center gap-1.5 text-emerald-700"><Check size={14} /> {__('Hotel location set up')}</p>
          ) : (
            <Link to="/local" className="text-sm font-medium mt-1 inline-flex items-center gap-1 text-aeo-700">{__('Add a location with type Hotel')} <ExternalLink size={12} /></Link>
          )}
        </div>
        <div className="aeo-card">
          <p className="text-xs text-slate-500">{__('Guest rating')}</p>
          <p className="text-sm font-medium mt-1 flex items-center gap-1.5">
            {data.rating ? <><Star size={14} className="text-amber-500" /> {data.rating.ratingValue} · {sprintf(__('%d reviews'), data.rating.reviewCount)}</> : <span className="text-slate-500">{__('No rated reviews yet')}</span>}
          </p>
        </div>
      </div>

      <Card className="mb-4">
        <CardHeader title={__('Output')} />
        <div className="-mx-2">
          <SwitchRow
            label={__('HotelRoom / Suite schema with an Offer on each room page')}
            hint={sprintf(__('Price from the room base price in %s, bed, occupancy, size, view and amenities.'), currency)}
            checked={flag('hotel_rooms_schema', true)}
            onChange={() => toggle('hotel_rooms_schema', true)}
          />
          <SwitchRow
            label={__('Star rating on room reviews')}
            hint={__('Adds a 1–5 rating to the room comment form. Ratings feed aggregateRating on the hotel and rooms.')}
            checked={flag('review_ratings', false)}
            onChange={() => toggle('review_ratings', false)}
          />
        </div>
      </Card>

      <Card padding={false} className="mb-4">
        <div className="p-5 pb-0">
          <CardHeader title={__('Rooms')} description={__('What each room page tells AI engines. Fill the missing fields in Quotel to complete them.')} />
        </div>
        {data.rooms.length === 0 ? (
          <EmptyState icon={BedDouble} title={__('No rooms')} description={__('Published Quotel rooms appear here.')} />
        ) : (
          <table className="aeo-table">
            <thead><tr><th>{__('Room')}</th><th>{__('Type')}</th><th className="text-right">{__('From')}</th><th>{__('In schema')}</th></tr></thead>
            <tbody>
              {data.rooms.map((r) => (
                <tr key={r.id}>
                  <td><a href={r.url} target="_blank" rel="noreferrer" className="text-slate-900 hover:text-aeo-700">{r.title}</a></td>
                  <td className="text-xs">{r.type}</td>
                  <td className="text-right tabular-nums">{r.price ? `${currency} ${r.price.toLocaleString()}` : '—'}</td>
                  <td>
                    <div className="flex flex-wrap gap-1">
                      {wanted.map((p) => {
                        const has = hasProp(r, p);
                        return (
                          <span key={p} className={cn('aeo-badge', !has && 'text-slate-400 line-through decoration-slate-300')} title={has ? '' : __('Missing')}>
                            {propLabel[p] || p}
                          </span>
                        );
                      })}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      <Card>
        <CardHeader
          title={__('Hotel FAQ starter')}
          description={__('The questions guests ask assistants most. Answers are pre-filled where your settings know them — complete the rest, then add them to a page.')}
          action={<HelpCircle size={16} className="text-slate-300" />}
        />
        <ul className="space-y-2 mb-4">
          {faqs.map((f, i) => (
            <li key={i} className={cn('flex gap-3 p-3 rounded-lg border', f.on ? 'border-slate-200' : 'border-dashed border-slate-200 opacity-60')}>
              <input type="checkbox" className="mt-1" checked={f.on} onChange={() => setFaqs((x) => x.map((y, j) => (j === i ? { ...y, on: !y.on } : y)))} aria-label={__('Include')} />
              <div className="flex-1 min-w-0 space-y-1.5">
                <input className="aeo-input text-sm font-medium" value={f.question} onChange={(e) => setFaqs((x) => x.map((y, j) => (j === i ? { ...y, question: e.target.value } : y)))} />
                <textarea
                  rows={2}
                  className={cn('aeo-textarea text-[13px]', f.on && !f.answer.trim() && 'border-amber-300')}
                  value={f.answer}
                  placeholder={__('Write the answer…')}
                  onChange={(e) => setFaqs((x) => x.map((y, j) => (j === i ? { ...y, answer: e.target.value } : y)))}
                />
              </div>
            </li>
          ))}
        </ul>
        <div className="flex flex-col sm:flex-row sm:items-center gap-2">
          <select className="aeo-select sm:w-72" value={target} onChange={(e) => setTarget(Number(e.target.value))} aria-label={__('Add to page')}>
            <option value={0}>{__('Add to page…')}</option>
            {pages.map((p) => <option key={p.id} value={p.id}>{p.title} ({p.type})</option>)}
          </select>
          <button type="button" className="aeo-btn-primary" disabled={!target || !ready || addFaqs.isPending} onClick={() => addFaqs.mutate()}>
            <HotelIcon size={14} /> {sprintf(__('Add %d FAQs'), ready)}
          </button>
          <span className="text-xs text-slate-500">{__('Questions without an answer are skipped.')}</span>
        </div>
      </Card>
    </div>
  );
}
