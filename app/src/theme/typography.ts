import type { TextStyle } from 'react-native';

const tab: TextStyle = { fontVariant: ['tabular-nums'] };
export const type = {
  displayXL: { fontFamily: 'BricolageGrotesque_700Bold', fontSize: 40, lineHeight: 44, letterSpacing: -1.2, ...tab },
  displayL: { fontFamily: 'BricolageGrotesque_700Bold', fontSize: 32, lineHeight: 36, letterSpacing: -0.8, ...tab },
  displayM: { fontFamily: 'BricolageGrotesque_600SemiBold', fontSize: 24, lineHeight: 30, letterSpacing: -0.4 },
  titleL: { fontFamily: 'PlusJakartaSans_700Bold', fontSize: 18, lineHeight: 24 },
  titleM: { fontFamily: 'PlusJakartaSans_600SemiBold', fontSize: 16, lineHeight: 22 },
  body: { fontFamily: 'PlusJakartaSans_500Medium', fontSize: 15, lineHeight: 22 },
  bodySm: { fontFamily: 'PlusJakartaSans_400Regular', fontSize: 13, lineHeight: 18 },
  label: { fontFamily: 'PlusJakartaSans_600SemiBold', fontSize: 13, lineHeight: 16, letterSpacing: 0.2 },
  overline: { fontFamily: 'PlusJakartaSans_700Bold', fontSize: 11, lineHeight: 14, letterSpacing: 1.0, textTransform: 'uppercase' },
  amount: { fontFamily: 'BricolageGrotesque_600SemiBold', fontSize: 16, lineHeight: 20, ...tab },
} satisfies Record<string, TextStyle>;
export type TypeVariant = keyof typeof type;
