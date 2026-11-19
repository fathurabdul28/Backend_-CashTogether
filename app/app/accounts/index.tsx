import React, { useState } from 'react';
import { View } from 'react-native';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Text } from '@/components/Text';
import { Money } from '@/components/Money';
import { Button } from '@/components/Button';
import { SegmentedControl } from '@/components/Controls';
import { ACCOUNT_ICON } from '@/components/icons';
import { ErrorState, ListSkeleton } from '@/components/Feedback';
import { FormSheet } from '@/components/FormSheet';
import { useAccounts } from '@/features/queries';
import { post } from '@/lib/api';
import { radius, useTheme } from '@/theme';

const TYPES = [{ value: 'cash', label: 'Tunai' }, { value: 'bank', label: 'Bank' }, { value: 'ewallet', label: 'E-wallet' }, { value: 'credit', label: 'Kartu' }] as const;

export default function Accounts() {
  const t = useTheme();
  const qc = useQueryClient();
  const q = useAccounts();
  const [open, setOpen] = useState(false);
  const [type, setType] = useState<(typeof TYPES)[number]['value']>('bank');
  const add = useMutation({ mutationFn: (b: any) => post('/accounts', b), onSuccess: () => qc.invalidateQueries({ queryKey: ['accounts'] }) });

  return (
    <Screen scroll bottomInset={40} onRefresh={() => q.refetch()}>
      <Header title="Akun" />
      {q.isLoading ? <ListSkeleton rows={3} /> : q.isError ? <ErrorState onRetry={() => q.refetch()} /> : (q.data ?? []).map((a) => {
        const I = ACCOUNT_ICON[a.type];
        return (
          <View key={a.id} style={{ minHeight: 64, flexDirection: 'row', alignItems: 'center', gap: 12, borderBottomWidth: 1, borderBottomColor: t.hairline }}>
            <View style={{ width: 40, height: 40, borderRadius: radius.md, backgroundColor: t.surfaceAlt, alignItems: 'center', justifyContent: 'center' }}><I size={22} color={t.ink} /></View>
            <View style={{ flex: 1 }}><Text variant="titleM">{a.name}</Text><Text variant="bodySm" color="inkMuted">Hanya kamu yang melihat</Text></View>
            <Money value={a.balance} colorBy="none" sign="none" />
          </View>
        );
      })}
      <Button label="Tambah akun" variant="secondary" onPress={() => setOpen(true)} style={{ marginTop: 20 }} />
      <FormSheet visible={open} title="Akun baru" onClose={() => setOpen(false)}
        fields={[{ key: 'name', label: 'Nama akun', placeholder: 'BCA' }, { key: 'initial_balance', label: 'Saldo awal (Rp)', numeric: true, optional: true }]}
        onSubmit={async (v) => { await add.mutateAsync({ ...v, type }); setOpen(false); }} />
      <View style={{ marginTop: 16, gap: 8 }}>
        <Text variant="label" color="inkMuted">Jenis akun baru</Text>
        <SegmentedControl options={TYPES.map((x) => ({ ...x }))} value={type} onChange={setType} />
      </View>
    </Screen>
  );
}
