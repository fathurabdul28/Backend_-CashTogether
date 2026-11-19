import React from 'react';
import { View } from 'react-native';
import { initial } from '@/lib/format';
import { useUserColor } from '@/theme';
import type { Member } from '@/features/types';
import { Text } from './Text';

export function Avatar({ user, size = 32, online }: { user?: Pick<Member, 'name' | 'gender' | 'color_variant'> | null; size?: number; online?: boolean }) {
  const c = useUserColor(user);
  return (
    <View accessibilityLabel={user?.name} style={{ width: size, height: size, borderRadius: 999, backgroundColor: c.primarySoft, alignItems: 'center', justifyContent: 'center' }}>
      <Text variant="label" style={{ color: c.fgOnSoft, fontSize: Math.max(9, size * 0.42), lineHeight: size * 0.5 }} maxFontSizeMultiplier={1}>{initial(user?.name)}</Text>
      {online ? <View style={{ position: 'absolute', right: -1, bottom: -1, width: size * 0.3, height: size * 0.3, borderRadius: 99, backgroundColor: '#2F7A4F', borderWidth: 2, borderColor: '#FFFDF8' }} /> : null}
    </View>
  );
}

export function AvatarPair({ users, size = 28 }: { users: Member[]; size?: number }) {
  return (
    <View style={{ flexDirection: 'row' }}>
      {users.map((u, i) => <View key={u.user_id} style={{ marginLeft: i ? -8 : 0 }}><Avatar user={u} size={size} /></View>)}
    </View>
  );
}
