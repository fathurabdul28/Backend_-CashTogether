import React from 'react';
import { RefreshControl, ScrollView, View, type ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { layout, useTheme } from '@/theme';

type Props = {
  children: React.ReactNode; scroll?: boolean; padded?: boolean; style?: ViewStyle;
  onRefresh?: () => Promise<unknown> | void; bottomInset?: number; edges?: ('top' | 'bottom')[];
};

export function Screen({ children, scroll, padded = true, style, onRefresh, bottomInset = 0, edges = ['top'] }: Props) {
  const t = useTheme();
  const insets = useSafeAreaInsets();
  const [refreshing, setRefreshing] = React.useState(false);
  const inner: ViewStyle = {
    width: '100%', maxWidth: layout.maxContent, alignSelf: 'center',
    paddingHorizontal: padded ? layout.screenPadding : 0, flexGrow: 1,
  };
  const outer: ViewStyle = {
    flex: 1, backgroundColor: t.bg,
    paddingTop: edges.includes('top') ? Math.max(insets.top, 16) : 0,
    paddingBottom: edges.includes('bottom') ? insets.bottom : 0,
  };
  if (!scroll) return <View style={[outer, style]}><View style={[inner, { flex: 1 }]}>{children}</View></View>;
  return (
    <View style={[outer, style]}>
      <ScrollView
        contentContainerStyle={[inner, { paddingBottom: bottomInset + 24 }]}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        refreshControl={onRefresh ? (
          <RefreshControl tintColor={t.primary} colors={[t.primary]} refreshing={refreshing}
            onRefresh={async () => { setRefreshing(true); try { await onRefresh(); } finally { setRefreshing(false); } }} />
        ) : undefined}
      >
        {children}
      </ScrollView>
    </View>
  );
}
