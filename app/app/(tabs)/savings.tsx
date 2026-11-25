import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { CheckCircle, Plus, Target } from 'phosphor-react-native';
import { Screen } from '@/components/Screen';
import { Text } from '@/components/Text';
import { IconButton } from '@/components/Button';
import { CategoryIcon } from '@/components/CategoryIcon';
import { ProgressBar } from '@/components/Progress';
import { EmptyState, ErrorState, ListSkeleton } from '@/components/Feedback';
import { FormSheet } from '@/components/FormSheet';
import { useGoals } from '@/features/queries';
import { useLookup } from '@/features/lookup';
import { post } from '@/lib/api';
import { formatGold, formatIDR } from '@/lib/format';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import { radius, useTheme } from '@/theme';
import { useUserColor } from '@/theme';

export function Contribution({ goalContrib }: { goalContrib: Record<string, number> }) {
  const L = useLookup();
  return (
    <View style={{ flexDirection: 'row', gap: 3 }}>
      {L.members.map((m) => <Dot key={m.user_id} m={m} on={(goalContrib[m.user_id] ?? 0) > 0} />)}
    </View>
  );
}
function Dot({ m, on }: { m: any; on: boolean }) {
  const c = useUserColor(m);
  return <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: on ? c.primary : c.primarySoft }} />;
}

const GOAL_FIELDS = [{ key: 'name', label: 'Nama target', placeholder: 'Dana Darurat' }, { key: 'target_amount', label: 'Nominal target (Rp)', numeric: true }];

export default function Savings() {
  const t = useTheme();
  const router = useRouter();
  const qc = useQueryClient();
  const q = useGoals();
  const [open, setOpen] = useState(false);
  const create = useMutation({ mutationFn: (b: any) => post('/saving-goals', { icon: 'Target', color: 'teal', ...b }), onSuccess: () => qc.invalidateQueries({ queryKey: ['goals'] }) });
  const goals = q.data ?? [];
  const total = goals.reduce((s, g) => s + g.saved, 0);

  return (
    <Screen scroll bottomInset={40} onRefresh={() => q.refetch()}>
      <View style={{ minHeight: 56, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <Text variant="displayM" accessibilityRole="header">Tabungan</Text>
        <IconButton label="Target baru" onPress={() => setOpen(true)} icon={<Plus size={24} color={t.primary} weight="bold" />} />
      </View>
      <View style={{ backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, padding: 16, marginBottom: 20, gap: 4 }}>
        <Text variant="label" color="inkMuted">Total ditabung</Text>
        <Text variant="displayL" color="saving">{formatIDR(total)}</Text>
        <Text variant="bodySm" color="inkMuted">dari {goals.length} tabungan{goals.some((g) => g.gold_grams > 0) ? ` · + ${formatGold(goals.reduce((s, g) => s + g.gold_grams, 0))}` : ''}</Text>
      </View>
      {q.isLoading ? <ListSkeleton rows={3} /> : q.isError ? <ErrorState onRetry={() => q.refetch()} /> : goals.length === 0 ? (
        <EmptyState icon={<Target size={48} color={t.inkFaint} />} title="Belum ada target. Mau mulai dari dana darurat?" actionLabel="Buat target" onAction={() => setOpen(true)} />
      ) : goals.map((g) => {
        const done = !!g.achieved_at;
        const hasTarget = g.target_amount > 0; // tabungan dari daftar chat boleh tanpa target
        const pct = hasTarget ? g.saved / g.target_amount : 0;
        return (
          <Pressable key={g.id} accessibilityRole="button" onPress={() => router.push(`/savings/${g.id}`)}
            style={{ flexDirection: 'row', gap: 12, padding: 14, marginBottom: 12, backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline }}>
            <CategoryIcon icon={g.icon} color={g.color} size={44} />
            <View style={{ flex: 1, gap: 6 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                <Text variant="titleM" style={{ flexShrink: 1 }}>{g.name}</Text>
                {done ? <View style={{ flexDirection: 'row', gap: 4, backgroundColor: t.incomeSoft, borderRadius: 99, paddingHorizontal: 8, paddingVertical: 2, alignItems: 'center' }}><CheckCircle size={14} color={t.income} weight="fill" /><Text variant="label" color="income">Tercapai</Text></View> : <Contribution goalContrib={g.contributions} />}
              </View>
              {g.target_date ? <Text variant="bodySm" color="inkMuted">Target {format(new Date(g.target_date), 'd MMM yyyy', { locale: id })}</Text> : null}
              {hasTarget ? (
                <>
                  <ProgressBar value={pct} tint={t.saving} />
                  <Text variant="bodySm" color="inkMuted">{formatIDR(g.saved)} / {formatIDR(g.target_amount)} · {Math.round(pct * 100)}%</Text>
                </>
              ) : <Text variant="titleM" color="saving">{formatIDR(g.saved)}</Text>}
              {g.gold_grams > 0 ? <Text variant="bodySm" color="inkMuted">+ {formatGold(g.gold_grams)}</Text> : null}
            </View>
          </Pressable>
        );
      })}
      <FormSheet visible={open} title="Target baru" fields={GOAL_FIELDS} onClose={() => setOpen(false)}
        onSubmit={async (v) => { await create.mutateAsync(v); setOpen(false); }} />
    </Screen>
  );
}
