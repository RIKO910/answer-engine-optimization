import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Plus, Save, Trash2, MapPin, Building } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card } from '@/components/ui/Card';
import type { Location } from '@/types';

const emptyLocation = (): Location => ({
  name: '', type: 'LocalBusiness', address: '', city: '', region: '', postal_code: '', country: '',
  phone: '', hours: '', price_range: '', url: '', lat: '', lng: '',
});

const businessTypes = [
  'LocalBusiness', 'Hotel', 'Resort', 'BedAndBreakfast', 'Motel', 'Hostel', 'LodgingBusiness',
  'Restaurant', 'Store', 'ProfessionalService', 'MedicalBusiness', 'LegalService', 'FinancialService',
];

type Field = { field: keyof Location; label: string; span?: 2; placeholder?: string; multiline?: boolean };

const fields: Field[] = [
  { field: 'name', label: 'Business name', span: 2 },
  { field: 'address', label: 'Street address', span: 2 },
  { field: 'city', label: 'City' },
  { field: 'region', label: 'State / region' },
  { field: 'postal_code', label: 'Postal code' },
  { field: 'country', label: 'Country', placeholder: 'BD' },
  { field: 'phone', label: 'Phone', placeholder: '+880 …' },
  { field: 'price_range', label: 'Price range', placeholder: '$$' },
  { field: 'hours', label: 'Opening hours', span: 2, multiline: true, placeholder: 'One per line, e.g.\nMo-Fr 09:00-18:00\nSa 10:00-14:00' },
  { field: 'lat', label: 'Latitude', placeholder: '21.4272' },
  { field: 'lng', label: 'Longitude', placeholder: '92.0058' },
];

export default function LocalBusiness() {
  const { data } = useQuery({
    queryKey: ['locations'],
    queryFn: () => api.getLocations() as unknown as Promise<Location[]>,
  });

  const [locations, setLocations] = useState<Location[]>([emptyLocation()]);

  useEffect(() => {
    if (data && data.length > 0) setLocations(data.map((l) => ({ ...emptyLocation(), ...l })));
  }, [data]);

  const saveMutation = useMutation({
    mutationFn: () => api.saveLocations(locations),
    onSuccess: () => toast.success('Locations saved'),
    onError: (e: Error) => toast.error(e.message || 'Save failed'),
  });

  const update = (index: number, field: keyof Location, value: string) => {
    const updated = [...locations];
    updated[index] = { ...updated[index], [field]: value };
    setLocations(updated);
  };

  return (
    <div>
      <PageHeader
        title="Local business"
        description="Name, address, phone and hours for each location, output as LocalBusiness schema."
        icon={MapPin}
        actions={
          <>
            <button onClick={() => setLocations([...locations, emptyLocation()])} className="aeo-btn-secondary">
              <Plus size={14} /> Add location
            </button>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="aeo-btn-primary">
              <Save size={14} /> {saveMutation.isPending ? 'Saving…' : 'Save'}
            </button>
          </>
        }
      />

      <div className="space-y-4">
        {locations.map((loc, i) => (
          <Card key={i}>
            <div className="flex items-center justify-between mb-5 pb-4 border-b border-slate-200">
              <div className="flex items-center gap-3">
                <div className="aeo-icon-box">
                  <Building size={15} strokeWidth={1.75} />
                </div>
                <div>
                  <h3 className="text-sm font-semibold text-slate-900">
                    {loc.name || `Location ${i + 1}`}
                  </h3>
                  <p className="text-xs text-slate-500">{loc.type || 'LocalBusiness'} schema · shown on the homepage</p>
                </div>
              </div>
              {locations.length > 1 && (
                <button
                  onClick={() => setLocations(locations.filter((_, idx) => idx !== i))}
                  className="aeo-btn-ghost aeo-btn-sm hover:!text-red-600"
                  aria-label="Remove location"
                >
                  <Trash2 size={14} />
                </button>
              )}
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <label className="aeo-label" htmlFor={`aeo-type-${i}`}>Business type</label>
                <select
                  id={`aeo-type-${i}`}
                  value={loc.type || 'LocalBusiness'}
                  onChange={(e) => update(i, 'type', e.target.value)}
                  className="aeo-select"
                >
                  {businessTypes.map((t) => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </div>
              {fields.map(({ field, label, span, placeholder, multiline }) => (
                <div key={field} className={span === 2 ? 'md:col-span-2' : ''}>
                  <label className="aeo-label" htmlFor={`aeo-${field}-${i}`}>{label}</label>
                  {multiline ? (
                    <textarea
                      id={`aeo-${field}-${i}`}
                      value={loc[field] ?? ''}
                      rows={3}
                      placeholder={placeholder}
                      onChange={(e) => update(i, field, e.target.value)}
                      className="aeo-textarea"
                    />
                  ) : (
                    <input
                      id={`aeo-${field}-${i}`}
                      type="text"
                      value={loc[field] ?? ''}
                      placeholder={placeholder}
                      onChange={(e) => update(i, field, e.target.value)}
                      className="aeo-input"
                    />
                  )}
                </div>
              ))}
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
