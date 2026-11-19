import { create } from 'zustand';
import { storage } from '@/lib/storage';

type S = {
  theme: 'system' | 'light' | 'dark'; hideBalance: boolean;
  setTheme: (t: S['theme']) => void; setHideBalance: (v: boolean) => void; load: () => Promise<void>;
};
export const useSettings = create<S>((set, g) => ({
  theme: 'system', hideBalance: false,
  setTheme: (theme) => { set({ theme }); storage.set('settings', JSON.stringify({ theme, hideBalance: g().hideBalance })); },
  setHideBalance: (hideBalance) => { set({ hideBalance }); storage.set('settings', JSON.stringify({ theme: g().theme, hideBalance })); },
  async load() {
    const raw = await storage.get('settings');
    if (raw) try { set(JSON.parse(raw)); } catch {}
  },
}));
