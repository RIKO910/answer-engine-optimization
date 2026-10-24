import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function getScoreColor(score: number): string {
  if (score >= 71) return 'aeo-score-green';
  if (score >= 41) return 'aeo-score-orange';
  return 'aeo-score-red';
}

export function getScoreLabel(score: number): string {
  if (score >= 71) return 'Good';
  if (score >= 41) return 'Needs work';
  return 'Poor';
}

export const pageToRoute: Record<string, string> = {
  'aeo-dashboard': '/',
  'aeo-content': '/content',
  'aeo-schema': '/schema',
  'aeo-faqs': '/faqs',
  'aeo-local': '/local',
  'aeo-woocommerce': '/woocommerce',
  'aeo-analytics': '/analytics',
  'aeo-audit': '/audit',
  'aeo-briefs': '/briefs',
  'aeo-settings': '/settings',
  'aeo-agency': '/agency',
  'aeo-ai-access': '/ai-access',
  'aeo-traffic': '/traffic',
  'aeo-content-tools': '/content-tools',
  'aeo-templates': '/templates',
  'aeo-hotel': '/hotel',
  'aeo-tools': '/tools',
};

export const routeToPage: Record<string, string> = Object.entries(pageToRoute).reduce(
  (acc, [page, route]) => {
    acc[route] = page;
    return acc;
  },
  {} as Record<string, string>
);

export const pageTitles: Record<string, string> = {
  'aeo-dashboard': 'Dashboard',
  'aeo-content': 'Content Optimizer',
  'aeo-schema': 'Schema Builder',
  'aeo-faqs': 'FAQ Manager',
  'aeo-local': 'Local Business',
  'aeo-woocommerce': 'WooCommerce AEO',
  'aeo-analytics': 'AI Visibility',
  'aeo-traffic': 'Traffic',
  'aeo-content-tools': 'Content Tools',
  'aeo-templates': 'Schema Templates',
  'aeo-hotel': 'Hotel',
  'aeo-ai-access': 'AI Access',
  'aeo-tools': 'Tools',
  'aeo-audit': 'Site Audit',
  'aeo-briefs': 'Content Briefs',
  'aeo-settings': 'Settings',
  'aeo-agency': 'Agency Tools',
};

export function syncWordPressMenu(pageSlug: string) {
  if (typeof window !== 'undefined' && window.aeoGeniusData) {
    window.aeoGeniusData.currentPage = pageSlug;
  }

  // Update browser URL query parameter without full reload
  if (typeof window !== 'undefined' && window.location) {
    try {
      const url = new URL(window.location.href);
      if (url.searchParams.get('page') !== pageSlug) {
        url.searchParams.set('page', pageSlug);
        window.history.replaceState(window.history.state, '', url.toString());
      }
    } catch {
      // Ignore URL parsing errors
    }
  }

  // Update document title
  const title = pageTitles[pageSlug];
  if (title && typeof document !== 'undefined') {
    const siteName = window.aeoGeniusData?.siteName || '';
    document.title = siteName ? `${title} ‹ ${siteName} ‹ WordPress` : `${title} ‹ WordPress`;
  }

  // Update WordPress admin sidebar menu active classes
  if (typeof document === 'undefined') return;
  const adminMenu = document.getElementById('adminmenu');
  if (!adminMenu) return;

  const aeoTopMenu =
    adminMenu.querySelector<HTMLElement>('#toplevel_page_aeo-dashboard') ||
    adminMenu.querySelector<HTMLElement>('li.toplevel_page_aeo-dashboard') ||
    (adminMenu.querySelector('a[href*="page=aeo-"]')?.closest('li.menu-top') as HTMLElement | null);

  if (!aeoTopMenu) return;

  // Ensure top-level menu is marked active and open
  aeoTopMenu.classList.add('wp-has-current-submenu', 'wp-menu-open');
  aeoTopMenu.classList.remove('wp-not-current-submenu');

  const topAnchor = aeoTopMenu.querySelector<HTMLAnchorElement>('a.menu-top');
  if (topAnchor) {
    topAnchor.classList.add('wp-has-current-submenu', 'wp-menu-open');
    topAnchor.classList.remove('wp-not-current-submenu');
  }

  // Update submenu items
  const submenuLinks = aeoTopMenu.querySelectorAll<HTMLAnchorElement>('.wp-submenu a');
  submenuLinks.forEach((link) => {
    const href = link.getAttribute('href') || '';
    const match = href.match(/[?&]page=([^&#]+)/);
    const linkSlug = match ? match[1] : '';
    const parentLi = link.closest('li');

    const isCurrent = linkSlug === pageSlug;

    if (isCurrent) {
      link.classList.add('current');
      link.setAttribute('aria-current', 'page');
      if (parentLi) {
        parentLi.classList.add('current');
      }
    } else {
      link.classList.remove('current');
      link.removeAttribute('aria-current');
      if (parentLi) {
        parentLi.classList.remove('current');
      }
    }
  });
}

export function setupWordPressMenuInterception(navigate: (to: string) => void) {
  if (typeof document === 'undefined') return () => {};
  const adminMenu = document.getElementById('adminmenu');
  if (!adminMenu) return () => {};

  const handleClick = (e: MouseEvent) => {
    // Only handle primary (left) clicks without modifier keys
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return;
    }

    const target = e.target as HTMLElement | null;
    const link = target?.closest('a') as HTMLAnchorElement | null;
    if (!link) return;

    const href = link.getAttribute('href') || '';
    const match = href.match(/[?&]page=(aeo-[^&#]+)/);
    if (!match) return;

    const pageSlug = match[1];
    const targetRoute = pageToRoute[pageSlug];
    if (targetRoute === undefined) return;

    // Prevent full page reload and hash stripping
    e.preventDefault();

    // Navigate to target route in React Router
    navigate(targetRoute);
  };

  adminMenu.addEventListener('click', handleClick);
  return () => {
    adminMenu.removeEventListener('click', handleClick);
  };
}

