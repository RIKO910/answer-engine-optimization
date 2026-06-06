import { useState, useEffect } from 'react';
import { Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import Layout from '@/components/Layout';
import OnboardingWizard from '@/components/OnboardingWizard';
import Dashboard from '@/pages/Dashboard';
import ContentOptimizer from '@/pages/ContentOptimizer';
import SchemaBuilder from '@/pages/SchemaBuilder';
import FaqManager from '@/pages/FaqManager';
import LocalBusiness from '@/pages/LocalBusiness';
import WooCommerce from '@/pages/WooCommerce';
import Analytics from '@/pages/Analytics';
import SiteAudit from '@/pages/SiteAudit';
import ContentBriefs from '@/pages/ContentBriefs';
import Settings from '@/pages/Settings';
import AgencyTools from '@/pages/AgencyTools';
import { api } from '@/lib/api';
import { pageToRoute } from '@/lib/utils';

function AppRoutes() {
  const navigate = useNavigate();
  const initialRoute = pageToRoute[window.aeoGeniusData?.currentPage || 'aeo-dashboard'] || '/';

  useEffect(() => {
    if (initialRoute !== '/') {
      navigate(initialRoute, { replace: true });
    }
  }, [initialRoute, navigate]);

  return (
    <Routes>
      <Route path="/" element={<Layout />}>
        <Route index element={<Dashboard />} />
        <Route path="content" element={<ContentOptimizer />} />
        <Route path="schema" element={<SchemaBuilder />} />
        <Route path="faqs" element={<FaqManager />} />
        <Route path="local" element={<LocalBusiness />} />
        <Route path="woocommerce" element={<WooCommerce />} />
        <Route path="analytics" element={<Analytics />} />
        <Route path="audit" element={<SiteAudit />} />
        <Route path="briefs" element={<ContentBriefs />} />
        <Route path="settings" element={<Settings />} />
        <Route path="agency" element={<AgencyTools />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}

export default function App() {
  const [showWizard, setShowWizard] = useState(false);
  const queryClient = useQueryClient();

  const { data: settings, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.getSettings(),
  });

  const onboardingComplete = (settings as Record<string, boolean>)?.onboarding_complete;

  useEffect(() => {
    if (!isLoading && !onboardingComplete) {
      setShowWizard(true);
    }
  }, [isLoading, onboardingComplete]);

  return (
    <>
      {showWizard && !onboardingComplete && (
        <OnboardingWizard
          onComplete={() => {
            setShowWizard(false);
            queryClient.invalidateQueries({ queryKey: ['settings'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
          }}
        />
      )}
      <AppRoutes />
    </>
  );
}
