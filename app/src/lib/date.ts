import { differenceInCalendarDays, format, isToday, isYesterday, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';

export const monthKey = (d: Date) => format(d, 'yyyy-MM');
export const monthLabel = (d: Date) => format(d, 'MMMM yyyy', { locale: id });
export const addMonths = (d: Date, n: number) => new Date(d.getFullYear(), d.getMonth() + n, 1);

export function dayLabel(iso: string) {
  const d = parseISO(iso);
  if (isToday(d)) return 'Hari ini';
  if (isYesterday(d)) return 'Kemarin';
  return format(d, 'EEEE, d MMM', { locale: id });
}
export const dayKey = (iso: string) => format(parseISO(iso), 'yyyy-MM-dd');
export const timeLabel = (iso: string) => format(parseISO(iso), 'd MMM yyyy, HH:mm', { locale: id });

export function greeting(now = new Date()) {
  const h = now.getHours();
  return h < 11 ? 'Selamat pagi,' : h < 15 ? 'Selamat siang,' : h < 18 ? 'Selamat sore,' : 'Selamat malam,';
}

// ---- Periode keuangan: bisa mengikuti tanggal gajian (mis. 25 Okt – 24 Nov). Periode dinamai menurut bulan awalnya. ----
export const clampCycle = (n?: number | null) => Math.min(28, Math.max(1, Math.round(n || 1)));

/** Tanggal 1 dari bulan awal periode yang sedang berjalan pada `now`. */
export function currentPeriodStart(cycleDay: number, now = new Date()): Date {
  const c = clampCycle(cycleDay);
  return now.getDate() >= c ? new Date(now.getFullYear(), now.getMonth(), 1) : new Date(now.getFullYear(), now.getMonth() - 1, 1);
}
export function periodBounds(month: Date, cycleDay: number) {
  const c = clampCycle(cycleDay);
  return { start: new Date(month.getFullYear(), month.getMonth(), c), end: new Date(month.getFullYear(), month.getMonth() + 1, c - 1) };
}
export function periodLabel(month: Date, cycleDay: number) {
  if (clampCycle(cycleDay) === 1) return monthLabel(month);
  const { start, end } = periodBounds(month, cycleDay);
  return `${format(start, 'd MMM', { locale: id })} – ${format(end, 'd MMM yyyy', { locale: id })}`;
}
export const periodWord = (cycleDay: number) => (clampCycle(cycleDay) === 1 ? 'bulan ini' : 'periode ini');
export const daysLeftInPeriod = (month: Date, cycleDay: number, now = new Date()) =>
  Math.max(0, differenceInCalendarDays(periodBounds(month, cycleDay).end, now));

/** Teks tanggal di sheet Catat: "Hari ini", "Kemarin", atau "Sen, 17 Nov 2026". */
export function pickedDateLabel(d: Date) {
  if (isToday(d)) return 'Hari ini';
  if (isYesterday(d)) return 'Kemarin';
  return format(d, 'EEE, d MMM yyyy', { locale: id });
}
