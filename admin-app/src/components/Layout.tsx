import { NavLink, Outlet, useLocation } from 'react-router-dom';
import {
  LayoutDashboard, FileText, Code2, HelpCircle, MapPin,
  ShoppingCart, BarChart3, Search, Lightbulb, Settings, Building2,
  Sparkles, ExternalLink,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const navGroups = [
  {
    label: 'Overview',
    items: [
      { to: '/', label: 'Dashboard', icon: LayoutDashboard },
      { to: '/analytics', label: 'Analytics', icon: BarChart3 },
    ],
  },
  {
    label: 'Optimize',
    items: [
      { to: '/content', label: 'Content', icon: FileText },
      { to: '/schema', label: 'Schema Builder', icon: Code2 },
      { to: '/faqs', label: 'FAQ Manager', icon: HelpCircle },
      { to: '/briefs', label: 'Content Briefs', icon: Lightbulb },
    ],
  },
  {
    label: 'Business',
    items: [
      { to: '/local', label: 'Local Business', icon: MapPin },
      { to: '/woocommerce', label: 'WooCommerce', icon: ShoppingCart },
    ],
  },
  {
    label: 'Tools',
    items: [
      { to: '/audit', label: 'Site Audit', icon: Search },
      { to: '/agency', label: 'Agency Tools', icon: Building2 },
      { to: '/settings', label: 'Settings', icon: Settings },
    ],
  },
];

export default function Layout() {
  const location = useLocation();

  return (
    <div className="aeo-shell">
      {/* Sidebar */}
      <aside className="w-64 flex-shrink-0 bg-gradient-dark shadow-sidebar flex flex-col">
        {/* Logo */}
        <div className="p-5 border-b border-white/10">
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-brand shadow-glow">
              <Sparkles size={18} className="text-white" />
            </div>
            <div>
              <h1 className="text-sm font-bold text-white leading-tight">AEO Genius</h1>
              <p className="text-[10px] text-indigo-300/70 font-medium tracking-wide uppercase">
                Answer Engine Optimization
              </p>
            </div>
          </div>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto p-3 space-y-5">
          {navGroups.map((group) => (
            <div key={group.label}>
              <p className="px-3 mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-indigo-400/50">
                {group.label}
              </p>
              <div className="space-y-0.5">
                {group.items.map(({ to, label, icon: Icon }) => (
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
                        <Icon size={17} strokeWidth={isActive ? 2.5 : 2} />
                        {label}
                        {isActive && (
                          <span className="ml-auto w-1.5 h-1.5 rounded-full bg-aeo-400" />
                        )}
                      </>
                    )}
                  </NavLink>
                ))}
              </div>
            </div>
          ))}
        </nav>

        {/* Footer */}
        <div className="p-4 border-t border-white/10">
          <div className="rounded-xl bg-white/5 p-3">
            <p className="text-xs text-indigo-200/60">Version {window.aeoGeniusData?.version}</p>
            <a
              href={window.aeoGeniusData?.siteUrl}
              target="_blank"
              rel="noreferrer"
              className="mt-1 flex items-center gap-1 text-xs text-indigo-300 hover:text-white transition-colors"
            >
              View site <ExternalLink size={10} />
            </a>
          </div>
        </div>
      </aside>

      {/* Main content */}
      <div className="flex-1 flex flex-col min-w-0 bg-gradient-mesh">
        {/* Top bar */}
        <header className="flex items-center justify-between px-8 py-4 border-b border-slate-200/60 bg-white/60 backdrop-blur-sm">
          <div className="text-sm text-slate-500">
            <span className="text-slate-400">{window.aeoGeniusData?.siteName}</span>
            <span className="mx-2 text-slate-300">/</span>
            <span className="font-medium text-slate-700">
              {navGroups.flatMap((g) => g.items).find((i) => i.to === location.pathname)?.label || 'Dashboard'}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <span className="aeo-badge bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/60">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse-soft" />
              Active
            </span>
          </div>
        </header>

        <main className="flex-1 overflow-auto p-8">
          <div className="aeo-page-enter max-w-7xl mx-auto">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
