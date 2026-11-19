import React, { useState } from 'react';
import { Modal, Pressable, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Trash } from 'phosphor-react-native';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Text } from '@/components/Text';
import { Money } from '@/components/Money';
import { Button, IconButton } from '@/components/Button';
import { CategoryIcon } from '@/components/CategoryIcon';
import { ErrorState, Skeleton } from '@/components/Feedback';
import { useDeleteTransaction, useRestoreTransaction, useTransaction } from '@/features/queries';
import { useLookup } from '@/features/lookup';
import { useToast } from '@/features/toast';
import { timeLabel } from '@/lib/date';
import { radius, useTheme } from '@/theme';
import { formatIDR } from '@/lib/format';

export default function Detail() {
  const t = useTheme();
  const router = useRouter();
  const id = Number(useLocalSearchParams<{ id: string }>().id);
  const q = useTransaction(id);
  const L = useLookup();
  const del = useDeleteTransaction();
  const restore = useRestoreTransaction();
  const [confirm, setConfirm] = useState(false);
  const tx = q.data;
  const cat = L.category(tx?.category_id);

  const remove = async () => {
    setConfirm(false);
    await del.mutateAsync(id);
    router.back();
    useToast.getState().show('Transaksi dihapus', { label: 'Urungkan', run: () => restore.mutate(id) });
  };
  const Info = ({ label, children }: { label: string; children: React.ReactNode }) => (
    <View style={{ minHeight: 52, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: t.hairline, gap: 16 }}>
      <Text color="inkMuted">{label}</Text><View style={{ flexShrink: 1, alignItems: 'flex-end' }}>{children}</View>
    </View>
  );

  return (
    <Screen scroll>
      <Header title={cat?.name ?? 'Detail'} right={<IconButton label="Hapus" onPress={() => setConfirm(true)} icon={<Trash size={22} color={t.danger} />} />} />
      {q.isLoading ? <View style={{ gap: 16, alignItems: 'center', paddingTop: 24 }}><Skeleton w={64} h={64} r={12} /><Skeleton w={200} h={40} /></View>
        : q.isError || !tx ? <ErrorState onRetry={() => q.refetch()} message={q.error?.message} /> : (
        <>
          <View style={{ alignItems: 'center', gap: 10, paddingVertical: 20 }}>
            <CategoryIcon icon={cat?.icon ?? 'DotsThree'} color={cat?.color ?? 'stone'} size={64} />
            <Money value={tx.amount} type={tx.type} variant="displayXL" />
            <Text variant="bodySm" color="inkMuted">{timeLabel(tx.occurred_at)}</Text>
          </View>
          <Info label="Akun"><Text>{L.account(tx.account_id)?.name}</Text></Info>
          {tx.note ? <Info label="Catatan"><Text style={{ textAlign: 'right' }}>{tx.note}</Text></Info> : null}
          {tx.updated_by ? <Text variant="bodySm" color="inkMuted" style={{ marginTop: 12 }}>Diubah {timeLabel(tx.updated_at)}</Text> : null}
        </>
      )}
      <Modal transparent visible={confirm} animationType="fade" onRequestClose={() => setConfirm(false)}>
        <View style={{ flex: 1, backgroundColor: t.overlay, justifyContent: 'flex-end' }}>
          <Pressable style={{ flex: 1 }} onPress={() => setConfirm(false)} accessibilityLabel="Batal" />
          <View style={{ backgroundColor: t.surface, borderTopLeftRadius: radius.xl, borderTopRightRadius: radius.xl, padding: 20, gap: 12 }}>
            <Text variant="titleL">Hapus transaksi ini?</Text>
            <Text color="inkMuted">Saldo akan dihitung ulang{tx ? ` tanpa ${formatIDR(tx.amount)}` : ''}.</Text>
            <Button variant="danger" label="Hapus" loading={del.isPending} onPress={remove} />
            <Button variant="ghost" label="Batal" onPress={() => setConfirm(false)} />
          </View>
        </View>
      </Modal>
    </Screen>
  );
}
