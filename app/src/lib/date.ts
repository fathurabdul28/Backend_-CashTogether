import { format, isToday, isYesterday, parseISO } from 'date-fns';
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
export const daysLeftInMonth = (now = new Date()) =>
  new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate() - now.getDate();
