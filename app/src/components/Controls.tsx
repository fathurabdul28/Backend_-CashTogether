import React, { useState } from 'react';
import { Pressable, TextInput, View, type TextInputProps, type ViewStyle } from 'react-native';
import * as Haptics from 'expo-haptics';
import { CaretLeft, CaretRight, Eye, EyeSlash } from 'phosphor-react-native';
import { radius, useTheme } from '@/theme';
import { addMonths, monthLabel } from '@/lib/date';
import { Text } from './Text';
import { IconButton } from './Button';

export function SegmentedControl<T extends string>({ options, value, onChange, tint }: {
  options: { value: T; label: string }[]; value: T; onChange: (v: T) => void; tint?: string;
}) {
  const t = useTheme();
  return (
    <View accessibilityRole="tablist" style={{ flexDirection: 'row', backgroundColor: t.surfaceAlt, borderRadius: radius.md, padding: 4 }}>
      {options.map((o) => {
        const on = o.value === value;
        return (
          <Pressable key={o.value} accessibilityRole="tab" accessibilityState={{ selected: on }} onPress={() => onChange(o.value)}
            style={{ flex: 1, minHeight: 40, alignItems: 'center', justifyContent: 'center', borderRadius: radius.sm, backgroundColor: on ? t.surface : 'transparent' }}>
            <Text variant="label" style={{ color: on ? (tint ?? t.ink) : t.inkMuted }}>{o.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

export function FilterChip({ label, active, onPress }: { label: string; active?: boolean; onPress: () => void }) {
  const t = useTheme();
  return (
    <Pressable accessibilityRole="button" accessibilityState={{ selected: !!active }} onPress={onPress}
      style={{ height: 34, paddingHorizontal: 14, borderRadius: radius.pill, justifyContent: 'center', backgroundColor: active ? t.primary : t.surfaceAlt, marginRight: 8 }}>
      <Text variant="label" style={{ color: active ? t.onPrimary : t.inkMuted }}>{label}</Text>
    </Pressable>
  );
}

export function MonthSwitcher({ value, onChange }: { value: Date; onChange: (d: Date) => void }) {
  const t = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center' }}>
      <IconButton label="Bulan sebelumnya" onPress={() => onChange(addMonths(value, -1))} icon={<CaretLeft size={20} color={t.inkMuted} />} />
      <Text variant="titleM" style={{ minWidth: 150, textAlign: 'center', textTransform: 'capitalize' }}>{monthLabel(value)}</Text>
      <IconButton label="Bulan berikutnya" onPress={() => onChange(addMonths(value, 1))} icon={<CaretRight size={20} color={t.inkMuted} />} />
    </View>
  );
}

export function TextField({ label, error, secure, style, ...p }: TextInputProps & { label: string; error?: string; secure?: boolean; style?: ViewStyle }) {
  const t = useTheme();
  const [focus, setFocus] = useState(false);
  const [show, setShow] = useState(false);
  return (
    <View style={style}>
      <Text variant="label" color="inkMuted" style={{ marginBottom: 6 }}>{label}</Text>
      <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: t.surfaceAlt, borderRadius: radius.md, borderWidth: 1.5, borderColor: error ? t.danger : focus ? t.primary : 'transparent' }}>
        <TextInput
          {...p} accessibilityLabel={label} secureTextEntry={secure && !show}
          onFocus={() => setFocus(true)} onBlur={() => setFocus(false)}
          placeholderTextColor={t.inkFaint}
          style={{ flex: 1, height: 50, paddingHorizontal: 14, color: t.ink, fontFamily: 'PlusJakartaSans_500Medium', fontSize: 15, outlineStyle: 'none' } as any}
        />
        {secure ? <IconButton label={show ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'} onPress={() => setShow(!show)}
          icon={show ? <EyeSlash size={20} color={t.inkMuted} /> : <Eye size={20} color={t.inkMuted} />} /> : null}
      </View>
      {error ? <Text variant="bodySm" color="danger" style={{ marginTop: 4 }}>{error}</Text> : null}
    </View>
  );
}

const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '000', '0', 'del'] as const;
export function NumPad({ onKey, onBackspace, onClear, keyHeight = 56 }: { onKey: (k: string) => void; onBackspace: () => void; onClear: () => void; keyHeight?: number }) {
  const t = useTheme();
  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
      {KEYS.map((k) => (
        <Pressable key={k}
          accessibilityRole="button" accessibilityLabel={k === 'del' ? 'Hapus' : k}
          onPress={() => { Haptics.selectionAsync().catch(() => {}); k === 'del' ? onBackspace() : onKey(k); }}
          onLongPress={k === 'del' ? onClear : undefined}
          style={({ pressed }) => ({ width: '31.8%', height: keyHeight, borderRadius: radius.md, backgroundColor: t.surfaceAlt, alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.7 : 1 })}>
          <Text variant="displayM">{k === 'del' ? '⌫' : k}</Text>
        </Pressable>
      ))}
    </View>
  );
}
