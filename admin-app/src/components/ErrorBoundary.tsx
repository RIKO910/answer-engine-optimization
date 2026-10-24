import { Component, type ErrorInfo, type ReactNode } from 'react';
import { __, sprintf } from '@/lib/i18n';

interface Props {
  children: ReactNode;
}
interface State {
  error: Error | null;
}

/**
 * Keeps one broken page from blanking the whole admin: React unmounts the entire
 * tree on an uncaught render error, menu included. Shows the message instead.
 */
export default class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // eslint-disable-next-line no-console
    console.error('[AEO] page crashed:', error, info.componentStack);
  }

  render() {
    if (!this.state.error) return this.props.children;
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-5">
        <p className="text-sm font-semibold text-red-900">{__('This page could not be displayed.')}</p>
        <p className="text-[13px] text-red-800 mt-1">
          {sprintf(__('Error: %s'), this.state.error.message)}
        </p>
        <p className="text-xs text-red-700 mt-2">
          {__('Other pages still work. Please send this message to the plugin developer.')}
        </p>
        <button type="button" className="aeo-btn-secondary aeo-btn-sm mt-3" onClick={() => this.setState({ error: null })}>
          {__('Try again')}
        </button>
      </div>
    );
  }
}
