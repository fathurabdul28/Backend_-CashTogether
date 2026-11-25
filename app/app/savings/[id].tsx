import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Text } from '@/components/Text';
import { Button } from '@/components/Button';
import { ProgressRing } from '@/components/Progress';
import { ErrorState, Skeleton } from '@/components/Feedback';
import { FormSheet } from '@/components/FormSheet';
import { useAddEntry, useConvertGold, useGoal } from '@/features/queries';
import { useLookup } from '@/features/lookup';
import { useUserColor, useTheme } from '@/theme';
import { formatGold, formatIDR } from '@/lib/format';
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
  const gold = useConvertGold(id);
  const [converting, setConverting] = useState(false);
  const [undoId, setUndoId] = useState<number | null>(null); // ketuk dua kali supaya tidak batal tak sengaja
  const L = useLookup();
  const [mode, setMode] = useState<'in' | 'out' | null>(null);
  const g = q.data;
  const hasTarget = !!g && g.target_amount > 0;
  const pct = g && hasTarget ? g.saved / g.target_amount : 0;
  const total = g ? Object.values(g.contributions).filter((v) => v > 0).reduce((a, b) => a + b, 0) : 0;

  return (
    <Screen scroll bottomInset={40}>
      <Header title={g?.name ?? 'Tabungan'} />
      {q.isLoading ? <View style={{ alignItems: 'center', paddingTop: 24 }}><Skeleton w={180} h={180} r={90} /></View> : q.isError || !g ? <ErrorState onRetry={() => q.refetch()} message={q.error?.message} /> : (
        <>
          <View style={{ alignItems: 'center', gap: 12, paddingVertical: 12 }}>
            {hasTarget ? (
              <>
                <ProgressRing value={pct} size={180} stroke={14}>
                  <Text variant="displayXL" accessibilityLabel={`${Math.round(pct * 100)} persen terkumpul`}>{Math.round(pct * 100)}%</Text>
                  <Text variant="bodySm" color="inkMuted">terkumpul</Text>
                </ProgressRing>
                <Text variant="titleM"><Text variant="titleL" color="saving">{formatIDR(g.saved)}</Text> dari {formatIDR(g.target_amount)}</Text>
              </>
            ) : (
              <>
                <Text variant="label" color="inkMuted">Total terkumpul</Text>
                <Text variant="displayL" color="saving">{formatIDR(g.saved)}</Text>
              </>
            )}
            {g.gold_grams > 0 ? <Text variant="titleM">+ {formatGold(g.gold_grams)}</Text> : null}
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

          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 8 }}>
            <Button style={{ flex: 1 }} label="Tambah setoran" tint={t.saving} onPress={() => setMode('in')} />
            <Button style={{ flex: 1 }} variant="secondary" label="Tarik dana" onPress={() => setMode('out')} />
          </View>
          <Button variant="secondary" label="Konversi ke emas" disabled={g.saved <= 0} onPress={() => setConverting(true)} style={{ marginBottom: 20, opacity: g.saved > 0 ? 1 : 0.5 }} />

          <Text variant="titleL" style={{ marginBottom: 8 }}>Rincian</Text>
          {(g.entries ?? []).length === 0 ? <Text color="inkMuted">Belum ada setoran.</Text> : g.entries!.map((e) => (
            <View key={e.id} style={{ minHeight: 56, paddingVertical: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, borderBottomWidth: 1, borderBottomColor: t.hairline }}>
              <View style={{ flex: 1 }}>
                <Text variant="titleM">{e.note || (e.amount >= 0 ? 'Setoran' : 'Penarikan')}</Text>
                {e.is_conversion ? (
                  <Pressable accessibilityRole="button" hitSlop={8} onPress={() => { if (undoId === e.id) { gold.undo.mutate(e.id); setUndoId(null); } else setUndoId(e.id); }}>
                    <Text variant="label" color="danger">{undoId === e.id ? 'Ketuk lagi untuk membatalkan' : 'Batalkan konversi'}</Text>
                  </Pressable>
                ) : null}
                <Text variant="bodySm" color="inkMuted">{L.member(e.contributed_by)?.name} · {format(new Date(e.occurred_at), 'd MMM yyyy', { locale: idLocale })}</Text>
              </View>
              <View style={{ alignItems: 'flex-end' }}>
                {e.amount !== 0 ? <Text variant="amount" color={e.amount > 0 ? 'income' : 'expense'}>{e.amount > 0 ? '+' : '−'}{formatIDR(Math.abs(e.amount))}</Text> : null}
                {e.gold_grams ? <Text variant="bodySm">{e.is_conversion ? '+' : ''}{formatGold(e.gold_grams)}</Text> : null}
                {e.amount === 0 && !e.gold_grams ? <Text variant="bodySm" color="inkFaint">belum ada harga</Text> : null}
              </View>
            </View>
          ))}
        </>
      )}
      <FormSheet visible={converting} title="Konversi ke emas" submitLabel="Konversi" onClose={() => setConverting(false)}
        hint={`Rupiah di tabungan ini: ${formatIDR(g?.saved ?? 0)}. Isi berapa rupiah yang dibelikan emas dan berapa gram yang didapat. Saldo dompet tidak berubah.`}
        fields={[{ key: 'amount', label: 'Rupiah yang dipakai', numeric: true }, { key: 'grams', label: 'Dapat emas (gram), mis. 0,5', decimal: true }]}
        onSubmit={async (v) => {
          const amount = Number(v.amount);
          const grams = Number(String(v.grams ?? '').replace(',', '.'));
          if (!(amount > 0)) throw new Error('Isi rupiah lebih dari 0.');
          if (amount > (g?.saved ?? 0)) throw new Error('Rupiah di tabungan ini tidak cukup.');
          if (!(grams >= 0.01)) throw new Error('Isi gram emas, mis. 1 atau 0,5.');
          await gold.convert.mutateAsync({ amount, gold_grams: grams });
          setConverting(false);
        }} />
      <FormSheet visible={!!mode} title={mode === 'out' ? 'Tarik dana' : 'Tambah setoran'} fields={[{ key: 'amount', label: 'Nominal (Rp)', numeric: true }, { key: 'note', label: 'Catatan', optional: true }]}
        onClose={() => setMode(null)}
        onSubmit={async (v) => { await add.mutateAsync({ amount: mode === 'out' ? -Number(v.amount) : Number(v.amount), note: v.note as string | undefined }); setMode(null); }} />
    </Screen>
  );
}
