import React, { createContext, useContext, useMemo } from 'react';
import { useColorScheme } from 'react-native';
import { categoryColors, dark, light, type CategoryColorKey, type Palette } from './colors';
import { identityOf, type Gender, type Scheme } from './identity';
import { useAuth } from '@/features/auth';
import { useSettings } from '@/features/settings';

export type Theme = Palette & {
  scheme: Scheme;
  primary: string; primaryPress: string; primarySoft: string; onPrimary: string; fgOnSoft: string;
  card: string; onCard: string; // kartu hero: selalu warna identitas pekat, teks terang
  category: (k: string) => { bg: string; fg: string };
};

const Ctx = createContext<Theme | null>(null);

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const system = useColorScheme();
  const pref = useSettings((s) => s.theme);
  const user = useAuth((s) => s.user);
  const scheme: Scheme = pref === 'system' ? (system === 'dark' ? 'dark' : 'light') : pref;
  const gender: Gender = (user?.identity_override ?? user?.gender ?? 'male') as Gender;

  const theme = useMemo<Theme>(() => {
    const cc = categoryColors[scheme];
    return {
      ...(scheme === 'dark' ? dark : light),
      ...identityOf(gender, scheme),
      card: identityOf(gender, 'light').primary,
      onCard: '#FFFDF8',
      scheme,
      category: (k) => cc[k as CategoryColorKey] ?? cc.stone,
    };
  }, [scheme, gender]);
  return <Ctx.Provider value={theme}>{children}</Ctx.Provider>;
}

export function useTheme(): Theme {
  const t = useContext(Ctx);
  if (!t) throw new Error('useTheme di luar ThemeProvider');
  return t;
}

/** Warna identitas user LAIN (pasangan), bukan warna app. */
export function useUserColor(member?: { gender: Gender; color_variant?: 'default' | 'alt' } | null) {
  const { scheme } = useTheme();
  return identityOf(member?.gender ?? 'male', scheme, member?.color_variant ?? 'default');
}
export * from './tokens';
export * from './typography';
export { type Gender } from './identity';
