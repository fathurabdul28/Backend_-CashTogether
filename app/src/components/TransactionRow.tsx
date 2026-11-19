import React from 'react';
import { Pressable, View } from 'react-native';
import { useTheme } from '@/theme';
import type { Account, Category, Member, Tx } from '@/features/types';
import { Avatar } from './Avatar';
import { CategoryIcon } from './CategoryIcon';
import { Money } from './Money';
import { Text } from './Text';

export function TransactionRow({ tx, category, account, creator, onPress, highlight }: {
  tx: Tx; category?: Category; account?: Account; creator?: Member; onPress?: () => void; highlight?: boolean;
}) {
  const t = useTheme();
  const title = category?.name ?? 'Lainnya';
  const sub = tx.note || account?.name || '';
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={`${title}, ${tx.type === 'income' ? 'pemasukan' : 'pengeluaran'} ${tx.amount} rupiah`}
      style={({ pressed }) => ({ minHeight: 64, flexDirection: 'row', alignItems: 'center', gap: 12, opacity: pressed ? 0.7 : 1, backgroundColor: highlight ? t.primarySoft : 'transparent' })}>
      <CategoryIcon icon={category?.icon ?? 'DotsThree'} color={category?.color ?? 'stone'} />
      <View style={{ flex: 1 }}>
        <Text variant="titleM" numberOfLines={1}>{title}</Text>
        {sub ? <Text variant="bodySm" color="inkMuted" numberOfLines={1}>{sub}</Text> : null}
      </View>
      <View style={{ alignItems: 'flex-end', gap: 4 }}>
        <Money value={tx.amount} type={tx.type} />
        {creator ? <Avatar user={creator} size={16} /> : null}
      </View>
    </Pressable>
  );
}
