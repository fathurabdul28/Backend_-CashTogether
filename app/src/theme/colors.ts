export const light = {
  bg: '#F6F2EA', surface: '#FFFDF8', surfaceAlt: '#EFE9DD', hairline: '#E2DACB',
  overlay: 'rgba(28, 36, 31, 0.40)',
  ink: '#1C241F', inkMuted: '#5E6A62', inkFaint: '#97A098',
  income: '#2F7A4F', incomeSoft: '#DDEEE2', expense: '#B4532A', expenseSoft: '#F5E1D6',
  saving: '#23736F', savingSoft: '#D5EBE8', warning: '#C08A1E', warningSoft: '#F6EBCF',
  danger: '#A63D2F', dangerSoft: '#F3DCD7',
};
export const dark: typeof light = {
  bg: '#121714', surface: '#1A211D', surfaceAlt: '#232B26', hairline: '#2E3832',
  overlay: 'rgba(0,0,0,0.55)',
  ink: '#EDE8DD', inkMuted: '#A5AEA7', inkFaint: '#6D766F',
  income: '#7CC79A', incomeSoft: '#1F3328', expense: '#E39A73', expenseSoft: '#3A261C',
  saving: '#7CCBC4', savingSoft: '#1C3331', warning: '#E2B65A', warningSoft: '#3A3020',
  danger: '#E07A6A', dangerSoft: '#3B221E',
};
export const categoryColors = {
  light: {
    sage: { bg: '#DCE8DF', fg: '#1F4D3A' }, clay: { bg: '#F5E1D6', fg: '#8E3F1E' },
    teal: { bg: '#D5EBE8', fg: '#23736F' }, mustard: { bg: '#F6EBCF', fg: '#7D5A10' },
    plum: { bg: '#EBDFE6', fg: '#6B3A57' }, stone: { bg: '#EAE5DA', fg: '#4D4A42' },
  },
  dark: {
    sage: { bg: '#22352B', fg: '#8FC4A5' }, clay: { bg: '#3A261C', fg: '#E39A73' },
    teal: { bg: '#1C3331', fg: '#7CCBC4' }, mustard: { bg: '#3A3020', fg: '#E2B65A' },
    plum: { bg: '#33232D', fg: '#D3A3BF' }, stone: { bg: '#2A2925', fg: '#C2BCAE' },
  },
};
export type CategoryColorKey = keyof typeof categoryColors.light;
export type Palette = typeof light;
