export type Gender = 'male' | 'female';
export type Scheme = 'light' | 'dark';
type Identity = { primary: string; primaryPress: string; primarySoft: string; onPrimary: string; fgOnSoft: string };

export const identity: Record<Gender, Record<Scheme, Identity>> = {
  male: {
    light: { primary: '#1F4E8C', primaryPress: '#183E70', primarySoft: '#DCE6F4', onPrimary: '#F6F2EA', fgOnSoft: '#1F4E8C' },
    dark: { primary: '#8DB4E8', primaryPress: '#7AA2D8', primarySoft: '#1E2C40', onPrimary: '#121714', fgOnSoft: '#8DB4E8' },
  },
  female: {
    light: { primary: '#B83D72', primaryPress: '#962F5B', primarySoft: '#F6DDE8', onPrimary: '#FFFDF8', fgOnSoft: '#962F5B' },
    dark: { primary: '#EE9DC0', primaryPress: '#E088AF', primarySoft: '#3A2230', onPrimary: '#121714', fgOnSoft: '#EE9DC0' },
  },
};
// Varian alternatif untuk pasangan sejenis kelamin
export const identityAlt: Record<Gender, Record<Scheme, Identity>> = {
  male: {
    light: { primary: '#3C7FC2', primaryPress: '#2F6BA8', primarySoft: '#DDEBF7', onPrimary: '#FFFDF8', fgOnSoft: '#1F4E8C' },
    dark: { primary: '#8DB4E8', primaryPress: '#7AA2D8', primarySoft: '#1E2C40', onPrimary: '#121714', fgOnSoft: '#8DB4E8' },
  },
  female: {
    light: { primary: '#8E3B6E', primaryPress: '#732F59', primarySoft: '#EEDDE8', onPrimary: '#FFFDF8', fgOnSoft: '#8E3B6E' },
    dark: { primary: '#D3A3BF', primaryPress: '#C28FAE', primarySoft: '#33232D', onPrimary: '#121714', fgOnSoft: '#D3A3BF' },
  },
};
export const identityOf = (g: Gender, s: Scheme, variant: 'default' | 'alt' = 'default') =>
  (variant === 'alt' ? identityAlt : identity)[g][s];
