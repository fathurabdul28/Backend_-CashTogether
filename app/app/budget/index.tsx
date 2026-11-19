import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Text } from '@/components/Text';
import { Button } from '@/components/Button';
import { MonthSwitcher } from '@/components/Controls';
import { CategoryIcon } from '@/components/CategoryIcon';
import { ProgressBar } from '@/components/Progress';
import { ErrorState, ListSkeleton } from '@/components/Feedback';
import { FormSheet } from '@/components/FormSheet';
import { useBudgets, useSetBudget } from '@/features/queries';
import { useLookup } from '@/features/lookup';
import { useToast } from '@/features/toast';
import { post } from '@/lib/api';
import { usePeriod } from '@/features/period';
import { formatIDR } from '@/lib/format';
import { radius, useTheme } from '@/theme';

export default function BudgetScreen() {
  const t = useTheme();
  const qc = useQueryClient();
  const L = useLookup();
  const { key: mk } = usePeriod();
  const q = useBudgets(mk);
  const set = useSetBudget(mk);
  const [editing, setEditing] = useState<number | null>(null);
  const items = new Map((q.data?.items ?? []).map((b) => [b.category_id, b]));
  const ratio = q.data && q.data.total_limit ? q.data.total_spent / q.data.total_limit : 0;

  return (
    <Screen scroll bottomInset={40} onRefresh={() => q.refetch()}>
      <Header title="Anggaran" />
      <MonthSwitcher />
      <View style={{ backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, padding: 16, gap: 10, marginVertical: 16 }}>
        <Text variant="label" color="inkMuted">Total terpakai</Text>
        <Text variant="displayL">{formatIDR(q.data?.total_spent ?? 0)}</Text>
        <ProgressBar value={ratio} tint={ratio > 1 ? t.expense : ratio >= 0.75 ? t.warning : t.primary} />
        <Text variant="bodySm" color="inkMuted">dari anggaran {formatIDR(q.data?.total_limit ?? 0)}</Text>
      </View>
      <Button variant="secondary" label="Salin dari periode lalu" onPress={async () => {
        const r = await post<{ copied: number }>('/budgets/copy-previous', {}, { month: mk });
        qc.invalidateQueries({ queryKey: ['budgets'] });
        useToast.getState().show(r.copied ? `${r.copied} anggaran disalin` : 'Tidak ada yang perlu disalin');
      }} style={{ marginBottom: 16 }} />
      {q.isLoading ? <ListSkeleton rows={4} /> : q.isError ? <ErrorState onRetry={() => q.refetch()} /> : L.categories.filter((c) => c.kind === 'expense').map((c) => {
        const b = items.get(c.id);
        const over = b ? b.spent > b.limit_amount : false;
        return (
          <Pressable key={c.id} accessibilityRole="button" accessibilityLabel={`Atur anggaran ${c.name}`} onPress={() => setEditing(c.id)}
            style={{ flexDirection: 'row', gap: 12, alignItems: 'center', paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: t.hairline }}>
            <CategoryIcon icon={c.icon} color={c.color} />
            <View style={{ flex: 1, gap: 6 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <Text variant="titleM">{c.name}</Text>
                <Text variant="bodySm" color="inkMuted">{b ? `${formatIDR(b.spent)} / ${formatIDR(b.limit_amount)}` : 'Belum diatur'}</Text>
              </View>
              {b ? <><ProgressBar value={b.limit_amount ? b.spent / b.limit_amount : 0} height={6} tint={over ? t.expense : t.primary} />
                <Text variant="bodySm" color={over ? 'expense' : 'inkMuted'}>{over ? `Lewat ${formatIDR(b.spent - b.limit_amount)}. Tidak apa-apa, coba atur ulang minggu depan.` : `Sisa ${formatIDR(b.limit_amount - b.spent)}`}</Text></> : null}
            </View>
          </Pressable>
        );
      })}
      <FormSheet visible={editing != null} title={`Anggaran ${L.category(editing)?.name ?? ''}`} fields={[{ key: 'limit_amount', label: 'Batas per bulan (Rp)', numeric: true }]}
        initial={{ limit_amount: String(items.get(editing ?? -1)?.limit_amount ?? '') }} onClose={() => setEditing(null)}
        onSubmit={async (v) => { await set.mutateAsync({ category_id: editing!, limit_amount: Number(v.limit_amount) }); setEditing(null); }} />
    </Screen>
  );
}
