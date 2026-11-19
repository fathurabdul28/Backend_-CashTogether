import React, { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Modal, Platform, Pressable, View } from 'react-native';
import { radius, useTheme } from '@/theme';
import { Button } from './Button';
import { TextField } from './Controls';
import { Text } from './Text';

export type Field = { key: string; label: string; numeric?: boolean; optional?: boolean; placeholder?: string };

/** Sheet form sederhana: kolom teks / nominal. Nilai numeric dikembalikan sebagai integer. */
export function FormSheet({ visible, title, fields, initial = {}, submitLabel = 'Simpan', onClose, onSubmit, error }: {
  visible: boolean; title: string; fields: Field[]; initial?: Record<string, string>; submitLabel?: string;
  onClose: () => void; onSubmit: (v: Record<string, string | number>) => Promise<void> | void; error?: string;
}) {
  const t = useTheme();
  const [vals, setVals] = useState<Record<string, string>>(initial);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  useEffect(() => { if (visible) { setVals(initial); setErr(''); } /* eslint-disable-next-line */ }, [visible]);
  const valid = fields.every((f) => f.optional || (vals[f.key] ?? '').trim().length > 0);

  const go = async () => {
    setBusy(true); setErr('');
    try {
      const out: Record<string, string | number> = {};
      fields.forEach((f) => { const v = (vals[f.key] ?? '').trim(); if (v) out[f.key] = f.numeric ? Number(v.replace(/\D/g, '')) : v; });
      await onSubmit(out);
    } catch (e: any) { setErr(e?.message ?? 'Ada yang tidak beres. Coba lagi sebentar ya.'); }
    finally { setBusy(false); }
  };

  return (
    <Modal transparent visible={visible} animationType="slide" onRequestClose={onClose}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1, backgroundColor: t.overlay, justifyContent: 'flex-end' }}>
        <Pressable style={{ flex: 1 }} onPress={onClose} accessibilityLabel="Tutup" />
        <View style={{ backgroundColor: t.surface, borderTopLeftRadius: radius.xl, borderTopRightRadius: radius.xl, padding: 20, gap: 14, width: '100%', maxWidth: 560, alignSelf: 'center' }}>
          <Text variant="titleL" accessibilityRole="header">{title}</Text>
          {fields.map((f) => (
            <TextField key={f.key} label={f.label} value={vals[f.key] ?? ''} placeholder={f.placeholder}
              keyboardType={f.numeric ? 'number-pad' : 'default'} maxLength={f.numeric ? 13 : 60}
              onChangeText={(v) => setVals({ ...vals, [f.key]: f.numeric ? v.replace(/\D/g, '') : v })} />
          ))}
          {(err || error) ? <Text variant="bodySm" color="danger">{err || error}</Text> : null}
          <Button label={submitLabel} size="lg" loading={busy} disabled={!valid} onPress={go} />
          <Button label="Batal" variant="ghost" onPress={onClose} />
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}
