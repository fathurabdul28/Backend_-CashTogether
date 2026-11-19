import React, { useState } from 'react';
import { View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Text } from '@/components/Text';
import { Button } from '@/components/Button';
import { ProgressRing } from '@/components/Progress';
import { ErrorState, Skeleton } from '@/components/Feedback';
import { FormSheet } from '@/components/FormSheet';
import { useAddEntry, useGoal } from '@/features/queries';
import { useLookup } from '@/features/lookup';
import { useUserColor, useTheme } from '@/theme';
import { formatIDR } from '@/lib/format';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';

function Seg({ m, amount, total }: { m: any; amount: number; total: number }) {
  const c = useUserColor(m);
  return amount > 0 ? <View style={{ flex: amount / total, backgroundColor: c.primary }} /> : null;
}
function Who({ m, amount }: { m: any; amount: number }) {
  const c = useUserColor(m);
  return <View><Text variant="label" style={{ color: c.fgOnSoft }}>{m.name}</Text><Text variant="bodySm" color="inkMuted">{formatIDR(amount)}</Text></View>;
}

export default function GoalDetail() {
  const t = useTheme();
  const id = Number(useLocalSearchParams<{ id: string }>().id);
  const q = useGoal(id);
  const add = useAddEntry(id);
  const L = useLookup();
  const [mode, setMode] = useState<'in' | 'out' | null>(null);
  const g = q.data;
  const pct = g ? g.saved / g.target_amount : 0;
  const total = g ? Object.values(g.contributions).filter((v) => v > 0).reduce((a, b) => a + b, 0) : 0;

  return (
    <Screen scroll bottomInset={40}>
      <Header title={g?.name ?? 'Tabungan'} />
      {q.isLoading ? <View style={{ alignItems: 'center', paddingTop: 24 }}><Skeleton w={180} h={180} r={90} /></View> : q.isError || !g ? <ErrorState onRetry={() => q.refetch()} message={q.error?.message} /> : (
        <>
          <View style={{ alignItems: 'center', gap: 12, paddingVertical: 12 }}>
            <ProgressRing value={pct} size={180} stroke={14}>
              <Text variant="displayXL" accessibilityLabel={`${Math.round(pct * 100)} persen terkumpul`}>{Math.round(pct * 100)}%</Text>
              <Text variant="bodySm" color="inkMuted">terkumpul</Text>
            </ProgressRing>
            <Text variant="titleM"><Text variant="titleL" color="saving">{formatIDR(g.saved)}</Text> dari {formatIDR(g.target_amount)}</Text>
            {g.achieved_at ? <Text variant="titleM" color="income">Kalian berhasil! 🎉</Text>
              : g.monthly_needed ? <Text variant="bodySm" color="inkMuted" style={{ textAlign: 'center' }}>Butuh {formatIDR(g.monthly_needed)}/bulan untuk capai target {format(new Date(g.target_date!), 'd MMM yyyy', { locale: idLocale })}</Text> : null}
          </View>

          {total > 0 ? (
            <View style={{ backgroundColor: t.surface, borderRadius: 16, borderWidth: 1, borderColor: t.hairline, padding: 16, gap: 12, marginBottom: 16 }}>
              <Text variant="overline" color="inkMuted">Kontribusi</Text>
              <View style={{ flexDirection: 'row', height: 10, borderRadius: 5, overflow: 'hidden', gap: 2 }}>
                {L.members.map((m) => <Seg key={m.user_id} m={m} amount={g.contributions[m.user_id] ?? 0} total={total} />)}
              </View>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                {L.members.map((m) => <Who key={m.user_id} m={m} amount={Math.max(0, g.contributions[m.user_id] ?? 0)} />)}
              </View>
            </View>
          ) : null}

          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 20 }}>
            <Button style={{ flex: 1 }} label="Tambah setoran" tint={t.saving} onPress={() => setMode('in')} />
            <Button style={{ flex: 1 }} variant="secondary" label="Tarik dana" onPress={() => setMode('out')} />
          </View>

          <Text variant="titleL" style={{ marginBottom: 8 }}>Riwayat setoran</Text>
          {(g.entries ?? []).length === 0 ? <Text color="inkMuted">Belum ada setoran.</Text> : g.entries!.map((e) => (
            <View key={e.id} style={{ minHeight: 56, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: t.hairline }}>
              <View><Text variant="titleM">{e.amount > 0 ? 'Setoran' : 'Penarikan'}</Text><Text variant="bodySm" color="inkMuted">{L.member(e.contributed_by)?.name} · {format(new Date(e.occurred_at), 'd MMM', { locale: idLocale })}</Text></View>
              <Text variant="amount" color={e.amount > 0 ? 'income' : 'expense'}>{e.amount > 0 ? '+' : '−'}{formatIDR(e.amount)}</Text>
            </View>
          ))}
        </>
      )}
      <FormSheet visible={!!mode} title={mode === 'out' ? 'Tarik dana' : 'Tambah setoran'} fields={[{ key: 'amount', label: 'Nominal (Rp)', numeric: true }, { key: 'note', label: 'Catatan', optional: true }]}
        onClose={() => setMode(null)}
        onSubmit={async (v) => { await add.mutateAsync({ amount: mode === 'out' ? -Number(v.amount) : Number(v.amount), note: v.note as string | undefined }); setMode(null); }} />
    </Screen>
  );
}
