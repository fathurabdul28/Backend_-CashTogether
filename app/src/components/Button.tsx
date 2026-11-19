import React from 'react';
import { ActivityIndicator, Pressable, View, type ViewStyle } from 'react-native';
import * as Haptics from 'expo-haptics';
import { radius, useTheme } from '@/theme';
import { Text } from './Text';

type Props = {
  label: string; onPress?: () => void; variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  size?: 'md' | 'lg'; loading?: boolean; disabled?: boolean; icon?: React.ReactNode; tint?: string; style?: ViewStyle;
};

export function Button({ label, onPress, variant = 'primary', size = 'md', loading, disabled, icon, tint, style }: Props) {
  const t = useTheme();
  const off = disabled || loading;
  const bg = variant === 'primary' ? (tint ?? t.primary) : variant === 'danger' ? t.danger : variant === 'secondary' ? t.surface : 'transparent';
  const fg = variant === 'primary' ? t.onPrimary : variant === 'danger' ? t.surface : variant === 'ghost' ? t.primary : t.ink;
  return (
    <Pressable
      accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled: !!off, busy: !!loading }}
      disabled={off}
      onPress={() => { Haptics.selectionAsync().catch(() => {}); onPress?.(); }}
      style={({ pressed }) => [{
        height: size === 'lg' ? 56 : 48, borderRadius: radius.md, backgroundColor: bg,
        alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 8, paddingHorizontal: 20,
        borderWidth: variant === 'secondary' ? 1 : 0, borderColor: t.hairline,
        opacity: off ? 0.45 : pressed ? 0.9 : 1, transform: [{ scale: pressed ? 0.97 : 1 }],
      }, style]}
    >
      {loading ? <ActivityIndicator color={fg} /> : (<>{icon}<Text variant="titleM" style={{ color: fg }}>{label}</Text></>)}
    </Pressable>
  );
}

export function IconButton({ icon, label, onPress, style }: { icon: React.ReactNode; label: string; onPress?: () => void; style?: ViewStyle }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} hitSlop={6}
      style={({ pressed }) => [{ minWidth: 44, minHeight: 44, alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.6 : 1 }, style]}>
      <View>{icon}</View>
    </Pressable>
  );
}
