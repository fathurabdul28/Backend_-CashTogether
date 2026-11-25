import type { Gender } from '@/theme/identity';

export type Member = { user_id: number; name: string; role: 'owner' | 'partner'; gender: Gender; color_variant: 'default' | 'alt' };
export type Household = { id: number; name: string; invite_code: string | null; invite_expires_at: string | null; members: Member[] };
export type TxType = 'income' | 'expense';
export type Category = { id: number; name: string; kind: 'income' | 'expense'; icon: string; color: string };
export type Account = { id: number; name: string; type: 'cash' | 'bank' | 'ewallet' | 'credit'; owner_id: number; initial_balance: number; balance: number };
export type Tx = {
  id: number; type: TxType; amount: number; category_id: number | null; account_id: number;
  occurred_at: string; note: string | null; created_by: number; updated_by: number | null; updated_at: string;
};
export type Summary = { balance: number; income: number; expense: number; saved: number; period?: string; label?: string };
export type Goal = {
  id: number; name: string; target_amount: number; target_date: string | null; icon: string; color: string;
  achieved_at: string | null; saved: number; gold_grams: number; contributions: Record<string, number>; monthly_needed: number | null;
  entries?: { id: number; amount: number; contributed_by: number; occurred_at: string; note: string | null; from_balance: boolean; gold_grams: number | null; is_conversion: boolean }[];
};
export type BudgetItem = { id: number; category_id: number; limit_amount: number; spent: number };
export type Budgets = { month: string; total_limit: number; total_spent: number; items: BudgetItem[] };
export type Report = {
  month: string; income: number; expense: number;
  weeks: { label: string; income: number; expense: number }[];
  by_category: { category_id: number; name: string; amount: number }[];
  insights: string[];
};
