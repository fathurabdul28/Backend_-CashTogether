import {
  ForkKnife, ShoppingCart, Motorcycle, Lightning, House, FirstAidKit, FilmSlate, BookOpen, WifiHigh, Bag, Gift,
  CreditCard, DotsThree, Briefcase, Sparkle, Storefront, ChartLineUp, Shield, Target, Airplane, Money, Bank,
  DeviceMobile, Car, Heart, Star, GraduationCap, PiggyBank, Receipt,
} from 'phosphor-react-native';
import type { Icon } from 'phosphor-react-native';

const MAP: Record<string, Icon> = {
  ForkKnife, ShoppingCart, Motorcycle, Lightning, House, FirstAidKit, FilmSlate, BookOpen, WifiHigh, Bag, Gift,
  CreditCard, DotsThree, Briefcase, Sparkle, Storefront, ChartLineUp, Shield, Target, Airplane, Money, Bank,
  DeviceMobile, Car, Heart, Star, GraduationCap, PiggyBank, Receipt,
};
export const iconFor = (name?: string): Icon => (name && MAP[name]) || DotsThree;
export const GOAL_ICONS = ['Shield', 'Airplane', 'House', 'Car', 'Heart', 'GraduationCap', 'PiggyBank', 'Target'];
export const ACCOUNT_ICON = { cash: Money, bank: Bank, ewallet: DeviceMobile, credit: CreditCard } as const;
