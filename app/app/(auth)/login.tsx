import React, { useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { Screen } from '@/components/Screen';
import { Header } from '@/components/AuthHeader';
import { Button } from '@/components/Button';
import { TextField } from '@/components/Controls';
import { useAuth } from '@/features/auth';
import { ApiError } from '@/lib/api';

export default function Login() {
  const router = useRouter();
  const login = useAuth((s) => s.login);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [err, setErr] = useState('');
  const [loading, setLoading] = useState(false);
  const valid = /\S+@\S+\.\S+/.test(email) && password.length >= 8;

  const submit = async () => {
    setLoading(true); setErr('');
    try { await login(email.trim(), password); router.replace('/(tabs)'); }
    catch (e) { setErr(e instanceof ApiError ? e.message : 'Ada yang tidak beres. Coba lagi sebentar ya.'); }
    finally { setLoading(false); }
  };
  return (
    <Screen scroll>
      <Header title="Masuk" />
      <View style={{ gap: 16, marginTop: 16 }}>
        <TextField label="Email" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" />
        <TextField label="Kata sandi" value={password} onChangeText={setPassword} secure autoComplete="password" error={err} onSubmitEditing={() => valid && submit()} />
        <Button label="Masuk" size="lg" disabled={!valid} loading={loading} onPress={submit} />
      </View>
    </Screen>
  );
}
