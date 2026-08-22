import type { Locale } from "@/lib/learning-api";

export const notificationCopy: Record<
  Locale,
  {
    title: string;
    subtitle: string;
    back: string;
    loading: string;
    emptyTitle: string;
    emptyBody: string;
    unavailable: string;
    offline: string;
    permission: string;
    retry: string;
    unread: string;
    read: string;
    markRead: string;
    markAll: string;
    markedAll: string;
    notifications: string;
  }
> = {
  en: {
    title: "Notifications",
    subtitle: "Updates saved to your MODRIK account. Push delivery is auxiliary; this inbox remains the source you can review later.",
    back: "Back to learning",
    loading: "Loading notifications…",
    emptyTitle: "You're all caught up",
    emptyBody: "There are no account notifications to show yet.",
    unavailable: "Notifications are temporarily unavailable.",
    offline: "You're offline. Reconnect to refresh your notification inbox.",
    permission: "Sign in to view your notifications.",
    retry: "Retry",
    unread: "Unread",
    read: "Read",
    markRead: "Mark as read",
    markAll: "Mark all as read",
    markedAll: "All notifications are read.",
    notifications: "notifications",
  },
  ar: {
    title: "الإشعارات",
    subtitle: "تحديثات محفوظة في حساب مُدرك. إشعارات Push خدمة مساعدة، بينما يظل هذا الصندوق هو السجل الذي يمكنك الرجوع إليه.",
    back: "العودة للتعلم",
    loading: "جارٍ تحميل الإشعارات…",
    emptyTitle: "لا توجد إشعارات جديدة",
    emptyBody: "لا توجد إشعارات مرتبطة بحسابك لعرضها الآن.",
    unavailable: "الإشعارات غير متاحة مؤقتًا.",
    offline: "أنت غير متصل. أعد الاتصال لتحديث صندوق الإشعارات.",
    permission: "سجّل الدخول لعرض إشعاراتك.",
    retry: "إعادة المحاولة",
    unread: "غير مقروء",
    read: "مقروء",
    markRead: "تحديد كمقروء",
    markAll: "تحديد الكل كمقروء",
    markedAll: "تمت قراءة كل الإشعارات.",
    notifications: "إشعارات",
  },
  fr: {
    title: "Notifications",
    subtitle: "Mises à jour enregistrées dans votre compte MODRIK. Le push reste auxiliaire ; cette boîte de réception reste consultable plus tard.",
    back: "Retour à l’apprentissage",
    loading: "Chargement des notifications…",
    emptyTitle: "Vous êtes à jour",
    emptyBody: "Aucune notification de compte à afficher pour le moment.",
    unavailable: "Les notifications sont temporairement indisponibles.",
    offline: "Vous êtes hors ligne. Reconnectez-vous pour actualiser vos notifications.",
    permission: "Connectez-vous pour voir vos notifications.",
    retry: "Réessayer",
    unread: "Non lue",
    read: "Lue",
    markRead: "Marquer comme lue",
    markAll: "Tout marquer comme lu",
    markedAll: "Toutes les notifications sont lues.",
    notifications: "notifications",
  },
};
