import { isSameMonth } from 'date-fns';
import { create } from 'zustand';
import { clampCycle, currentPeriodStart, daysLeftInPeriod, monthKey, periodLabel, periodWord } from '@/lib/date';
import { useAuth } from './auth';

/** Periode yang sedang dilihat, dipakai bersama oleh Beranda, Riwayat, Laporan, dan Anggaran. null = ikuti periode berjalan. */
const useSelected = create<{ month: Date | null; set: (d: Date) => void; reset: () => void }>((set) => ({
  month: null,
  set: (month) => set({ month }),
  reset: () => set({ month: null }),
}));

export const resetPeriod = () => useSelected.getState().reset();

export function usePeriod() {
  const cycle = clampCycle(useAuth((s) => s.user?.cycle_start_day));
  const selected = useSelected((s) => s.month);
  const current = currentPeriodStart(cycle);
  const month = selected ?? current;
  return {
    cycle, month, key: monthKey(month),
    setMonth: useSelected.getState().set,
    label: periodLabel(month, cycle),
    word: periodWord(cycle),
    isCurrent: isSameMonth(month, current),
    daysLeft: daysLeftInPeriod(month, cycle),
  };
}
