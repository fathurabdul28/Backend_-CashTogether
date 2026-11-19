import React from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import Svg, { Circle, Line, Rect } from 'react-native-svg';
import { Screen } from '@/components/Screen';
import { Text } from '@/components/Text';
import { Button } from '@/components/Button';
import { identity } from '@/theme/identity';
import { useTheme } from '@/theme';

function Illustration() {
  const t = useTheme();
  const m = identity.male[t.scheme], f = identity.female[t.scheme];
  return (
    <Svg width={220} height={130} viewBox="0 0 220 130">
      <Line x1={70} y1={70} x2={110} y2={30} stroke={t.inkFaint} strokeWidth={1.5} strokeDasharray="4 4" />
      <Line x1={150} y1={70} x2={110} y2={30} stroke={t.inkFaint} strokeWidth={1.5} strokeDasharray="4 4" />
      <Rect x={96} y={16} width={28} height={22} rx={6} fill={t.ink} />
      <Circle cx={70} cy={85} r={34} fill={m.primarySoft} stroke={m.primary} strokeWidth={2} />
      <Circle cx={150} cy={85} r={34} fill={f.primarySoft} stroke={f.primary} strokeWidth={2} />
    </Svg>
  );
}

export default function Welcome() {
  const t = useTheme();
  const router = useRouter();
  return (
    <Screen edges={['top', 'bottom']}>
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', gap: 28 }}>
        <View style={{ alignItems: 'center' }}>
          <View style={{ flexDirection: 'row', alignItems: 'flex-end' }}>
            <Text variant="displayL" accessibilityRole="header">CashTogether</Text>
            <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: t.expense, marginLeft: 3, marginBottom: 7 }} />
          </View>
        </View>
        <Illustration />
        <View style={{ gap: 8, alignItems: 'center' }}>
          <Text variant="displayM" style={{ textAlign: 'center' }}>Catat uang berdua, tanpa ribet.</Text>
          <Text color="inkMuted" style={{ textAlign: 'center' }}>Saldo masing-masing, tabungan bersama, selalu sinkron.</Text>
        </View>
      </View>
      <View style={{ gap: 8, paddingBottom: 16 }}>
        <Button label="Buat akun" size="lg" onPress={() => router.push('/(auth)/register')} />
        <Button label="Saya sudah punya akun" variant="ghost" onPress={() => router.push('/(auth)/login')} />
      </View>
    </Screen>
  );
}
