import { useEffect, useRef } from 'react';
import { AppState } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { del, get, post, put } from '@/lib/api';
import { useAuth } from './auth';
import { useToast } from './toast';
import type { Account, Budgets, Category, Goal, Household, Report, Summary, Tx } from './types';

export const useHousehold = () => useQuery({ queryKey: ['household'], queryFn: () => get<Household>('/household') });
export const useCategories = () => useQuery({ queryKey: ['categories'], queryFn: () => get<Category[]>('/categories'), staleTime: 5 * 60_000 });
export const useAccounts = () => useQuery({ queryKey: ['accounts'], queryFn: () => get<Account[]>('/accounts') });
export const useSummary = (month: string) => useQuery({ queryKey: ['summary', month], queryFn: () => get<Summary>('/summary', { month }) });
export const useTransactions = (month: string, f: Record<string, any> = {}) =>
  useQuery({ queryKey: ['transactions', month, f], queryFn: () => get<Tx[]>('/transactions', { month, ...f }) });
export const useTransaction = (id: number) => useQuery({ queryKey: ['transaction', id], queryFn: () => get<Tx>(`/transactions/${id}`) });
export const useGoals = () => useQuery({ queryKey: ['goals'], queryFn: () => get<Goal[]>('/saving-goals') });
export const useGoal = (id: number) => useQuery({ queryKey: ['goal', id], queryFn: () => get<Goal>(`/saving-goals/${id}`) });
export const useBudgets = (month: string) => useQuery({ queryKey: ['budgets', month], queryFn: () => get<Budgets>('/budgets', { month }) });
export const useReport = (month: string) => useQuery({ queryKey: ['report', month], queryFn: () => get<Report>('/reports/monthly', { month }) });

const TX_KEYS = ['transactions', 'transaction', 'summary', 'accounts', 'report', 'budgets'];

export function useSaveTransaction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (b: Record<string, any>) => post<Tx>('/transactions', b),
    onSuccess: () => TX_KEYS.forEach((k) => qc.invalidateQueries({ queryKey: [k] })),
  });
}
export function useDeleteTransaction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => del(`/transactions/${id}`),
    onSuccess: () => TX_KEYS.forEach((k) => qc.invalidateQueries({ queryKey: [k] })),
  });
}
export function useRestoreTransaction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => post(`/transactions/${id}/restore`),
    onSuccess: () => TX_KEYS.forEach((k) => qc.invalidateQueries({ queryKey: [k] })),
  });
}
export function useAddEntry(goalId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (b: { amount: number; note?: string }) => post(`/saving-goals/${goalId}/entries`, b),
    onSuccess: () => ['goals', 'goal', 'report'].forEach((k) => qc.invalidateQueries({ queryKey: [k] })),
  });
}
export function useConvertGold(goalId: number) {
  const qc = useQueryClient();
  const refresh = () => ['goals', 'goal', 'report', 'summary'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
  return {
    convert: useMutation({ mutationFn: (b: { amount: number; gold_grams: number }) => post(`/saving-goals/${goalId}/convert`, b), onSuccess: refresh }),
    undo: useMutation({ mutationFn: (entry: number) => del(`/saving-goals/${goalId}/conversions/${entry}`), onSuccess: refresh }),
  };
}
export function useSetBudget(month: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (b: { category_id: number; limit_amount: number }) => put('/budgets', b, { month }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['budgets'] }),
  });
}

/** Polling pengganti realtime: cek stempel perubahan tiap 3 dtk saat app aktif. */
export function usePulse(enabled: boolean) {
  const qc = useQueryClient();
  const me = useAuth((s) => s.user?.id);
  const last = useRef<string | null>(null);
  const { data: hh } = useHousehold();

  useEffect(() => {
    if (!enabled) return;
    let alive = true;
    let timer: ReturnType<typeof setInterval> | undefined;
    const tick = async () => {
      try {
        const p = await get<{ transactions: string | null; last_saving_by: number | null; savings: string | null; budgets: string | null }>('/pulse');
        const sig = JSON.stringify([p.transactions, p.savings, p.budgets]);
        if (!alive) return;
        const prev: (string | null)[] | null = last.current ? JSON.parse(last.current) : null;
        if (last.current && sig !== last.current) {
          ['transactions', 'transaction', 'summary', 'accounts', 'report', 'budgets', 'goals', 'goal'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
          const savingsChanged = prev?.[1] !== p.savings;
          if (savingsChanged && p.last_saving_by && p.last_saving_by !== me) {
            const who = hh?.members.find((m) => m.user_id === p.last_saving_by)?.name ?? 'Pasanganmu';
            useToast.getState().show(`${who} memperbarui tabungan`);
          }
        }
        last.current = sig;
      } catch {}
    };
    const start = () => { tick(); timer = setInterval(tick, 3000); };
    const stop = () => timer && clearInterval(timer);
    start();
    const sub = AppState.addEventListener('change', (s) => { stop(); if (s === 'active') start(); });
    return () => { alive = false; stop(); sub.remove(); };
  }, [enabled, qc, me, hh]);
}

export type Recurring = { id: number; amount: number; day: number; note: string | null; active: boolean; category_id: number | null; account_id: number; next_month: string };
export const useRecurring = () => useQuery({ queryKey: ['recurring'], queryFn: () => get<Recurring[]>('/recurring') });
