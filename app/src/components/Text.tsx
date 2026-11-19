import React from 'react';
import { Text as RNText, type TextProps, type TextStyle } from 'react-native';
import { useTheme, type Theme } from '@/theme';
import { type, type TypeVariant } from '@/theme/typography';

type ColorKey = Exclude<{ [K in keyof Theme]: Theme[K] extends string ? K : never }[keyof Theme], 'scheme'>;
type Props = TextProps & { variant?: TypeVariant; color?: ColorKey; display?: boolean };

export function Text({ variant = 'body', color = 'ink', display, style, ...p }: Props) {
  const t = useTheme();
  const s: TextStyle = { ...type[variant] as TextStyle, color: t[color] as string };
  return <RNText allowFontScaling maxFontSizeMultiplier={display || variant.startsWith('display') ? 1.4 : 1.6} {...p} style={[s, style]} />;
}
