import React from 'react';
import type { TextStyle } from 'react-native';
import { formatIDR, spokenIDR } from '@/lib/format';
import type { TxType } from '@/features/types';
import { Text } from './Text';
import type { TypeVariant } from '@/theme/typography';
import { useTheme } from '@/theme';

type Props = {
  value: number; type?: TxType; sign?: 'auto' | 'none'; variant?: TypeVariant; colorBy?: 'type' | 'none';
  hidden?: boolean; style?: TextStyle; color?: any;
};
const LABEL = { income: 'pemasukan', expense: 'pengeluaran' } as const;

export function Money({ value, type, sign = 'auto', variant = 'amount', colorBy = 'type', hidden, style, color }: Props) {
  const t = useTheme();
  const s = sign === 'auto' && type === 'income' ? '+' : sign === 'auto' && type === 'expense' ? '−' : '';
  const c = color ?? (colorBy === 'type' && type === 'income' ? 'income' : colorBy === 'type' && type === 'expense' ? 'expense' : 'ink');
  void t;
  return (
    <Text variant={variant} color={c} style={style} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.6}
      accessibilityLabel={hidden ? 'saldo disembunyikan' : spokenIDR(value, type ? LABEL[type] : undefined)}>
      {hidden ? 'Rp •••••••' : `${s}${formatIDR(value)}`}
    </Text>
  );
}
