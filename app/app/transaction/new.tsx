import React, { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, Switch, TextInput, View, useWindowDimensions } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { ArrowsClockwise, CalendarBlank, NotePencil, Wallet } from 'phosphor-react-native';
import { Screen } from '@/components/Screen';
import { Text } from '@/components/Text';
import { Button, IconButton } from '@/components/Button';
import { NumPad, SegmentedControl } from '@/components/Controls';
import { CategoryIcon } from '@/components/CategoryIcon';
import { useLookup } from '@/features/lookup';
import { useDeleteTransaction, useSaveTransaction } from '@/features/queries';
import { useToast } from '@/features/toast';
import type { TxType } from '@/features/types';
import { ApiError } from '@/lib/api';
import { formatIDR } from '@/lib/format';
import { pickedDateLabel } from '@/lib/date';
import { DatePickerSheet } from '@/components/DatePickerSheet';
import { isToday } from 'date-fns';
import { radius, useTheme } from '@/theme';
import { X } from 'phosphor-react-native';

const TYPES = [{ value: 'expense', label: 'Keluar' }, { value: 'income', label: 'Masuk' }] as const;

function Row({ icon, label, children, onPress, h = 52 }: { icon: React.ReactNode; label: string; children?: React.ReactNode; onPress?: () => void; h?: number }) {
  const t = useTheme();
  return (
    <Pressable accessibilityRole={onPress ? 'button' : undefined} accessibilityLabel={label} onPress={onPress}
      style={{ minHeight: h, flexDirection: 'row', alignItems: 'center', gap: 12, borderBottomWidth: 1, borderBottomColor: t.hairline }}>
      {icon}<Text variant="body" color="inkMuted" style={{ width: 92 }}>{label}</Text>
      <View style={{ flex: 1, alignItems: 'flex-end' }}>{children}</View>
    </Pressable>
  );
}

export default function NewTransaction() {
  const t = useTheme();
  const router = useRouter();
  const { height } = useWindowDimensions();
  const compact = height < 900;
  const params = useLocalSearchParams<{ type?: TxType }>();
  const L = useLookup();
  const save = useSaveTransaction();
  const del = useDeleteTransaction();

  const [type, setType] = useState<TxType>(params.type ?? 'expense');
  const [digits, setDigits] = useState('');
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [accountId, setAccountId] = useState<number | null>(null);
  const [date, setDate] = useState(() => new Date());
  const [pickDate, setPickDate] = useState(false);
  const [note, setNote] = useState('');
  const [repeat, setRepeat] = useState(false);
  const [pickAccount, setPickAccount] = useState(false);
  const [err, setErr] = useState('');

  const tint = type === 'income' ? t.income : t.expense;
  const amount = Number(digits || '0');
  const cats = useMemo(() => L.categories.filter((c) => c.kind === type), [L.categories, type]);
  useEffect(() => { setCategoryId(null); setErr(''); if (type !== 'income') setRepeat(false); }, [type]);
  useEffect(() => { if (accountId == null && L.accounts[0]) setAccountId(L.accounts[0].id); }, [L.accounts, accountId]);

  const press = (k: string) => setDigits((d) => (d + k).replace(/^0+/, '').slice(0, 13));
  const canSave = amount > 0 && categoryId != null && accountId != null;

  const submit = async () => {
    if (amount <= 0) return setErr('Masukkan nominal dulu');
    if (!categoryId) return setErr('Pilih kategori dulu');
    // hari ini: pakai jam sekarang; tanggal lain: jam 12 siang supaya aman dari pergeseran zona waktu
    const when = isToday(date) ? new Date() : new Date(date.getFullYear(), date.getMonth(), date.getDate(), 12, 0, 0);
    try {
      const tx = await save.mutateAsync({
        type, amount, account_id: accountId, occurred_at: when.toISOString(), note: note.trim() || null, category_id: categoryId, ...(repeat ? { repeat_monthly: true } : {}),
      });
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
      const name = L.category(categoryId)?.name;
      router.back();
      useToast.getState().show(`Tersimpan · ${name} ${formatIDR(amount)}${repeat ? ' · diulang tiap bulan' : ''}`, { label: 'Urungkan', run: () => del.mutate(tx.id) });
    } catch (e) { setErr(e instanceof ApiError ? e.message : 'Ada yang tidak beres. Coba lagi sebentar ya.'); }
  };

  const AccountPicker = () => (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, paddingVertical: 8 }}>
      {L.accounts.map((a) => (
        <Pressable key={a.id} onPress={() => { setAccountId(a.id); setPickAccount(false); }} accessibilityRole="button"
          style={{ paddingHorizontal: 12, minHeight: 36, justifyContent: 'center', borderRadius: 99, backgroundColor: a.id === accountId ? t.primary : t.surfaceAlt }}>
          <Text variant="label" style={{ color: a.id === accountId ? t.onPrimary : t.ink }}>{a.name}</Text>
        </Pressable>
      ))}
    </View>
  );

  return (
    <Screen edges={['bottom']} style={{ paddingTop: 8 }}>
      <View style={{ alignItems: 'center', paddingTop: 8 }}><View style={{ width: 36, height: 4, borderRadius: 2, backgroundColor: t.hairline }} /></View>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 4 }}>
        <Text variant="titleL" accessibilityRole="header">Catat</Text>
        <IconButton label="Tutup" onPress={() => router.back()} icon={<X size={22} color={t.ink} />} style={{ marginRight: -12 }} />
      </View>
      <SegmentedControl options={TYPES.map((x) => ({ ...x }))} value={type} onChange={setType} tint={tint} />

      <ScrollView style={{ flex: 1 }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
        <View style={{ alignItems: 'center', paddingVertical: compact ? 6 : 18, flexDirection: 'row', justifyContent: 'center', gap: 6 }} accessibilityLabel={`Nominal ${amount} rupiah`}>
          <Text variant="titleL" color="inkMuted">Rp</Text>
          <Text variant="displayL" display style={{ color: amount ? tint : t.inkFaint }}>{(amount || 0).toLocaleString('id-ID')}</Text>
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -20 }} contentContainerStyle={{ paddingHorizontal: 20 }}>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', height: compact ? 158 : 176, width: Math.ceil(cats.length / 2) * 76, gap: 4, alignContent: 'flex-start' }}>
              {cats.map((c) => {
                const on = c.id === categoryId;
                return (
                  <Pressable key={c.id} accessibilityRole="button" accessibilityState={{ selected: on }} accessibilityLabel={c.name}
                    onPress={() => { setCategoryId(c.id); setErr(''); Haptics.selectionAsync().catch(() => {}); }} style={{ width: 72, alignItems: 'center', gap: 4, paddingVertical: 4 }}>
                    <View style={{ borderRadius: radius.md + 2, borderWidth: 2, borderColor: on ? tint : 'transparent', padding: 1 }}><CategoryIcon icon={c.icon} color={c.color} size={compact ? 40 : 44} /></View>
                    <Text variant="bodySm" numberOfLines={1} style={{ fontFamily: on ? 'PlusJakartaSans_700Bold' : undefined, textAlign: 'center' }}>{c.name.split(' ')[0]}</Text>
                  </Pressable>
                );
              })}
            </View>
          </ScrollView>

        <View style={{ marginTop: 4 }}>
          <Row h={compact ? 46 : 52} icon={<CalendarBlank size={20} color={t.inkMuted} />} label="Tanggal" onPress={() => setPickDate(true)}>
            <Text variant="body" style={{ color: t.primary }} accessibilityHint="Ketuk untuk memilih tanggal">{pickedDateLabel(date)}</Text>
          </Row>
          <Row h={compact ? 46 : 52} icon={<Wallet size={20} color={t.inkMuted} />} label={type === 'income' ? 'Ke akun' : 'Dari akun'} onPress={() => setPickAccount(!pickAccount)}>
            <Text variant="body">{L.account(accountId)?.name ?? '-'}</Text>
          </Row>
          {pickAccount ? <AccountPicker /> : null}
          {type === 'income' ? (
            <Row h={compact ? 46 : 52} icon={<ArrowsClockwise size={20} color={t.inkMuted} />} label="Tiap bulan">
              <Switch accessibilityLabel="Ulangi tiap bulan (mis. gaji)" value={repeat} onValueChange={setRepeat} trackColor={{ true: t.primary }} />
            </Row>
          ) : null}
          <Row h={compact ? 46 : 52} icon={<NotePencil size={20} color={t.inkMuted} />} label="Catatan">
            <TextInput accessibilityLabel="Catatan" value={note} onChangeText={setNote} placeholder="Opsional" placeholderTextColor={t.inkFaint} maxLength={200}
              style={{ color: t.ink, fontFamily: 'PlusJakartaSans_500Medium', fontSize: 15, textAlign: 'right', width: '100%', outlineStyle: 'none' } as any} />
          </Row>
        </View>
      </ScrollView>

      {err ? <Text variant="bodySm" color="danger" style={{ textAlign: 'center', marginBottom: 6 }} accessibilityLiveRegion="polite">{err}</Text> : null}
      <Button label="Simpan" size="lg" tint={tint} loading={save.isPending} disabled={!canSave && !err ? false : false} onPress={submit} style={{ marginBottom: 8, opacity: canSave ? 1 : 0.5 }} />
      <NumPad keyHeight={compact ? 44 : 56} onKey={press} onBackspace={() => setDigits((d) => d.slice(0, -1))} onClear={() => setDigits('')} />
      <DatePickerSheet visible={pickDate} value={date} onClose={() => setPickDate(false)} onPick={setDate} />
    </Screen>
  );
}
