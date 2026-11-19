import { create } from 'zustand';

type T = { id: number; message: string; action?: { label: string; run: () => void } } | null;
export const useToast = create<{ toast: T; show: (m: string, a?: NonNullable<T>['action']) => void; hide: () => void }>((set) => ({
  toast: null,
  show: (message, action) => {
    const id = Date.now();
    set({ toast: { id, message, action } });
    setTimeout(() => set((s) => (s.toast?.id === id ? { toast: null } : s)), action ? 5000 : 4000);
  },
  hide: () => set({ toast: null }),
}));
