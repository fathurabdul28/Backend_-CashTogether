import React, { useMemo, useState } from 'react';
import { ScrollView, SectionList, TextInput, View } from 'react-native';
import { useRouter } from 'expo-router';
import { DownloadSimple, MagnifyingGlass, Receipt } from 'phosphor-react-native';
import { exportExcel } from '@/features/export';
import { Text } from '@/components/Text';
import { Screen } from '@/components/Screen';
import { FilterChip, MonthSwitcher } from '@/components/Controls';
import { IconButton } from '@/components/Button';
import { TransactionRow } from '@/components/TransactionRow';
import { EmptyState, ErrorState, ListSkeleton } from '@/components/Feedback';
import { useTransactions } from '@/features/queries';
import { useLookup } from '@/features/lookup';
import type { Tx } from '@/features/types';
import { dayKey, dayLabel } from '@/lib/date';
import { usePeriod } from '@/features/period';
import { formatIDRCompact } from '@/lib/format';
import { useTheme } from '@/theme';

type Filter = 'all' | 'income' | 'expense';

export default function History() {
  const t = useTheme();
  const router = useRouter();
  const L = useLookup();
  const { key: mk, word } = usePeriod();
  const [filter, setFilter] = useState<Filter>('all');
  const [search, setSearch] = useState('');
  const [searching, setSearching] = useState(false);
  const q = useTransactions(mk);

  const rows = useMemo(() => (q.data ?? []).filter((x) => {
    if (filter !== 'all' && x.type !== filter) return false;
    if (search) {
      const s = search.toLowerCase();
      return (x.note ?? '').toLowerCase().includes(s) || (L.category(x.category_id)?.name ?? '').toLowerCase().includes(s) || String(x.amount).includes(s.replace(/\D/g, '') || '§');
    }
    return true;
  }), [q.data, filter, search, L]);

  const sections = useMemo(() => {
    const m = new Map<string, Tx[]>();
    rows.forEach((x) => { const k = dayKey(x.occurred_at); m.set(k, [...(m.get(k) ?? []), x]); });
    return [...m.values()].map((data) => ({ title: dayLabel(data[0].occurred_at), net: data.reduce((s, x) => s + (x.type === 'income' ? x.amount : -x.amount), 0), data }));
  }, [rows]);

  const inc = rows.filter((x) => x.type === 'income').reduce((s, x) => s + x.amount, 0);
  const exp = rows.filter((x) => x.type === 'expense').reduce((s, x) => s + x.amount, 0);
  const chips: { k: Filter; l: string }[] = [{ k: 'all', l: 'Semua' }, { k: 'income', l: 'Masuk' }, { k: 'expense', l: 'Keluar' }];

  return (
    <Screen>
      <View style={{ minHeight: 56, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <Text variant="displayM" accessibilityRole="header">Riwayat</Text>
        <View style={{ flexDirection: 'row' }}>
          <IconButton label="Ekspor ke Excel" onPress={() => exportExcel({ month: mk })} icon={<DownloadSimple size={24} color={t.ink} />} />
          <IconButton label="Cari" onPress={() => { setSearching(!searching); setSearch(''); }} icon={<MagnifyingGlass size={24} color={t.ink} />} />
        </View>
      </View>
      {searching ? (
        <TextInput autoFocus value={search} onChangeText={setSearch} placeholder="Cari catatan, kategori, nominal" placeholderTextColor={t.inkFaint} accessibilityLabel="Cari"
          style={{ height: 44, borderRadius: 12, paddingHorizontal: 14, backgroundColor: t.surfaceAlt, color: t.ink, fontFamily: 'PlusJakartaSans_500Medium', marginBottom: 8, outlineStyle: 'none' } as any} />
      ) : null}
      <MonthSwitcher />
      <View style={{ height: 46, marginTop: 8 }}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -20 }} contentContainerStyle={{ paddingHorizontal: 20, alignItems: 'center' }}>
          {chips.map((c) => <FilterChip key={c.k} label={c.l} active={filter === c.k} onPress={() => setFilter(c.k)} />)}
        </ScrollView>
      </View>
      <Text variant="bodySm" color="inkMuted" style={{ marginBottom: 8 }}>
        Masuk <Text variant="bodySm" color="income">{formatIDRCompact(inc)}</Text> · Keluar <Text variant="bodySm" color="expense">{formatIDRCompact(exp)}</Text> · Selisih <Text variant="bodySm" color={inc - exp >= 0 ? 'income' : 'expense'}>{inc - exp >= 0 ? '+' : '−'}{formatIDRCompact(inc - exp)}</Text>
      </Text>
      {q.isLoading ? <ListSkeleton /> : q.isError ? <ErrorState onRetry={() => q.refetch()} /> : (
        <SectionList
          sections={sections} keyExtractor={(x) => String(x.id)} stickySectionHeadersEnabled showsVerticalScrollIndicator={false}
          refreshing={q.isRefetching} onRefresh={() => q.refetch()} contentContainerStyle={{ paddingBottom: 40 }}
          ListEmptyComponent={<EmptyState icon={<Receipt size={48} color={t.inkFaint} />} title={`Belum ada catatan ${word}.`} actionLabel="Catat sekarang" onAction={() => router.push('/transaction/new')} />}
          renderSectionHeader={({ section }) => (
            <View style={{ backgroundColor: t.bg, flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 8 }}>
              <Text variant="label" color="inkMuted">{section.title}</Text>
              <Text variant="label" color="inkMuted">{section.net >= 0 ? '+' : '−'}{formatIDRCompact(section.net)}</Text>
            </View>
          )}
          renderItem={({ item }) => (
            <TransactionRow tx={item} category={L.category(item.category_id)} account={L.account(item.account_id)} onPress={() => router.push(`/transaction/${item.id}`)} />
          )}
        />
      )}
    </Screen>
  );
}
