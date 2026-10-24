import { NavLink, Outlet, useLocation } from 'react-router-dom';
import {
  LayoutDashboard, FileText, Code2, HelpCircle, MapPin,
  ShoppingCart, BarChart3, Search, Lightbulb, Settings, Building2,
  ArrowUpRight, LucideIcon, Bot, Activity, Wrench, LayoutTemplate, ListChecks, KeyRound,
} from 'lucide-react';
import { cn, pageToRoute } from '@/lib/utils';
import { useEffect } from 'react';
import { __ } from '@/lib/i18n';
import ErrorBoundary from '@/components/ErrorBoundary';
import { ProBadge } from '@/components/ProGate';
import { isPro } from '@/lib/pro';

interface NavItem {
  to: string;
  label: string;
  icon: LucideIcon;
  /** Capability needed to see the item. */
  cap?: 'manage' | 'view' | 'schema' | 'ai';
  /** Whole page is a Pro feature (shows a badge until licensed). */
  pro?: boolean;
}

const caps = window.aeoGeniusData?.caps;

const allGroups: Array<{ label: string; items: NavItem[] }> = [
  {
    label: __('Overview'),
    items: [
      { to: '/', label: __('Dashboard'), icon: LayoutDashboard },
      { to: '/analytics', label: __('AI visibility'), icon: BarChart3 },
      { to: '/traffic', label: __('Traffic'), icon: Activity },
    ],
  },
  {
    label: __('Optimize'),
    items: [
      { to: '/content', label: __('Content'), icon: FileText },
      { to: '/content-tools', label: __('Content tools'), icon: ListChecks },
      { to: '/schema', label: __('Schema'), icon: Code2 },
      { to: '/templates', label: __('Schema templates'), icon: LayoutTemplate, cap: 'schema', pro: true },
      { to: '/faqs', label: __('FAQs'), icon: HelpCircle },
      { to: '/briefs', label: __('Briefs'), icon: Lightbulb },
    ],
  },
  {
    label: __('Business'),
    items: [
      { to: '/local', label: __('Local Business'), icon: MapPin },
      { to: '/woocommerce', label: 'WooCommerce', icon: ShoppingCart },
    ],
  },
  {
    label: __('Tools'),
    items: [
      { to: '/ai-access', label: __('AI access'), icon: Bot, cap: 'manage' },
      { to: '/audit', label: __('Site Audit'), icon: Search },
      { to: '/tools', label: __('Tools'), icon: Wrench, cap: 'manage' },
      { to: '/agency', label: __('Agency'), icon: Building2, cap: 'manage', pro: true },
      { to: '/settings', label: __('Settings'), icon: Settings, cap: 'manage' },
      { to: '/license', label: __('License'), icon: KeyRound, cap: 'manage' },
    ],
  },
];

const navGroups = allGroups
  .map((g) => ({ ...g, items: g.items.filter((i) => !i.cap || !caps || caps[i.cap]) }))
  .filter((g) => g.items.length > 0);

const allItems = navGroups.flatMap((g) => g.items);

function useBranding() {
  // White-label is a Pro feature; the server sends empty values without a license.
  const b = (window.aeoGeniusData as unknown as { branding?: { name?: string; logo?: string } })?.branding || {};
  return {
    name: b.name?.trim() || 'AEO',
    logo: b.logo?.trim() || '',
  };
}

function Brand() {
  const { name, logo } = useBranding();
  return (
    <div className="flex items-center gap-2.5 min-w-0">
      {logo ? (
        <img src={logo} alt="" className="w-7 h-7 rounded-md object-contain" />
      ) : (
        <div className="flex items-center justify-center w-7 h-7 rounded-md bg-slate-900 text-white text-[11px] font-semibold tracking-tight">
          {(name.length <= 3 ? name : name.charAt(0)).toUpperCase()}
        </div>
      )}
      <div className="min-w-0">
        <p className="text-sm font-semibold text-slate-900 leading-tight truncate">{name}</p>
        <p className="text-2xs text-slate-500 leading-tight truncate">{__('Answer Engine Optimization')}</p>
      </div>
    </div>
  );
}

export default function Layout() {
  const location = useLocation();

  // Keep the WordPress admin submenu highlight in step with the in-app route.
  useEffect(() => {
    const slug = Object.keys(pageToRoute).find((k) => pageToRoute[k] === location.pathname && k !== 'aeo-dashboard')
      || (location.pathname === '/' ? 'aeo-dashboard' : '');
    const menu = document.querySelector('#toplevel_page_aeo-dashboard .wp-submenu');
    if (!menu || !slug) return;
    menu.querySelectorAll('li').forEach((li) => {
      const link = li.querySelector('a');
      const href = link?.getAttribute('href') || '';
      const match = new RegExp(`[?&]page=${slug}(?:$|&|#)`).test(href);
      li.classList.toggle('current', match);
      link?.classList.toggle('current', match);
      if (match) link?.setAttribute('aria-current', 'page');
      else link?.removeAttribute('aria-current');
    });
  }, [location.pathname]);
  const current = allItems.find((i) => i.to === location.pathname)?.label || 'Dashboard';
  const data = window.aeoGeniusData;

  return (
    <div className="aeo-shell">
      {/* Sidebar (desktop) */}
      <aside className="aeo-sidebar hidden md:flex">
        <div className="h-14 flex items-center px-4 border-b border-slate-200">
          <Brand />
        </div>

        <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-5" aria-label="AEO">
          {navGroups.map((group) => (
            <div key={group.label}>
              <p className="px-2.5 mb-1 text-2xs font-medium text-slate-400">{group.label}</p>
              <div className="space-y-px">
                {group.items.map(({ to, label, icon: Icon, pro }) => (
                  <NavLink
                    key={to}
                    to={to}
                    end={to === '/'}
                    className={({ isActive }) =>
                      cn(isActive ? 'aeo-nav-item-active' : 'aeo-nav-item-inactive')
                    }
                  >
                    {({ isActive }) => (
                      <>
                        <Icon
                          size={16}
                          strokeWidth={1.75}
                          className={isActive ? 'text-aeo-600' : 'text-slate-400'}
                        />
                        {label}
                        {pro && !isPro() && <ProBadge className="ml-auto" />}
                      </>
                    )}
                  </NavLink>
                ))}
              </div>
            </div>
          ))}
        </nav>

        <div className="px-4 py-3 border-t border-slate-200 flex items-center justify-between">
          <span className="text-2xs text-slate-400 tabular-nums">v{data?.version}</span>
          <a
            href={data?.siteUrl}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-900 transition-colors"
          >
            {__('View site')} <ArrowUpRight size={12} />
          </a>
        </div>
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col min-w-0">
        <header className="h-14 flex items-center justify-between gap-4 px-6 lg:px-8 border-b border-slate-200 bg-white">
          <div className="md:hidden">
            <Brand />
          </div>
          <div className="hidden md:flex items-center text-sm min-w-0">
            <span className="text-slate-500 truncate">{data?.siteName}</span>
            <span className="mx-2 text-slate-300">/</span>
            <span className="font-medium text-slate-900 truncate">{current}</span>
          </div>
        </header>

        {/* Mobile nav */}
        <nav className="md:hidden flex gap-1 overflow-x-auto px-4 py-2 border-b border-slate-200 bg-white" aria-label="AEO">
          {allItems.map(({ to, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              className={({ isActive }) =>
                cn(
                  'flex-shrink-0 h-8 px-3 inline-flex items-center rounded-md text-xs font-medium',
                  isActive ? 'bg-slate-100 text-slate-900' : 'text-slate-600'
                )
              }
            >
              {label}
            </NavLink>
          ))}
        </nav>

        <main className="flex-1 px-6 py-8 lg:px-10">
          <div key={location.pathname} className="aeo-page-enter max-w-6xl mx-auto">
            <ErrorBoundary key={location.pathname}>
              <Outlet />
            </ErrorBoundary>
          </div>
        </main>
      </div>
    </div>
  );
}
