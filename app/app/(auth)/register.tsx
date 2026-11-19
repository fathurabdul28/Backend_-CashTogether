import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useRouter } from 'expo-router';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Button } from '@/components/Button';
import { TextField } from '@/components/Controls';
import { Text } from '@/components/Text';
import { useAuth } from '@/features/auth';
import { ApiError } from '@/lib/api';
import { identity, type Gender } from '@/theme/identity';
import { radius, useTheme } from '@/theme';

export default function Register() {
  const t = useTheme();
  const router = useRouter();
  const register = useAuth((s) => s.register);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [gender, setGender] = useState<Gender | null>(null);
  const [err, setErr] = useState('');
  const [loading, setLoading] = useState(false);
  const valid = name.trim().length > 0 && /\S+@\S+\.\S+/.test(email) && password.length >= 8 && !!gender;
  const preview = gender ? identity[gender][t.scheme] : null;

  const submit = async () => {
    setLoading(true); setErr('');
    try { await register({ name: name.trim(), email: email.trim(), password, gender: gender! }); router.replace('/(auth)/pair'); }
    catch (e) { setErr(e instanceof ApiError ? e.message : 'Ada yang tidak beres. Coba lagi sebentar ya.'); }
    finally { setLoading(false); }
  };

  return (
    <Screen scroll style={preview ? { backgroundColor: preview.primarySoft } : undefined}>
      <Header title="Buat akun" />
      <View style={{ gap: 16, marginTop: 16 }}>
        <TextField label="Nama panggilan" value={name} onChangeText={setName} maxLength={60} />
        <TextField label="Email" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" />
        <TextField label="Kata sandi" value={password} onChangeText={setPassword} secure error={password && password.length < 8 ? 'Minimal 8 karakter' : undefined} />
        <View>
          <Text variant="label" color="inkMuted" style={{ marginBottom: 6 }}>Jenis kelamin</Text>
          <View style={{ flexDirection: 'row', gap: 12 }}>
            {(['male', 'female'] as Gender[]).map((g) => {
              const c = identity[g][t.scheme];
              const on = gender === g;
              return (
                <Pressable key={g} accessibilityRole="radio" accessibilityState={{ selected: on }} onPress={() => setGender(g)}
                  style={{ flex: 1, minHeight: 88, borderRadius: radius.lg, backgroundColor: t.surface, borderWidth: 2, borderColor: on ? c.primary : t.hairline, padding: 14, justifyContent: 'space-between' }}>
                  <View style={{ width: 24, height: 24, borderRadius: 12, backgroundColor: c.primary }} />
                  <Text variant="titleM">{g === 'male' ? 'Laki-laki' : 'Perempuan'}</Text>
                </Pressable>
              );
            })}
          </View>
          <Text variant="bodySm" color="inkMuted" style={{ marginTop: 6 }}>Menentukan warna aplikasimu. Bisa diubah nanti.</Text>
        </View>
        {err ? <Text variant="bodySm" color="danger">{err}</Text> : null}
        <Button label="Buat akun" size="lg" tint={preview?.primary} disabled={!valid} loading={loading} onPress={submit} />
      </View>
    </Screen>
  );
}
