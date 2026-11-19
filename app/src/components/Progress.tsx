import React from 'react';
import { View } from 'react-native';
import Svg, { Circle } from 'react-native-svg';
import { useTheme } from '@/theme';

export function ProgressBar({ value, tint, height = 8 }: { value: number; tint?: string; height?: number }) {
  const t = useTheme();
  const pct = Math.max(0, Math.min(1, value));
  return (
    <View accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: Math.round(value * 100) }}
      style={{ height, borderRadius: 99, backgroundColor: t.surfaceAlt, overflow: 'hidden' }}>
      <View style={{ width: `${pct * 100}%`, height, borderRadius: 99, backgroundColor: tint ?? t.primary }} />
    </View>
  );
}

export function ProgressRing({ value, size = 56, stroke = 6, tint, track, children }: {
  value: number; size?: number; stroke?: number; tint?: string; track?: string; children?: React.ReactNode;
}) {
  const t = useTheme();
  const r = (size - stroke) / 2, C = 2 * Math.PI * r, pct = Math.max(0, Math.min(1, value));
  return (
    <View style={{ width: size, height: size, alignItems: 'center', justifyContent: 'center' }}>
      <Svg width={size} height={size} style={{ position: 'absolute', transform: [{ rotate: '-90deg' }] }}>
        <Circle cx={size / 2} cy={size / 2} r={r} stroke={track ?? t.savingSoft} strokeWidth={stroke} fill="none" />
        <Circle cx={size / 2} cy={size / 2} r={r} stroke={tint ?? t.saving} strokeWidth={stroke} fill="none"
          strokeDasharray={`${C * pct} ${C}`} strokeLinecap="round" />
      </Svg>
      {children}
    </View>
  );
}
