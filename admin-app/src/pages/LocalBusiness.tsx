import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Plus, Save, Trash2, MapPin, Building } from 'lucide-react';
import { api } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { Card } from '@/components/ui/Card';
import type { Location } from '@/types';

const emptyLocation = (): Location => ({
  name: '', address: '', phone: '', hours: '', lat: '', lng: '',
});

export default function LocalBusiness() {
  const { data } = useQuery({
    queryKey: ['locations'],
    queryFn: () => api.getLocations() as unknown as Promise<Location[]>,
  });

  const [locations, setLocations] = useState<Location[]>([emptyLocation()]);

  useEffect(() => {
    if (data && data.length > 0) setLocations(data);
  }, [data]);

  const saveMutation = useMutation({
    mutationFn: () => api.saveLocations(locations),
    onSuccess: () => toast.success('Locations saved'),
    onError: () => toast.error('Save failed'),
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
                  <p className="text-xs text-slate-500">LocalBusiness schema</p>
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
              {([
                { field: 'name' as const, label: 'Business name', span: 2 },
                { field: 'address' as const, label: 'Address', span: 2 },
                { field: 'phone' as const, label: 'Phone' },
                { field: 'hours' as const, label: 'Opening hours' },
                { field: 'lat' as const, label: 'Latitude' },
                { field: 'lng' as const, label: 'Longitude' },
              ]).map(({ field, label, span }) => (
                <div key={field} className={span === 2 ? 'md:col-span-2' : ''}>
                  <label className="aeo-label">{label}</label>
                  <input
                    type="text"
                    value={loc[field]}
                    onChange={(e) => update(i, field, e.target.value)}
                    className="aeo-input"
                  />
                </div>
              ))}
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
