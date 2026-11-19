export const formatIDR = (n: number) => 'Rp' + Math.abs(Math.round(n)).toLocaleString('id-ID');

export const formatIDRCompact = (n: number) => {
  const a = Math.abs(n);
  const f = (v: number, d: number) => v.toLocaleString('id-ID', { maximumFractionDigits: d });
  if (a >= 1e9) return `Rp${f(a / 1e9, 2)} M`;
  if (a >= 1e6) return `Rp${f(a / 1e6, 2)} jt`;
  if (a >= 1e3) return `Rp${f(a / 1e3, 0)} rb`;
  return formatIDR(a);
};

/** Pembentuk teks untuk pembaca layar: "pengeluaran empat puluh lima ribu rupiah" (versi ringkas berbasis angka) */
export const spokenIDR = (n: number, kind?: string) =>
  `${kind ? kind + ' ' : ''}${Math.abs(Math.round(n)).toLocaleString('id-ID')} rupiah`;

export const initial = (name?: string) => (name?.trim()[0] ?? '?').toUpperCase();
