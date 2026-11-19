import React, { useEffect, useState } from 'react';
import { Modal, Pressable, View } from 'react-native';
import { CaretLeft, CaretRight } from 'phosphor-react-native';
import {
  addMonths, eachDayOfInterval, endOfMonth, endOfWeek, format, isAfter, isSameDay, isSameMonth,
  startOfDay, startOfMonth, startOfWeek, subDays,
} from 'date-fns';
import { id } from 'date-fns/locale';
import { radius, useTheme } from '@/theme';
import { Button, IconButton } from './Button';
import { Text } from './Text';

const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

/** Kalender sederhana (tanpa modul native) yang jalan sama di web, iPhone, dan Android. Tanggal masa depan dinonaktifkan. */
export function DatePickerSheet({ visible, value, onClose, onPick, maxDate = new Date() }: {
  visible: boolean; value: Date; onClose: () => void; onPick: (d: Date) => void; maxDate?: Date;
}) {
  const t = useTheme();
  const max = startOfDay(maxDate);
  const [cursor, setCursor] = useState(startOfMonth(value));
  useEffect(() => { if (visible) setCursor(startOfMonth(value)); /* eslint-disable-next-line */ }, [visible]);

  const days = eachDayOfInterval({
    start: startOfWeek(startOfMonth(cursor), { weekStartsOn: 1 }),
    end: endOfWeek(endOfMonth(cursor), { weekStartsOn: 1 }),
  });
  const canNext = !isAfter(startOfMonth(addMonths(cursor, 1)), max);
  const pick = (d: Date) => { onPick(d); onClose(); };

  return (
    <Modal transparent visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: t.overlay, justifyContent: 'flex-end' }}>
        <Pressable style={{ flex: 1 }} onPress={onClose} accessibilityLabel="Tutup pemilih tanggal" />
        <View style={{ backgroundColor: t.surface, borderTopLeftRadius: radius.xl, borderTopRightRadius: radius.xl, padding: 20, gap: 12, width: '100%', maxWidth: 560, alignSelf: 'center' }}>
          <Text variant="titleL" accessibilityRole="header">Pilih tanggal</Text>

          <View style={{ flexDirection: 'row', gap: 8 }}>
            {[{ l: 'Hari ini', d: max }, { l: 'Kemarin', d: subDays(max, 1) }].map((q) => {
              const on = isSameDay(q.d, value);
              return (
                <Pressable key={q.l} accessibilityRole="button" accessibilityState={{ selected: on }} onPress={() => pick(q.d)}
                  style={{ height: 34, paddingHorizontal: 14, borderRadius: radius.pill, justifyContent: 'center', backgroundColor: on ? t.primary : t.surfaceAlt }}>
                  <Text variant="label" style={{ color: on ? t.onPrimary : t.inkMuted }}>{q.l}</Text>
                </Pressable>
              );
            })}
          </View>

          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <IconButton label="Bulan sebelumnya" onPress={() => setCursor(addMonths(cursor, -1))} icon={<CaretLeft size={20} color={t.inkMuted} />} />
            <Text variant="titleM" accessibilityLiveRegion="polite" style={{ textTransform: 'capitalize' }}>{format(cursor, 'MMMM yyyy', { locale: id })}</Text>
            <IconButton label="Bulan berikutnya" onPress={() => canNext && setCursor(addMonths(cursor, 1))}
              icon={<CaretRight size={20} color={canNext ? t.inkMuted : t.hairline} />} />
          </View>

          <View style={{ flexDirection: 'row' }}>
            {WEEKDAYS.map((w) => <Text key={w} variant="label" color="inkFaint" style={{ width: '14.2857%', textAlign: 'center' }}>{w}</Text>)}
          </View>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
            {days.map((d) => {
              const disabled = isAfter(d, max);
              const selected = isSameDay(d, value);
              const today = isSameDay(d, max);
              const inMonth = isSameMonth(d, cursor);
              return (
                <Pressable key={d.toISOString()} disabled={disabled} onPress={() => pick(d)}
                  accessibilityRole="button" accessibilityState={{ selected, disabled }}
                  accessibilityLabel={format(d, 'd MMMM yyyy', { locale: id })}
                  style={{ width: '14.2857%', height: 44, alignItems: 'center', justifyContent: 'center' }}>
                  <View style={{ width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center',
                    backgroundColor: selected ? t.primary : 'transparent', borderWidth: today && !selected ? 1.5 : 0, borderColor: t.primary, opacity: disabled ? 0.3 : 1 }}>
                    <Text variant="body" style={{ color: selected ? t.onPrimary : inMonth ? t.ink : t.inkFaint }}>{d.getDate()}</Text>
                  </View>
                </Pressable>
              );
            })}
          </View>
          <Button label="Tutup" variant="ghost" onPress={onClose} />
        </View>
      </View>
    </Modal>
  );
}
