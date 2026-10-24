import { useState, useEffect, useRef } from 'react';
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
import AiAccess from '@/pages/AiAccess';
import Traffic from '@/pages/Traffic';
import ContentTools from '@/pages/ContentTools';
import SchemaTemplates from '@/pages/SchemaTemplates';
import Tools from '@/pages/Tools';
import License from '@/pages/License';
import { api } from '@/lib/api';
import { pageToRoute } from '@/lib/utils';

function AppRoutes() {
  const navigate = useNavigate();
  const redirected = useRef(false);

  // Opening a WordPress submenu item (admin.php?page=aeo-content) should land on that
  // screen, once. Earlier this effect re-ran on every route change (react-router gives
  // a new `navigate` each time the location changes) and sent clicks in the plugin's
  // own menu straight back to the submenu page.
  useEffect(() => {
    if (redirected.current) return;
    redirected.current = true;
    const hasHashRoute = window.location.hash.length > 2; // e.g. "#/schema"
    const initialRoute = pageToRoute[window.aeoGeniusData?.currentPage || 'aeo-dashboard'] || '/';
    if (!hasHashRoute && initialRoute !== '/') {
      navigate(initialRoute, { replace: true });
    }
  }, [navigate]);

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
        <Route path="ai-access" element={<AiAccess />} />
        <Route path="traffic" element={<Traffic />} />
        <Route path="content-tools" element={<ContentTools />} />
        <Route path="templates" element={<SchemaTemplates />} />
        <Route path="tools" element={<Tools />} />
        <Route path="license" element={<License />} />
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
