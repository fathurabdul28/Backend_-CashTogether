import React, { useState } from 'react';
import { View } from 'react-native';
import Svg, { Circle, Line, Rect } from 'react-native-svg';
import { Lightbulb } from 'phosphor-react-native';
import { Screen } from '@/components/Screen';
import { Text } from '@/components/Text';
import { MonthSwitcher } from '@/components/Controls';
import { ErrorState, Skeleton } from '@/components/Feedback';
import { useReport } from '@/features/queries';
import { monthKey } from '@/lib/date';
import { formatIDR, formatIDRCompact } from '@/lib/format';
import { radius, useTheme } from '@/theme';

const Card = ({ title, sub, children }: { title: string; sub?: string; children: React.ReactNode }) => {
  const t = useTheme();
  return (
    <View style={{ backgroundColor: t.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: t.hairline, padding: 16, gap: 12, marginBottom: 16 }}>
      <View><Text variant="titleL">{title}</Text>{sub ? <Text variant="bodySm" color="inkMuted">{sub}</Text> : null}</View>
      {children}
    </View>
  );
};

export default function Reports() {
  const t = useTheme();
  const [month, setMonth] = useState(new Date());
  const q = useReport(monthKey(month));
  const r = q.data;
  const W = 280, H = 140;
  const max = Math.max(1, ...(r?.weeks ?? []).flatMap((w) => [w.income, w.expense]));
  const palette = [t.expense, t.warning, t.saving, t.primary, t.income];
  const cats = (r?.by_category ?? []).slice(0, 5);
  const rest = (r?.by_category ?? []).slice(5).reduce((s, c) => s + c.amount, 0);
  const legend = [...cats.map((c) => ({ name: c.name, amount: c.amount })), ...(rest ? [{ name: 'Lainnya', amount: rest }] : [])];
  const totalExp = r?.expense ?? 0;
  const R = 62, C = 2 * Math.PI * R;
  let acc = 0;

  return (
    <Screen scroll bottomInset={40} onRefresh={() => q.refetch()}>
      <View style={{ minHeight: 56, justifyContent: 'center' }}><Text variant="displayM" accessibilityRole="header">Laporan</Text></View>
      <View style={{ marginBottom: 12 }}><MonthSwitcher value={month} onChange={setMonth} /></View>
      {q.isLoading ? <View style={{ gap: 16 }}><Skeleton h={200} r={16} /><Skeleton h={200} r={16} /></View> : q.isError || !r ? <ErrorState onRetry={() => q.refetch()} /> : (
        <>
          <Card title="Arus kas" sub="Per minggu">
            <View style={{ flexDirection: 'row', gap: 12 }}>
              <Text variant="bodySm" color="income">■ Masuk {formatIDRCompact(r.income)}</Text>
              <Text variant="bodySm" color="expense">■ Keluar {formatIDRCompact(r.expense)}</Text>
            </View>
            <Svg width="100%" height={H + 22} viewBox={`0 0 ${W} ${H + 22}`} accessibilityLabel="Grafik arus kas mingguan">
              {[0, 0.5, 1].map((p) => <Line key={p} x1={0} x2={W} y1={H - H * p} y2={H - H * p} stroke={t.hairline} strokeWidth={1} strokeDasharray={p ? '3 3' : undefined} />)}
              {r.weeks.map((w, i) => {
                const gx = i * (W / 4) + 16, bw = 18;
                const hi = (w.income / max) * (H - 4), he = (w.expense / max) * (H - 4);
                return (
                  <React.Fragment key={w.label}>
                    <Rect x={gx} y={H - hi} width={bw} height={hi} rx={4} fill={t.income} />
                    <Rect x={gx + bw + 4} y={H - he} width={bw} height={he} rx={4} fill={t.expense} />
                  </React.Fragment>
                );
              })}
            </Svg>
            <View style={{ flexDirection: 'row' }}>{r.weeks.map((w) => <Text key={w.label} variant="bodySm" color="inkMuted" style={{ flex: 1, textAlign: 'center' }}>{w.label}</Text>)}</View>
          </Card>

          <Card title="Ke mana uangnya pergi">
            {totalExp === 0 ? <Text color="inkMuted">Belum ada pengeluaran bulan ini.</Text> : (
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                <View style={{ width: 150, height: 150, alignItems: 'center', justifyContent: 'center' }}>
                  <Svg width={150} height={150} viewBox="0 0 160 160" style={{ position: 'absolute', transform: [{ rotate: '-90deg' }] }}>
                    <Circle cx={80} cy={80} r={R} stroke={t.surfaceAlt} strokeWidth={22} fill="none" />
                    {legend.map((s, i) => {
                      const len = Math.max(0, (s.amount / totalExp) * C - 3);
                      const el = <Circle key={s.name} cx={80} cy={80} r={R} fill="none" stroke={palette[i % palette.length]} strokeWidth={22} strokeDasharray={`${len} ${C}`} strokeDashoffset={-acc} />;
                      acc += (s.amount / totalExp) * C;
                      return el;
                    })}
                  </Svg>
                  <Text variant="bodySm" color="inkMuted">Total</Text><Text variant="titleM">{formatIDRCompact(totalExp)}</Text>
                </View>
                <View style={{ flex: 1, gap: 8 }}>
                  {legend.map((s, i) => (
                    <View key={s.name} style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                      <View style={{ width: 10, height: 10, borderRadius: 3, backgroundColor: palette[i % palette.length] }} />
                      <Text variant="bodySm" style={{ flex: 1 }} numberOfLines={1}>{s.name}</Text>
                      <Text variant="label">{Math.round((s.amount / totalExp) * 100)}%</Text>
                    </View>
                  ))}
                </View>
              </View>
            )}
          </Card>

          {r.insights.map((s) => (
            <View key={s} style={{ flexDirection: 'row', gap: 12, alignItems: 'center', backgroundColor: t.warningSoft, borderRadius: radius.md, padding: 12, marginBottom: 8 }}>
              <Lightbulb size={22} color={t.warning} /><Text style={{ flex: 1 }}>{s}</Text>
            </View>
          ))}
          <Text variant="bodySm" color="inkFaint" style={{ marginTop: 8 }}>Total pengeluaran {formatIDR(totalExp)}</Text>
        </>
      )}
    </Screen>
  );
}
