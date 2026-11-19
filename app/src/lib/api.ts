import { storage } from './storage';

const BASE = (process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '');

export class ApiError extends Error {
  constructor(public status: number, message: string, public errors?: Record<string, string[]>) {
    super(message);
  }
}

let token: string | null = null;
let onUnauthorized: () => void = () => {};
export const setToken = (t: string | null) => { token = t; };
export const setUnauthorizedHandler = (fn: () => void) => { onUnauthorized = fn; };

export async function api<T = any>(method: string, path: string, body?: unknown, query?: Record<string, any>): Promise<T> {
  const qs = query
    ? '?' + Object.entries(query).filter(([, v]) => v != null && v !== '').map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`).join('&')
    : '';
  let res: Response;
  // Server gratis bisa butuh ~1 menit untuk bangun; setelah itu anggap gagal agar app tidak menggantung selamanya
  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), 70_000);
  try {
    res = await fetch(`${BASE}${path}${qs}`, {
      method, signal: ctl.signal,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError(0, 'Sedang offline atau server tidak terjangkau. Coba lagi sebentar ya.');
  } finally {
    clearTimeout(timer);
  }
  if (res.status === 204) return undefined as T;
  const data = await res.json().catch(() => ({}));
  if (res.status === 401 && token) onUnauthorized();
  if (!res.ok) {
    const first = data?.errors ? (Object.values(data.errors)[0] as string[])[0] : undefined;
    throw new ApiError(res.status, first ?? data?.message ?? 'Ada yang tidak beres. Coba lagi sebentar ya.', data?.errors);
  }
  return data as T;
}
export const get = <T = any>(p: string, q?: Record<string, any>) => api<T>('GET', p, undefined, q);
export const post = <T = any>(p: string, b?: unknown, q?: Record<string, any>) => api<T>('POST', p, b ?? {}, q);
export const put = <T = any>(p: string, b?: unknown, q?: Record<string, any>) => api<T>('PUT', p, b, q);
export const patch = <T = any>(p: string, b?: unknown) => api<T>('PATCH', p, b);
export const del = <T = any>(p: string) => api<T>('DELETE', p);
