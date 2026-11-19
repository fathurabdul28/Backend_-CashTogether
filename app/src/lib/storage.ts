import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

export const storage = {
  async get(k: string): Promise<string | null> {
    try { return Platform.OS === 'web' ? localStorage.getItem(k) : await SecureStore.getItemAsync(k); } catch { return null; }
  },
  async set(k: string, v: string) {
    try { Platform.OS === 'web' ? localStorage.setItem(k, v) : await SecureStore.setItemAsync(k, v); } catch {}
  },
  async del(k: string) {
    try { Platform.OS === 'web' ? localStorage.removeItem(k) : await SecureStore.deleteItemAsync(k); } catch {}
  },
};
