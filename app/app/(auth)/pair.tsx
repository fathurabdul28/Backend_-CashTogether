import React, { useEffect, useState } from 'react';
import { Share, TextInput, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import * as Clipboard from 'expo-clipboard';
import { Copy, ShareNetwork } from 'phosphor-react-native';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Button } from '@/components/Button';
import { SegmentedControl, TextField } from '@/components/Controls';
import { Text } from '@/components/Text';
import { useAuth } from '@/features/auth';
import { useToast } from '@/features/toast';
import type { Household } from '@/features/types';
import { get, post, ApiError } from '@/lib/api';
import { radius, useTheme } from '@/theme';

function CodeBoxes({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const t = useTheme();
  const ref = React.useRef<any>(null);
  return (
    <View>
      <View style={{ flexDirection: 'row', gap: 8, justifyContent: 'center' }} onTouchEnd={() => ref.current?.focus()}>
        {Array.from({ length: 6 }).map((_, i) => (
          <View key={i} style={{ width: 48, height: 60, borderRadius: radius.md, backgroundColor: t.surfaceAlt, alignItems: 'center', justifyContent: 'center', borderWidth: 1.5, borderColor: value.length === i ? t.primary : 'transparent' }}>
            <Text variant="displayM">{value[i] ?? ''}</Text>
          </View>
        ))}
      </View>
      <TextInput ref={ref} accessibilityLabel="Kode undangan" value={value} autoCapitalize="characters" autoCorrect={false} maxLength={6}
        onChangeText={(v) => onChange(v.toUpperCase().replace(/[^A-Z0-9]/g, ''))}
        style={{ position: 'absolute', opacity: 0.02, width: '100%', height: 60 }} />
    </View>
  );
}

export default function Pair() {
  const t = useTheme();
  const user = useAuth((s) => s.user)!;
  const [mode, setMode] = useState<'create' | 'join'>('create');
  const [name, setName] = useState('Dompet Kita');
  const [created, setCreated] = useState<Household | null>(null);
  const [code, setCode] = useState('');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);

  const { data: live } = useQuery({
    queryKey: ['pair-household'], queryFn: () => get<Household>('/household'),
    enabled: !!created, refetchInterval: 3000,
  });
  const hh = live ?? created;
  const partner = hh?.members.find((m) => m.user_id !== user.id);

  const create = async () => {
    setBusy(true); setErr('');
    try { setCreated(await post<Household>('/household', { name })); }
    catch (e) { setErr(e instanceof ApiError ? e.message : 'Ada yang tidak beres. Coba lagi sebentar ya.'); }
    finally { setBusy(false); }
  };
  const join = async () => {
    setBusy(true); setErr('');
    try { await post('/household/join', { code }); await useAuth.getState().refresh(); }
    catch (e) { setErr(e instanceof ApiError ? e.message : 'Ada yang tidak beres. Coba lagi sebentar ya.'); }
    finally { setBusy(false); }
  };
  const start = () => useAuth.getState().refresh();
  useEffect(() => { setErr(''); }, [mode]);

  return (
    <Screen scroll edges={['top', 'bottom']}>
      <Header title="Hubungkan pasangan" back={false} />
      <View style={{ gap: 20, marginTop: 8 }}>
        {!created ? <SegmentedControl value={mode} onChange={setMode} options={[{ value: 'create', label: 'Buat dompet baru' }, { value: 'join', label: 'Gabung dompet' }]} /> : null}

        {mode === 'create' && !created && (
          <>
            <TextField label="Nama dompet" value={name} onChangeText={setName} maxLength={60} />
            {err ? <Text variant="bodySm" color="danger">{err}</Text> : null}
            <Button label="Buat dompet" size="lg" loading={busy} disabled={!name.trim()} onPress={create} />
          </>
        )}

        {created && (
          <>
            <View style={{ backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, padding: 20, gap: 16, alignItems: 'center' }}>
              <Text variant="overline" color="inkMuted">Kode undangan · {hh?.name}</Text>
              <Text variant="displayXL" accessibilityLabel={`Kode ${hh?.invite_code?.split('').join(' ')}`} style={{ letterSpacing: 6 }}>{hh?.invite_code ?? '——————'}</Text>
              <View style={{ flexDirection: 'row', gap: 8, width: '100%' }}>
                <Button style={{ flex: 1 }} variant="secondary" label="Salin" icon={<Copy size={18} color={t.ink} />}
                  onPress={async () => { await Clipboard.setStringAsync(hh?.invite_code ?? ''); useToast.getState().show('Kode disalin'); }} />
                <Button style={{ flex: 1 }} label="Bagikan" icon={<ShareNetwork size={18} color={t.onPrimary} />}
                  onPress={() => Share.share({ message: `Gabung ke ${hh?.name} di CashTogether. Kode: ${hh?.invite_code}` }).catch(() => {})} />
              </View>
              <Text variant="bodySm" color="inkMuted">Berlaku 48 jam · maksimal 2 orang per dompet</Text>
            </View>
            {partner ? (
              <View style={{ alignItems: 'center', gap: 12 }}>
                <Text variant="titleM" accessibilityLiveRegion="polite">{partner.name} sudah bergabung! Sekarang kalian bisa mencatat bersama.</Text>
                <Button label="Mulai" size="lg" onPress={start} style={{ alignSelf: 'stretch' }} />
              </View>
            ) : (
              <View style={{ gap: 12 }}>
                <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center' }}>
                  <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: t.warning }} />
                  <Text color="inkMuted">Menunggu pasangan bergabung…</Text>
                </View>
                <Button variant="ghost" label="Lewati dulu, undang nanti" onPress={start} />
              </View>
            )}
          </>
        )}

        {mode === 'join' && !created && (
          <>
            <Text color="inkMuted">Masukkan 6 karakter kode dari pasanganmu.</Text>
            <CodeBoxes value={code} onChange={setCode} />
            {err ? <Text variant="bodySm" color="danger" style={{ textAlign: 'center' }}>{err}</Text> : null}
            <Button label="Gabung" size="lg" loading={busy} disabled={code.length !== 6} onPress={join} />
          </>
        )}
        <Button variant="ghost" label="Keluar dari akun" onPress={() => useAuth.getState().logout()} />
      </View>
    </Screen>
  );
}
