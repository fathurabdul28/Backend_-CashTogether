import React from 'react';
import { View } from 'react-native';
import { radius, useTheme } from '@/theme';
import { iconFor } from './icons';

export function CategoryIcon({ icon, color, size = 40 }: { icon: string; color: string; size?: 36 | 40 | 44 | 64 }) {
  const t = useTheme();
  const c = t.category(color);
  const I = iconFor(icon);
  return (
    <View style={{ width: size, height: size, borderRadius: radius.md, backgroundColor: c.bg, alignItems: 'center', justifyContent: 'center' }}>
      <I size={size * 0.52} color={c.fg} weight="regular" />
    </View>
  );
}
