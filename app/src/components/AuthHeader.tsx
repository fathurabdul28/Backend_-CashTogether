import React from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { CaretLeft } from 'phosphor-react-native';
import { useTheme } from '@/theme';
import { IconButton } from './Button';
import { Text } from './Text';

export function Header({ title, back = true, right }: { title: string; back?: boolean; right?: React.ReactNode }) {
  const t = useTheme();
  const router = useRouter();
  return (
    <View style={{ minHeight: 56, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
      {back ? <IconButton label="Kembali" onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))} icon={<CaretLeft size={24} color={t.ink} />} style={{ marginLeft: -12 }} /> : null}
      <Text variant="displayM" accessibilityRole="header" style={{ flex: 1 }}>{title}</Text>
      {right}
    </View>
  );
}
