import { create } from 'zustand';
import { get, post, setToken, setUnauthorizedHandler } from '@/lib/api';
import { storage } from '@/lib/storage';
import type { Gender } from '@/theme/identity';

export type User = { id: number; name: string; email: string; gender: Gender; identity_override: Gender | null; household_id: number | null };

type State = {
  ready: boolean; user: User | null;
  hydrate: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (d: { name: string; email: string; password: string; gender: Gender }) => Promise<void>;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
};

export const useAuth = create<State>((set, getState) => ({
  ready: false, user: null,
  async hydrate() {
    const t = await storage.get('token');
    if (t) {
      setToken(t);
      try { set({ user: await get<User>('/me') }); } catch { setToken(null); await storage.del('token'); }
    }
    set({ ready: true });
  },
  async login(email, password) {
    const r = await post<{ token: string; user: User }>('/auth/login', { email, password });
    setToken(r.token); await storage.set('token', r.token); set({ user: r.user });
  },
  async register(d) {
    const r = await post<{ token: string; user: User }>('/auth/register', d);
    setToken(r.token); await storage.set('token', r.token); set({ user: r.user });
  },
  async refresh() { set({ user: await get<User>('/me') }); },
  async logout() {
    try { await post('/auth/logout'); } catch {}
    setToken(null); await storage.del('token'); set({ user: null });
  },
}));
setUnauthorizedHandler(() => { setToken(null); storage.del('token'); useAuth.setState({ user: null }); });
