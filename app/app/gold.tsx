import React, { useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Text } from '@/components/Text';
import { TextField } from '@/components/Controls';
import { Button } from '@/components/Button';
import { ErrorState, Skeleton } from '@/components/Feedback';
import { useGoals, useGoldPrice } from '@/features/queries';
import { formatGold, formatIDR } from '@/lib/format';
import { radius, useTheme } from '@/theme';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';

const Card = ({ title, children }: { title: string; children: React.ReactNode }) => {
  const t = useTheme();
  return (
    <View style={{ backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, padding: 16, gap: 10, marginBottom: 16 }}>
      <Text variant="titleM">{title}</Text>
      {children}
    </View>
  );
};

/** "2026-11-25T03:22:50Z" → "25 Nov 2026, 10.22"; sumber harian hanya punya tanggal */
function asOfLabel(s: string | null) {
  if (!s) return '-';
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s;
  return format(d, s.length > 10 ? 'd MMM yyyy, HH.mm' : 'd MMM yyyy', { locale: id });
}

export default function Gold() {
  const router = useRouter();
  const q = useGoldPrice(true);
  const goals = useGoals();
  const price = q.data?.price_per_gram ?? null;
  const withGold = (goals.data ?? []).filter((g) => g.gold_grams > 0);
  const grams = withGold.reduce((s, g) => s + g.gold_grams, 0);

  const [rupiah, setRupiah] = useState('');
  const [gram, setGram] = useState('');
  const fromRupiah = price && rupiah ? Number(rupiah) / price : null;
  const fromGram = price && gram ? Number(gram.replace(',', '.')) * price : null;

  return (
    <Screen scroll bottomInset={40} onRefresh={() => q.refetch()}>
      <Header title="Emas" />
      {q.isLoading ? <Skeleton h={140} r={16} /> : q.isError ? <ErrorState onRetry={() => q.refetch()} message={q.error?.message} /> : (
        <Card title="Harga emas per gram">
          {price ? (
            <>
              <Text variant="displayL" color="saving">{formatIDR(price)}</Text>
              <Text variant="bodySm" color="inkMuted">Per {asOfLabel(q.data!.as_of)} · {q.data!.source}</Text>
              {q.data!.stale ? <Text variant="bodySm" color="danger">Sumber harga sedang bermasalah. Ini harga terakhir yang berhasil diambil.</Text> : null}
              <Text variant="bodySm" color="inkMuted">Ini harga spot dunia, bukan harga jual Antam atau Pegadaian. Harga beli di toko biasanya lebih tinggi.</Text>
            </>
          ) : <Text color="inkMuted">Harga emas belum bisa diambil. Coba lagi sebentar.</Text>}
          <Button variant="secondary" label="Perbarui" loading={q.isFetching} onPress={() => q.refetch()} />
        </Card>
      )}

      <Card title="Emas saya di tabungan">
        {withGold.length === 0 ? <Text color="inkMuted">Belum ada emas di tabungan. Buka salah satu tabungan lalu pilih “Konversi ke emas”.</Text> : (
          <>
            <Text variant="titleL">{formatGold(grams)}{price ? ` (≈ ${formatIDR(grams * price)})` : ''}</Text>
            {withGold.map((g) => (
              <Button key={g.id} variant="ghost" label={`${g.name}: ${formatGold(g.gold_grams)}${price ? ` (≈ ${formatIDR(g.gold_grams * price)})` : ''}`} onPress={() => router.push(`/savings/${g.id}`)} />
            ))}
          </>
        )}
      </Card>

      {price ? (
        <Card title="Kalkulator">
          <TextField label="Rupiah" value={rupiah} keyboardType="number-pad" maxLength={13} onChangeText={(v) => setRupiah(v.replace(/\D/g, ''))} />
          <Text variant="bodySm" color="inkMuted">{fromRupiah != null ? `${formatIDR(Number(rupiah))} ≈ ${formatGold(fromRupiah)}` : 'Isi rupiah untuk melihat perkiraan gramnya.'}</Text>
          <TextField label="Gram emas" value={gram} keyboardType="decimal-pad" maxLength={8} onChangeText={(v) => setGram(v.replace(/[^\d.,]/g, ''))} />
          <Text variant="bodySm" color="inkMuted">{fromGram != null && Number.isFinite(fromGram) ? `${gram} gram ≈ ${formatIDR(fromGram)}` : 'Isi gram untuk melihat perkiraan rupiahnya.'}</Text>
        </Card>
      ) : null}
    </Screen>
  );
}
