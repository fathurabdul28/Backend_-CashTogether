import React, { useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowDownLeft, ArrowUpRight, Wallet, Eye, EyeSlash, Plus, TrendUp, PiggyBank } from 'phosphor-react-native';
import { Screen } from '@/components/Screen';
import { Text } from '@/components/Text';
import { Money } from '@/components/Money';
import { AvatarPair } from '@/components/Avatar';
import { MonthSwitcher } from '@/components/Controls';
import { ProgressBar, ProgressRing } from '@/components/Progress';
import { CategoryIcon } from '@/components/CategoryIcon';
import { TransactionRow } from '@/components/TransactionRow';
import { EmptyState, ErrorState, ListSkeleton, SectionHeader, Skeleton } from '@/components/Feedback';
import { useBudgets, useGoals, useSummary, useTransactions } from '@/features/queries';
import { useLookup } from '@/features/lookup';
import { useSettings } from '@/features/settings';
import { dayKey, dayLabel, daysLeftInMonth, greeting, monthKey } from '@/lib/date';
import { formatIDR, formatIDRCompact } from '@/lib/format';
import { radius, useTheme } from '@/theme';

function BalanceCard({ total, income, expense }: { total?: number; income?: number; expense?: number }) {
  const t = useTheme();
  const hidden = useSettings((s) => s.hideBalance);
  const set = useSettings((s) => s.setHideBalance);
  const fg = t.onCard;
  return (
    <View style={{ backgroundColor: t.card, borderRadius: radius.lg, padding: 20, paddingBottom: 22, overflow: 'hidden' }}>
      <View pointerEvents="none" style={{ position: 'absolute', inset: 0 } as any}>
        {Array.from({ length: 16 }).map((_, i) => <View key={i} style={{ height: 1, marginTop: 15, backgroundColor: fg, opacity: 0.06 }} />)}
      </View>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
        <Text variant="overline" style={{ color: fg, opacity: 0.7 }}>Total saldo</Text>
        <Pressable accessibilityRole="button" accessibilityLabel={hidden ? 'Tampilkan saldo' : 'Sembunyikan saldo'} onPress={() => set(!hidden)} hitSlop={12}>
          {hidden ? <EyeSlash size={20} color={fg} /> : <Eye size={20} color={fg} />}
        </Pressable>
      </View>
      <View style={{ marginVertical: 8 }}>
        {total === undefined ? <Skeleton w={200} h={40} /> : <Money value={total} variant="displayXL" colorBy="none" hidden={hidden} color="onCard" sign="none" style={{ color: fg }} />}
      </View>
      <View style={{ flexDirection: 'row', marginTop: 8 }}>
        {[{ l: 'Masuk bulan ini', v: income, I: ArrowDownLeft }, { l: 'Keluar bulan ini', v: expense, I: ArrowUpRight }].map((c, i) => (
          <View key={c.l} style={{ flex: 1, paddingLeft: i ? 16 : 0, borderLeftWidth: i ? 1 : 0, borderLeftColor: 'rgba(255,255,255,0.2)', gap: 2 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
              <c.I size={14} color={fg} /><Text variant="bodySm" style={{ color: fg, opacity: 0.8 }}>{c.l}</Text>
            </View>
            <Text variant="amount" style={{ color: fg }}>{c.v === undefined ? '…' : hidden ? 'Rp ••••' : formatIDR(c.v)}</Text>
          </View>
        ))}
      </View>
    </View>
  );
}

export default function Home() {
  const t = useTheme();
  const router = useRouter();
  const qc = useQueryClient();
  const L = useLookup();
  const [month, setMonth] = useState(new Date());
  const mk = monthKey(month);
  const summary = useSummary(mk);
  const budgets = useBudgets(mk);
  const goals = useGoals();
  const txs = useTransactions(mk);

  const bTot = budgets.data;
  const ratio = bTot && bTot.total_limit ? bTot.total_spent / bTot.total_limit : 0;
  const barTint = ratio > 1 ? t.expense : ratio >= 0.75 ? t.warning : t.primary;
  const recent = (txs.data ?? []).slice(0, 5);

  const actions = [
    { l: 'Pemasukan', I: ArrowDownLeft, bg: t.incomeSoft, fg: t.income, go: () => router.push('/transaction/new?type=income') },
    { l: 'Pengeluaran', I: ArrowUpRight, bg: t.expenseSoft, fg: t.expense, go: () => router.push('/transaction/new?type=expense') },
    { l: 'Anggaran', I: Wallet, bg: t.savingSoft, fg: t.saving, go: () => router.push('/budget') },
    { l: 'Tabung', I: PiggyBank, bg: t.warningSoft, fg: t.warning, go: () => router.push('/(tabs)/savings') },
  ];

  return (
    <Screen scroll bottomInset={40} onRefresh={() => qc.invalidateQueries()}>
      <View style={{ minHeight: 56, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
        <View>
          <Text variant="bodySm" color="inkMuted">{greeting()}</Text>
          <Text variant="titleL">{L.me?.name}</Text>
        </View>
        <Pressable accessibilityRole="button" accessibilityLabel="Pengaturan" onPress={() => router.push('/settings')} hitSlop={8}>
          <AvatarPair users={L.members} />
        </Pressable>
      </View>

      <BalanceCard total={summary.data?.balance} income={summary.data?.income} expense={summary.data?.expense} />
      <View style={{ marginVertical: 12 }}><MonthSwitcher value={month} onChange={setMonth} /></View>

      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 28, marginTop: 4 }}>
        {actions.map(({ l, I, go, bg, fg }) => (
          <Pressable key={l} accessibilityRole="button" accessibilityLabel={l} onPress={go} style={{ flex: 1, alignItems: 'center', gap: 6, minHeight: 44 }}>
            <View style={{ width: 52, height: 52, borderRadius: radius.md, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}><I size={24} color={fg} weight="bold" /></View>
            <Text variant="label" numberOfLines={1} maxFontSizeMultiplier={1.2} style={{ fontSize: 12 }}>{l}</Text>
          </Pressable>
        ))}
      </View>

      <SectionHeader title="Anggaran" actionLabel="Atur" onAction={() => router.push('/budget')} />
      <View style={{ backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, padding: 16, gap: 10, marginBottom: 28 }}>
        {budgets.isLoading ? <Skeleton h={40} /> : !bTot || bTot.items.length === 0 ? (
          <Text color="inkMuted">Belum ada anggaran bulan ini. Atur dulu supaya pengeluaran lebih terkontrol.</Text>
        ) : (
          <>
            <Text variant="body">Terpakai <Text variant="titleM">{formatIDR(bTot.total_spent)}</Text> dari {formatIDR(bTot.total_limit)}</Text>
            <ProgressBar value={ratio} tint={barTint} />
            <Text variant="bodySm" color="inkMuted">
              {ratio > 1 ? `Lewat ${formatIDR(bTot.total_spent - bTot.total_limit)}. Tidak apa-apa, coba atur ulang minggu depan.` : `Sisa ${formatIDR(bTot.total_limit - bTot.total_spent)} · ${daysLeftInMonth()} hari lagi`}
            </Text>
            {bTot.items.slice().sort((a, b) => b.spent - a.spent).slice(0, 3).map((b) => {
              const c = L.category(b.category_id);
              return (
                <View key={b.id} style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                  <CategoryIcon icon={c?.icon ?? 'DotsThree'} color={c?.color ?? 'stone'} size={36} />
                  <View style={{ flex: 1, gap: 4 }}>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                      <Text variant="label">{c?.name}</Text>
                      <Text variant="bodySm" color="inkMuted">{formatIDRCompact(b.spent)} / {formatIDRCompact(b.limit_amount)}</Text>
                    </View>
                    <ProgressBar value={b.limit_amount ? b.spent / b.limit_amount : 0} height={6} tint={b.spent > b.limit_amount ? t.expense : t.primary} />
                  </View>
                </View>
              );
            })}
          </>
        )}
      </View>

      <SectionHeader title="Tabungan" actionLabel="Lihat" onAction={() => router.push('/(tabs)/savings')} />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -20, marginBottom: 28 }} contentContainerStyle={{ paddingHorizontal: 20, gap: 12 }}>
        {(goals.data ?? []).map((g) => (
          <Pressable key={g.id} accessibilityRole="button" onPress={() => router.push(`/savings/${g.id}`)}
            style={{ width: 160, backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, padding: 14, gap: 10 }}>
            <ProgressRing value={g.saved / g.target_amount} size={48} stroke={5}><Text variant="label" style={{ fontSize: 11 }}>{Math.round((g.saved / g.target_amount) * 100)}%</Text></ProgressRing>
            <Text variant="titleM" numberOfLines={1}>{g.name}</Text>
            <Text variant="bodySm" color="inkMuted">{formatIDRCompact(g.saved)} / {formatIDRCompact(g.target_amount)}</Text>
          </Pressable>
        ))}
        <Pressable accessibilityRole="button" accessibilityLabel="Target baru" onPress={() => router.push('/(tabs)/savings')}
          style={{ width: 160, borderRadius: radius.lg, borderWidth: 1.5, borderStyle: 'dashed', borderColor: t.hairline, alignItems: 'center', justifyContent: 'center', gap: 6, minHeight: 130 }}>
          <Plus size={22} color={t.inkMuted} /><Text variant="label" color="inkMuted">Target baru</Text>
        </Pressable>
      </ScrollView>

      <SectionHeader title="Transaksi terakhir" actionLabel="Lihat semua" onAction={() => router.push('/(tabs)/transactions')} />
      {txs.isLoading ? <ListSkeleton rows={4} /> : txs.isError ? <ErrorState onRetry={() => txs.refetch()} /> : recent.length === 0 ? (
        <EmptyState icon={<TrendUp size={48} color={t.inkFaint} />} title="Belum ada catatan bulan ini." actionLabel="Catat sekarang" onAction={() => router.push('/transaction/new')} />
      ) : recent.map((x, i) => (
        <View key={x.id}>
          {(i === 0 || dayKey(recent[i - 1].occurred_at) !== dayKey(x.occurred_at)) ? <Text variant="label" color="inkMuted" style={{ marginTop: i ? 12 : 0, marginBottom: 4 }}>{dayLabel(x.occurred_at)}</Text> : null}
          <TransactionRow tx={x} category={L.category(x.category_id)} account={L.account(x.account_id)} onPress={() => router.push(`/transaction/${x.id}`)} />
        </View>
      ))}
    </Screen>
  );
}
