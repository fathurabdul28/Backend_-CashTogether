import { Linking, Platform } from 'react-native';
import { post } from '@/lib/api';
import { useToast } from './toast';

/**
 * Ekspor ke Excel (.xlsx; Google Sheets juga bisa membukanya).
 * Meminta tautan sementara bertanda tangan dari server (berlaku 5 menit), lalu membukanya:
 * di web browser langsung mengunduh, di HP sistem yang menangani unduhan/pembukaan file.
 */
export async function exportExcel(opts: { month?: string; all?: boolean }) {
  const toast = useToast.getState();
  try {
    toast.show('Menyiapkan file Excel…');
    const r = await post<{ url: string; filename: string }>('/export/link', {}, opts.all ? { scope: 'all' } : { month: opts.month });
    if (Platform.OS === 'web') window.location.assign(r.url);
    else await Linking.openURL(r.url);
    toast.show(`Mengunduh ${r.filename}`);
  } catch (e: any) {
    toast.show(e?.message ?? 'Ekspor gagal. Coba lagi sebentar ya.');
  }
}
