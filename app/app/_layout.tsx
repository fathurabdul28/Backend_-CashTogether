import React, { useEffect } from 'react';
import { LogBox, View } from 'react-native';
import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import * as SplashScreen from 'expo-splash-screen';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useFonts } from 'expo-font';
import { PlusJakartaSans_400Regular, PlusJakartaSans_500Medium, PlusJakartaSans_600SemiBold, PlusJakartaSans_700Bold } from '@expo-google-fonts/plus-jakarta-sans';
import { BricolageGrotesque_600SemiBold, BricolageGrotesque_700Bold } from '@expo-google-fonts/bricolage-grotesque';
import { ThemeProvider, useTheme } from '@/theme';
import { useAuth } from '@/features/auth';
import { useSettings } from '@/features/settings';
import { Toast } from '@/components/Feedback';

// react-native-svg (web) meneruskan accessible={false} ke DOM: peringatan dev tak berbahaya dari library
LogBox.ignoreLogs(['non-boolean attribute', 'useNativeDriver']);
SplashScreen.preventAutoHideAsync().catch(() => {});
const qc = new QueryClient({ defaultOptions: { queries: { retry: 1, staleTime: 15_000 } } });

function Guard({ children }: { children: React.ReactNode }) {
  const { user, ready } = useAuth();
  const segments = useSegments();
  const router = useRouter();
  useEffect(() => {
    if (!ready) return;
    const inAuth = segments[0] === '(auth)';
    const page = (segments as string[])[1];
    if (!user) { if (!inAuth || page === 'pair') router.replace('/(auth)/welcome'); }
    else if (!user.household_id) { if (page !== 'pair') router.replace('/(auth)/pair'); }
    else if (inAuth) router.replace('/(tabs)');
  }, [user, ready, segments, router]);
  return <>{children}</>;
}

function Shell() {
  const t = useTheme();
  const user = useAuth((s) => s.user);
  const ready = !!user?.household_id; // sudah login DAN punya dompet
  return (
    <View style={{ flex: 1, backgroundColor: t.bg }}>
      <StatusBar style={t.scheme === 'dark' ? 'light' : 'dark'} />
      <Guard>
        <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: t.bg }, animation: 'fade_from_bottom' }}>
          {/* Layar aplikasi tidak dirender sama sekali sebelum login (tidak ada panggilan API yang ditolak) */}
          <Stack.Protected guard={ready} redirectTo="/(auth)/welcome">
            <Stack.Screen name="(tabs)" />
            <Stack.Screen name="transaction/new" options={{ presentation: 'modal', animation: 'slide_from_bottom' }} />
            <Stack.Screen name="transaction/[id]" />
            <Stack.Screen name="savings/[id]" />
            <Stack.Screen name="gold" />
            <Stack.Screen name="budget/index" />
            <Stack.Screen name="accounts/index" />
            <Stack.Screen name="settings/index" />
          </Stack.Protected>
          {/* Welcome / Masuk / Daftar / Hubungkan pasangan */}
          <Stack.Protected guard={!ready} redirectTo="/(tabs)">
            <Stack.Screen name="(auth)" />
          </Stack.Protected>
        </Stack>
      </Guard>
      <Toast bottom={100} />
    </View>
  );
}

export default function Root() {
  const [loaded] = useFonts({
    PlusJakartaSans_400Regular, PlusJakartaSans_500Medium, PlusJakartaSans_600SemiBold, PlusJakartaSans_700Bold,
    BricolageGrotesque_600SemiBold, BricolageGrotesque_700Bold,
  });
  const ready = useAuth((s) => s.ready);
  useEffect(() => { useSettings.getState().load(); useAuth.getState().hydrate(); }, []);
  useEffect(() => { if (loaded && ready) SplashScreen.hideAsync().catch(() => {}); }, [loaded, ready]);
  if (!loaded || !ready) return null;
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <QueryClientProvider client={qc}>
          <ThemeProvider><Shell /></ThemeProvider>
        </QueryClientProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
