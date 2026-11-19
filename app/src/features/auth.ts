import { create } from 'zustand';
import { ApiError, get, post, setToken, setUnauthorizedHandler } from '@/lib/api';
import { storage } from '@/lib/storage';
import type { Gender } from '@/theme/identity';

export type User = {
  id: number; name: string; email: string; gender: Gender; identity_override: Gender | null; household_id: number | null;
  cycle_start_day: number; // tanggal mulai periode keuangan (1–28); 25 = periode gajian 25 s.d. 24
};

type State = {
  ready: boolean; user: User | null;
  hydrate: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (d: { name: string; email: string; password: string; gender: Gender }) => Promise<void>;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
};

const USER_KEY = 'user';

const readCachedUser = async (): Promise<User | null> => {
  try { const raw = await storage.get(USER_KEY); return raw ? (JSON.parse(raw) as User) : null; } catch { return null; }
};
const cacheUser = (u: User) => storage.set(USER_KEY, JSON.stringify(u));
const clearSession = async () => { setToken(null); await storage.del('token'); await storage.del(USER_KEY); };

/**
 * Sesi bertahan sampai pengguna menekan Keluar: token tidak kedaluwarsa di server.
 * Sesi hanya dihapus bila server menjawab 401 (token dicabut/tidak valid), BUKAN karena offline atau server sedang bangun.
 */
export const useAuth = create<State>((set) => ({
  ready: false, user: null,
  async hydrate() {
    const t = await storage.get('token');
    if (!t) { set({ ready: true }); return; }
    setToken(t);
    const refresh = async () => { const u = await get<User>('/me'); await cacheUser(u); set({ user: u }); };

    const cached = await readCachedUser();
    if (cached) { // buka app seketika dengan data tersimpan, segarkan di latar belakang
      set({ user: cached, ready: true });
      refresh().catch(async (e) => { if (e instanceof ApiError && e.status === 401) { await clearSession(); set({ user: null }); } });
      return;
    }
    try { await refresh(); }
    catch (e) { if (e instanceof ApiError && e.status === 401) await clearSession(); }
    set({ ready: true });
  },
  async login(email, password) {
    const r = await post<{ token: string; user: User }>('/auth/login', { email, password });
    setToken(r.token); await storage.set('token', r.token); await cacheUser(r.user); set({ user: r.user });
  },
  async register(d) {
    const r = await post<{ token: string; user: User }>('/auth/register', d);
    setToken(r.token); await storage.set('token', r.token); await cacheUser(r.user); set({ user: r.user });
  },
  async refresh() { const u = await get<User>('/me'); await cacheUser(u); set({ user: u }); },
  async logout() {
    try { await post('/auth/logout'); } catch {}
    await clearSession(); set({ user: null });
  },
}));
setUnauthorizedHandler(() => { clearSession(); useAuth.setState({ user: null }); });
