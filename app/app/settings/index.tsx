import React, { useEffect, useState } from 'react';
import { Linking, Pressable, Switch, View } from 'react-native';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { CaretRight } from 'phosphor-react-native';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Text } from '@/components/Text';
import { Avatar } from '@/components/Avatar';
import { Button } from '@/components/Button';
import { SegmentedControl } from '@/components/Controls';
import { useAuth } from '@/features/auth';
import { useSettings } from '@/features/settings';
import { useLookup } from '@/features/lookup';
import { del, get, patch, post } from '@/lib/api';
import { radius, useTheme } from '@/theme';
import type { Gender } from '@/theme/identity';

type WaStatus = { linked: boolean; phone: string | null; bot_number: string | null; code: string | null; expires_at: string | null };

function WhatsAppGroup({ Group, Row }: { Group: any; Row: any }) {
  const t = useTheme();
  const qc = useQueryClient();
  const [waiting, setWaiting] = useState(false);
  const q = useQuery({ queryKey: ['whatsapp'], queryFn: () => get<WaStatus>('/whatsapp'), refetchInterval: waiting ? 3000 : false });
  const s = q.data;
  useEffect(() => { if (s?.linked) setWaiting(false); else if (s?.code) setWaiting(true); }, [s?.linked, s?.code]);

  const makeCode = async () => { await post('/whatsapp/link-code'); qc.invalidateQueries({ queryKey: ['whatsapp'] }); };
  const unlink = async () => { await del('/whatsapp'); qc.invalidateQueries({ queryKey: ['whatsapp'] }); };
  const openWa = () => {
    if (!s?.bot_number || !s.code) return;
    Linking.openURL(`https://wa.me/${s.bot_number}?text=${encodeURIComponent(`HUBUNGKAN ${s.code}`)}`).catch(() => {});
  };

  return (
    <Group title="WhatsApp">
      {s?.linked ? (
        <>
          <Row label="Terhubung" right={<Text color="income">{s.phone}</Text>} />
          <View style={{ paddingVertical: 10 }}><Text variant="bodySm" color="inkMuted">Kirim pesan seperti "pengeluaran 34 ribu untuk makan" ke bot, dan catatannya masuk otomatis.</Text></View>
          <Row label="Putuskan WhatsApp" onPress={unlink} last />
        </>
      ) : s?.code ? (
        <View style={{ paddingVertical: 14, gap: 10 }}>
          <Text variant="bodySm" color="inkMuted">Kirim pesan ini ke nomor bot{s.bot_number ? ` (${s.bot_number})` : ''} dalam 15 menit:</Text>
          <View style={{ backgroundColor: t.surfaceAlt, borderRadius: radius.md, padding: 14, alignItems: 'center' }}>
            <Text variant="displayM" style={{ letterSpacing: 2 }}>HUBUNGKAN {s.code}</Text>
          </View>
          {s.bot_number ? <Button label="Buka WhatsApp" onPress={openWa} /> : <Text variant="bodySm" color="danger">Nomor bot belum diatur di server (FONNTE_BOT_NUMBER).</Text>}
          <Text variant="bodySm" color="inkMuted">Menunggu pesanmu…</Text>
        </View>
      ) : (
        <View style={{ paddingVertical: 14, gap: 10 }}>
          <Text variant="bodySm" color="inkMuted">Catat pengeluaran lewat chat WhatsApp, tanpa membuka app.</Text>
          <Button label="Hubungkan WhatsApp" onPress={makeCode} />
        </View>
      )}
    </Group>
  );
}

export default function Settings() {
  const t = useTheme();
  const router = useRouter();
  const L = useLookup();
  const s = useSettings();
  const logout = useAuth((x) => x.logout);

  const Group = ({ title, children }: { title: string; children: React.ReactNode }) => (
    <View style={{ marginBottom: 20 }}>
      <Text variant="overline" color="inkMuted" style={{ marginBottom: 8 }}>{title}</Text>
      <View style={{ backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, paddingHorizontal: 16 }}>{children}</View>
    </View>
  );
  const Row = ({ label, onPress, right, last }: { label: string; onPress?: () => void; right?: React.ReactNode; last?: boolean }) => (
    <Pressable accessibilityRole={onPress ? 'button' : undefined} onPress={onPress}
      style={{ minHeight: 52, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: last ? 0 : 1, borderBottomColor: t.hairline, gap: 12 }}>
      <Text>{label}</Text>{right ?? (onPress ? <CaretRight size={18} color={t.inkFaint} /> : null)}
    </Pressable>
  );

  return (
    <Screen scroll bottomInset={40}>
      <Header title="Pengaturan" />
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, marginVertical: 12 }}>
        <Avatar user={L.meMember} size={52} />
        <View><Text variant="titleL">{L.me?.name}</Text><Text variant="bodySm" color="inkMuted">{L.me?.email}</Text></View>
      </View>

      <Group title="Dompet bersama">
        <Row label={L.household?.name ?? 'Dompet'} right={<Text color="inkMuted">{L.members.length}/2 orang</Text>} />
        {L.members.map((m) => <Row key={m.user_id} label={m.name} right={<Avatar user={m} size={28} />} last={!L.household?.invite_code && m === L.members[L.members.length - 1]} />)}
        {L.household?.invite_code ? <Row label="Kode undangan" right={<Text variant="titleM" style={{ letterSpacing: 3 }}>{L.household.invite_code}</Text>} last /> : null}
      </Group>
      <WhatsAppGroup Group={Group} Row={Row} />
      <Group title="Data">
        <Row label="Akun & sumber dana" onPress={() => router.push('/accounts')} />
        <Row label="Anggaran" onPress={() => router.push('/budget')} last />
      </Group>
      <Group title="Tampilan">
        <View style={{ paddingVertical: 10 }}>
          <SegmentedControl value={s.theme} onChange={s.setTheme} options={[{ value: 'light', label: 'Terang' }, { value: 'dark', label: 'Gelap' }, { value: 'system', label: 'Sistem' }]} />
        </View>
        <Row label="Sembunyikan saldo" right={<Switch value={s.hideBalance} onValueChange={s.setHideBalance} trackColor={{ true: t.primary }} />} />
        <View style={{ paddingVertical: 10, gap: 8 }}>
          <Text color="inkMuted" variant="bodySm">Warna identitas</Text>
          <SegmentedControl value={(L.me?.identity_override ?? L.me?.gender ?? 'male') as Gender}
            onChange={async (g) => { await patch('/me', { identity_override: g }); useAuth.getState().refresh(); }}
            options={[{ value: 'male', label: 'Biru' }, { value: 'female', label: 'Pink' }]} />
        </View>
      </Group>
      <Button variant="ghost" label="Keluar" onPress={logout} style={{ marginTop: 4 }} />
      <Text variant="bodySm" color="inkFaint" style={{ textAlign: 'center', marginTop: 8 }}>CashTogether 1.0.0</Text>
    </Screen>
  );
}
