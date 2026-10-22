import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { HashRouter } from 'react-router-dom';
import { Toaster } from 'sonner';
import App from './App';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 30000, retry: 1 },
  },
});

const root = document.getElementById('aeo-genius-root');
if (root) {
  ReactDOM.createRoot(root).render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <HashRouter>
          <App />
          <Toaster position="bottom-right" toastOptions={{ style: { borderRadius: 8, border: "1px solid #e4e4e7", boxShadow: "0 8px 24px -8px rgba(0,0,0,0.12)", fontSize: 13 } }} />
        </HashRouter>
      </QueryClientProvider>
    </React.StrictMode>
  );
}
