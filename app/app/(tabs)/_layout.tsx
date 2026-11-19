import React from 'react';
import { Pressable, View } from 'react-native';
import { Tabs, useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ChartBar, House, Plus, Receipt, Target } from 'phosphor-react-native';
import { Text } from '@/components/Text';
import { useTheme } from '@/theme';
import { usePulse } from '@/features/queries';

const TABS: Record<string, { label: string; Icon: typeof House }> = {
  index: { label: 'Beranda', Icon: House }, transactions: { label: 'Riwayat', Icon: Receipt },
  savings: { label: 'Tabungan', Icon: Target }, reports: { label: 'Laporan', Icon: ChartBar },
};

type BarProps = Parameters<NonNullable<React.ComponentProps<typeof Tabs>['tabBar']>>[0];
function TabBar({ state }: BarProps) {
  const t = useTheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const routes = state.routes.filter((r) => TABS[r.name]);
  const item = (r: (typeof routes)[number]) => {
    const on = state.routes[state.index].key === r.key;
    const { label, Icon } = TABS[r.name];
    return (
      <Pressable key={r.key} accessibilityRole="tab" accessibilityLabel={label} accessibilityState={{ selected: on }}
        onPress={() => router.navigate((r.name === 'index' ? '/(tabs)' : `/(tabs)/${r.name}`) as any)} style={{ flex: 1, minHeight: 56, alignItems: 'center', justifyContent: 'center', gap: 2 }}>
        <Icon size={24} weight={on ? 'fill' : 'regular'} color={on ? t.primary : t.inkFaint} />
        <Text variant="label" style={{ color: on ? t.primary : t.inkFaint, fontSize: 11 }} maxFontSizeMultiplier={1.2}>{label}</Text>
      </Pressable>
    );
  };
  return (
    <View style={{ backgroundColor: t.surface, borderTopWidth: 1, borderTopColor: t.hairline, paddingBottom: insets.bottom, minHeight: 64 + insets.bottom }}>
    <View style={{ width: '100%', maxWidth: 560, alignSelf: 'center', flexDirection: 'row', alignItems: 'center', minHeight: 64 }}>
      {routes.slice(0, 2).map(item)}
      <View style={{ width: 72, alignItems: 'center' }}>
        <Pressable accessibilityRole="button" accessibilityLabel="Tambah transaksi"
          onPress={() => { Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {}); router.push('/transaction/new'); }}
          style={({ pressed }) => ({ position: 'absolute', top: -34, width: 56, height: 56, borderRadius: 28, backgroundColor: t.primary, alignItems: 'center', justifyContent: 'center', transform: [{ scale: pressed ? 0.95 : 1 }],
            shadowColor: '#1C241F', shadowOpacity: 0.08, shadowRadius: 16, shadowOffset: { width: 0, height: 6 }, elevation: 4 })}>
          <Plus size={26} weight="bold" color={t.onPrimary} />
        </Pressable>
      </View>
      {routes.slice(2).map(item)}
    </View>
    </View>
  );
}

export default function TabsLayout() {
  usePulse(true);
  return (
    <Tabs tabBar={(p) => <TabBar {...p} />} screenOptions={{ headerShown: false }}>
      <Tabs.Screen name="index" /><Tabs.Screen name="transactions" /><Tabs.Screen name="savings" /><Tabs.Screen name="reports" />
    </Tabs>
  );
}
