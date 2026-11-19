import { useMemo } from 'react';
import { useAuth } from './auth';
import { useAccounts, useCategories, useHousehold } from './queries';

export function useLookup() {
  const me = useAuth((s) => s.user);
  const { data: hh } = useHousehold();
  const { data: cats } = useCategories();
  const { data: accs } = useAccounts();
  return useMemo(() => {
    const members = hh?.members ?? [];
    return {
      me, household: hh, members,
      meMember: members.find((m) => m.user_id === me?.id),
      partner: members.find((m) => m.user_id !== me?.id),
      member: (id?: number | null) => members.find((m) => m.user_id === id),
      categories: cats ?? [], accounts: accs ?? [],
      category: (id?: number | null) => cats?.find((c) => c.id === id),
      account: (id?: number | null) => accs?.find((a) => a.id === id),
    };
  }, [me, hh, cats, accs]);
}
