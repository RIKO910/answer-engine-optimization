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
  'aeo-tools': '/tools',
  'aeo-license': '/license',
};
