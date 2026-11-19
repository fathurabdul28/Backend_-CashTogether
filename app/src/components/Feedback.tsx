import React, { useEffect, useRef } from 'react';
import { Animated, Pressable, View } from 'react-native';
import { Warning } from 'phosphor-react-native';
import { radius, useTheme } from '@/theme';
import { useToast } from '@/features/toast';
import { Text } from './Text';
import { Button } from './Button';

export function Toast({ bottom = 90 }: { bottom?: number }) {
  const t = useTheme();
  const toast = useToast((s) => s.toast);
  const hide = useToast((s) => s.hide);
  if (!toast) return null;
  return (
    <View pointerEvents="box-none" style={{ position: 'absolute', left: 20, right: 20, bottom, alignItems: 'center' }}>
      <View accessibilityLiveRegion="polite" style={{ flexDirection: 'row', alignItems: 'center', gap: 16, backgroundColor: t.ink, borderRadius: radius.md, paddingHorizontal: 16, paddingVertical: 12, maxWidth: 520 }}>
        <Text variant="body" style={{ color: t.bg, flexShrink: 1 }}>{toast.message}</Text>
        {toast.action ? (
          <Pressable accessibilityRole="button" onPress={() => { toast.action!.run(); hide(); }} hitSlop={10}>
            <Text variant="label" style={{ color: t.primarySoft }}>{toast.action.label}</Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

export function EmptyState({ icon, title, actionLabel, onAction }: { icon: React.ReactNode; title: string; actionLabel?: string; onAction?: () => void }) {
  return (
    <View style={{ alignItems: 'center', paddingVertical: 48, gap: 12 }}>
      {icon}
      <Text variant="body" color="inkMuted" style={{ textAlign: 'center' }}>{title}</Text>
      {actionLabel ? <Button variant="ghost" label={actionLabel} onPress={onAction} /> : null}
    </View>
  );
}

export function ErrorState({ message, onRetry }: { message?: string; onRetry?: () => void }) {
  const t = useTheme();
  return (
    <View style={{ alignItems: 'center', paddingVertical: 48, gap: 12 }}>
      <Warning size={48} color={t.inkFaint} />
      <Text variant="body" color="inkMuted" style={{ textAlign: 'center' }}>{message ?? 'Ada yang tidak beres. Coba lagi sebentar ya.'}</Text>
      {onRetry ? <Button variant="secondary" label="Coba lagi" onPress={onRetry} /> : null}
    </View>
  );
}

export function Skeleton({ w = '100%', h = 16, r = 8 }: { w?: number | `${number}%`; h?: number; r?: number }) {
  const t = useTheme();
  const v = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    const a = Animated.loop(Animated.sequence([
      Animated.timing(v, { toValue: 1, duration: 800, useNativeDriver: true }),
      Animated.timing(v, { toValue: 0, duration: 800, useNativeDriver: true }),
    ]));
    a.start();
    return () => a.stop();
  }, [v]);
  return <Animated.View style={{ width: w, height: h, borderRadius: r, backgroundColor: t.hairline, opacity: v.interpolate({ inputRange: [0, 1], outputRange: [0.5, 1] }) }} />;
}

export function ListSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <View style={{ gap: 16, paddingTop: 8 }}>
      {Array.from({ length: rows }).map((_, i) => (
        <View key={i} style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
          <Skeleton w={40} h={40} r={12} />
          <View style={{ flex: 1, gap: 6 }}><Skeleton w="55%" h={14} /><Skeleton w="35%" h={11} /></View>
          <Skeleton w={70} h={14} />
        </View>
      ))}
    </View>
  );
}

export function SectionHeader({ title, actionLabel, onAction }: { title: string; actionLabel?: string; onAction?: () => void }) {
  const t = useTheme();
  return (
    <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
      <Text variant="titleL" accessibilityRole="header">{title}</Text>
      {actionLabel ? <Pressable onPress={onAction} hitSlop={10} accessibilityRole="link"><Text variant="label" style={{ color: t.primary }}>{actionLabel}</Text></Pressable> : null}
    </View>
  );
}
