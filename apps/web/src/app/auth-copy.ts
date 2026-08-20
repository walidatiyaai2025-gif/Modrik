import type { Locale } from "../lib/auth-api";

export type AuthCopy = {
  brandTagline: string;
  loading: string;
  signInTitle: string;
  registerTitle: string;
  recoveryTitle: string;
  verifyTitle: string;
  resetTitle: string;
  accountTitle: string;
  email: string;
  name: string;
  password: string;
  currentPassword: string;
  newPassword: string;
  token: string;
  signIn: string;
  register: string;
  sendRecovery: string;
  verify: string;
  resetPassword: string;
  resendVerification: string;
  forgotPassword: string;
  createAccount: string;
  backToLogin: string;
  useVerificationToken: string;
  useResetToken: string;
  learning: string;
  account: string;
  logout: string;
  offlineTitle: string;
  offlineBody: string;
  retry: string;
  genericError: string;
  loginRejected: string;
  recoveryAccepted: string;
  verificationAccepted: string;
  verified: string;
  resetComplete: string;
  sessionExpiredTitle: string;
  sessionExpiredBody: string;
  unverifiedTitle: string;
  unverifiedBody: string;
  sessionsTitle: string;
  sessionsLoading: string;
  noOtherSessions: string;
  currentSession: string;
  lastUsed: string;
  revokeOthers: string;
  revokeAll: string;
  recentAuthTitle: string;
  recentAuthBody: string;
  reauthenticate: string;
  changePasswordTitle: string;
  changePassword: string;
  passwordChanged: string;
  deleteTitle: string;
  deleteWarning: string;
  deleteConfirm: string;
  deleteAccount: string;
  providerTitle: string;
  providerGoogle: string;
  providerApple: string;
  providerLogin: string;
  providerLink: string;
  providerPendingTitle: string;
  providerPendingBody: string;
  providerError: string;
  accountSummaryUnavailable: string;
  working: string;
  signedIn: string;
  accountCreated: string;
};

export const authCopy: Record<Locale, AuthCopy> = {
  en: {
    brandTagline: "Secure access to your MODRIK learning workspace",
    loading: "Checking your account session…",
    signInTitle: "Sign in",
    registerTitle: "Create your account",
    recoveryTitle: "Recover your password",
    verifyTitle: "Verify your email",
    resetTitle: "Set a new password",
    accountTitle: "Account & security",
    email: "Email",
    name: "Name",
    password: "Password",
    currentPassword: "Current password",
    newPassword: "New password",
    token: "One-time token",
    signIn: "Sign in",
    register: "Create account",
    sendRecovery: "Send recovery instructions",
    verify: "Verify email",
    resetPassword: "Reset password",
    resendVerification: "Resend verification",
    forgotPassword: "Forgot password?",
    createAccount: "Create an account",
    backToLogin: "Back to sign in",
    useVerificationToken: "I have a verification token",
    useResetToken: "I have a reset token",
    learning: "Learning workspace",
    account: "Account & sessions",
    logout: "Sign out",
    offlineTitle: "You are offline",
    offlineBody: "Account changes need a connection. Your learning workspace will keep its existing offline behavior.",
    retry: "Retry",
    genericError: "The request could not be completed. Try again.",
    loginRejected: "The email or password was not accepted.",
    recoveryAccepted: "If an eligible account matches that address, recovery instructions will be sent.",
    verificationAccepted: "If verification is still needed, a new message will be sent.",
    verified: "Email verification completed.",
    resetComplete: "Password reset completed. Sign in again with the new password.",
    sessionExpiredTitle: "Your session ended",
    sessionExpiredBody: "This session expired or was revoked. Sign in again to continue.",
    unverifiedTitle: "Email verification required",
    unverifiedBody: "Protected learning changes remain unavailable until the account email is verified.",
    sessionsTitle: "Active sessions",
    sessionsLoading: "Loading active sessions…",
    noOtherSessions: "No other active sessions were found.",
    currentSession: "Current session",
    lastUsed: "Last used",
    revokeOthers: "Revoke other sessions",
    revokeAll: "Revoke all sessions",
    recentAuthTitle: "Confirm it is you",
    recentAuthBody: "This sensitive action needs a recent password confirmation.",
    reauthenticate: "Confirm password",
    changePasswordTitle: "Change password",
    changePassword: "Change password",
    passwordChanged: "Password changed. Other sessions were revoked by the Backend policy.",
    deleteTitle: "Delete account",
    deleteWarning: "Deletion is sensitive and irreversible from this screen. Type DELETE exactly, then confirm your password if requested.",
    deleteConfirm: "Type DELETE to confirm",
    deleteAccount: "Delete my account",
    providerTitle: "Connected sign-in providers",
    providerGoogle: "Google",
    providerApple: "Apple",
    providerLogin: "Continue with",
    providerLink: "Link",
    providerPendingTitle: "Provider setup pending",
    providerPendingBody: "MODRIK prepared a secure provider intent, but production provider client configuration is not available yet. No ID or secret is invented by the Web app.",
    providerError: "The provider sign-in could not be completed.",
    accountSummaryUnavailable: "This session is valid. Email/provider profile details are shown only when the existing Backend contract supplies them.",
    working: "Working…",
    signedIn: "Signed in securely.",
    accountCreated: "Account created. Verify your email before protected learning changes.",
  },
  ar: {
    brandTagline: "دخول آمن إلى مساحة التعلّم في مُدرك",
    loading: "جارٍ التحقق من جلسة حسابك…",
    signInTitle: "تسجيل الدخول",
    registerTitle: "إنشاء حسابك",
    recoveryTitle: "استعادة كلمة المرور",
    verifyTitle: "تأكيد البريد الإلكتروني",
    resetTitle: "تعيين كلمة مرور جديدة",
    accountTitle: "الحساب والأمان",
    email: "البريد الإلكتروني",
    name: "الاسم",
    password: "كلمة المرور",
    currentPassword: "كلمة المرور الحالية",
    newPassword: "كلمة المرور الجديدة",
    token: "الرمز المؤقت",
    signIn: "دخول",
    register: "إنشاء الحساب",
    sendRecovery: "إرسال تعليمات الاستعادة",
    verify: "تأكيد البريد",
    resetPassword: "إعادة تعيين كلمة المرور",
    resendVerification: "إعادة إرسال التأكيد",
    forgotPassword: "نسيت كلمة المرور؟",
    createAccount: "إنشاء حساب",
    backToLogin: "العودة لتسجيل الدخول",
    useVerificationToken: "لدي رمز تأكيد",
    useResetToken: "لدي رمز إعادة تعيين",
    learning: "مساحة التعلّم",
    account: "الحساب والجلسات",
    logout: "تسجيل الخروج",
    offlineTitle: "أنت غير متصل",
    offlineBody: "تغييرات الحساب تحتاج إلى اتصال. تبقى آلية التعلّم دون اتصال كما هي.",
    retry: "إعادة المحاولة",
    genericError: "تعذر إكمال الطلب. حاول مرة أخرى.",
    loginRejected: "لم يتم قبول البريد الإلكتروني أو كلمة المرور.",
    recoveryAccepted: "إذا وُجد حساب مؤهل يطابق هذا العنوان فستُرسل تعليمات الاستعادة.",
    verificationAccepted: "إذا كان التأكيد لا يزال مطلوبًا فستُرسل رسالة جديدة.",
    verified: "تم تأكيد البريد الإلكتروني.",
    resetComplete: "تمت إعادة تعيين كلمة المرور. سجّل الدخول من جديد بكلمة المرور الجديدة.",
    sessionExpiredTitle: "انتهت جلستك",
    sessionExpiredBody: "انتهت صلاحية هذه الجلسة أو تم إلغاؤها. سجّل الدخول مرة أخرى للمتابعة.",
    unverifiedTitle: "تأكيد البريد مطلوب",
    unverifiedBody: "تظل تغييرات التعلّم المحمية غير متاحة حتى يتم تأكيد بريد الحساب.",
    sessionsTitle: "الجلسات النشطة",
    sessionsLoading: "جارٍ تحميل الجلسات النشطة…",
    noOtherSessions: "لا توجد جلسات نشطة أخرى.",
    currentSession: "الجلسة الحالية",
    lastUsed: "آخر استخدام",
    revokeOthers: "إلغاء الجلسات الأخرى",
    revokeAll: "إلغاء كل الجلسات",
    recentAuthTitle: "أكد هويتك",
    recentAuthBody: "هذا الإجراء الحساس يحتاج إلى تأكيد حديث لكلمة المرور.",
    reauthenticate: "تأكيد كلمة المرور",
    changePasswordTitle: "تغيير كلمة المرور",
    changePassword: "تغيير كلمة المرور",
    passwordChanged: "تم تغيير كلمة المرور وإلغاء الجلسات الأخرى وفق سياسة الخادم.",
    deleteTitle: "حذف الحساب",
    deleteWarning: "الحذف إجراء حساس ولا يمكن التراجع عنه من هذه الشاشة. اكتب DELETE كما هي ثم أكد كلمة المرور إذا طُلبت.",
    deleteConfirm: "اكتب DELETE للتأكيد",
    deleteAccount: "حذف حسابي",
    providerTitle: "مزودو تسجيل الدخول المرتبطون",
    providerGoogle: "Google",
    providerApple: "Apple",
    providerLogin: "المتابعة باستخدام",
    providerLink: "ربط",
    providerPendingTitle: "إعداد المزود قيد الانتظار",
    providerPendingBody: "أنشأ مُدرك طلب ربط آمنًا، لكن إعداد عميل المزود للإنتاج غير متاح بعد. تطبيق الويب لا يخترع أي معرّف أو سر.",
    providerError: "تعذر إكمال تسجيل الدخول عبر المزود.",
    accountSummaryUnavailable: "الجلسة صالحة. تفاصيل البريد والمزود تظهر فقط عندما يوفرها عقد الخادم الحالي.",
    working: "جارٍ التنفيذ…",
    signedIn: "تم تسجيل الدخول بأمان.",
    accountCreated: "تم إنشاء الحساب. أكد بريدك قبل تغييرات التعلّم المحمية.",
  },
  fr: {
    brandTagline: "Accès sécurisé à votre espace d’apprentissage MODRIK",
    loading: "Vérification de votre session…",
    signInTitle: "Se connecter",
    registerTitle: "Créer votre compte",
    recoveryTitle: "Récupérer votre mot de passe",
    verifyTitle: "Vérifier votre e-mail",
    resetTitle: "Définir un nouveau mot de passe",
    accountTitle: "Compte et sécurité",
    email: "E-mail",
    name: "Nom",
    password: "Mot de passe",
    currentPassword: "Mot de passe actuel",
    newPassword: "Nouveau mot de passe",
    token: "Jeton à usage unique",
    signIn: "Se connecter",
    register: "Créer le compte",
    sendRecovery: "Envoyer les instructions de récupération",
    verify: "Vérifier l’e-mail",
    resetPassword: "Réinitialiser le mot de passe",
    resendVerification: "Renvoyer la vérification",
    forgotPassword: "Mot de passe oublié ?",
    createAccount: "Créer un compte",
    backToLogin: "Retour à la connexion",
    useVerificationToken: "J’ai un jeton de vérification",
    useResetToken: "J’ai un jeton de réinitialisation",
    learning: "Espace d’apprentissage",
    account: "Compte et sessions",
    logout: "Se déconnecter",
    offlineTitle: "Vous êtes hors ligne",
    offlineBody: "Les modifications du compte nécessitent une connexion. Le comportement hors ligne de l’apprentissage reste inchangé.",
    retry: "Réessayer",
    genericError: "La demande n’a pas pu aboutir. Réessayez.",
    loginRejected: "L’e-mail ou le mot de passe n’a pas été accepté.",
    recoveryAccepted: "Si un compte éligible correspond à cette adresse, des instructions de récupération seront envoyées.",
    verificationAccepted: "Si la vérification est encore nécessaire, un nouveau message sera envoyé.",
    verified: "Vérification de l’e-mail terminée.",
    resetComplete: "Mot de passe réinitialisé. Reconnectez-vous avec le nouveau mot de passe.",
    sessionExpiredTitle: "Votre session est terminée",
    sessionExpiredBody: "Cette session a expiré ou a été révoquée. Reconnectez-vous pour continuer.",
    unverifiedTitle: "Vérification de l’e-mail requise",
    unverifiedBody: "Les modifications d’apprentissage protégées restent indisponibles jusqu’à la vérification de l’e-mail.",
    sessionsTitle: "Sessions actives",
    sessionsLoading: "Chargement des sessions actives…",
    noOtherSessions: "Aucune autre session active n’a été trouvée.",
    currentSession: "Session actuelle",
    lastUsed: "Dernière utilisation",
    revokeOthers: "Révoquer les autres sessions",
    revokeAll: "Révoquer toutes les sessions",
    recentAuthTitle: "Confirmez votre identité",
    recentAuthBody: "Cette action sensible exige une confirmation récente du mot de passe.",
    reauthenticate: "Confirmer le mot de passe",
    changePasswordTitle: "Modifier le mot de passe",
    changePassword: "Modifier le mot de passe",
    passwordChanged: "Mot de passe modifié. Les autres sessions ont été révoquées selon la politique Backend.",
    deleteTitle: "Supprimer le compte",
    deleteWarning: "La suppression est sensible et irréversible depuis cet écran. Saisissez exactement DELETE, puis confirmez votre mot de passe si demandé.",
    deleteConfirm: "Saisissez DELETE pour confirmer",
    deleteAccount: "Supprimer mon compte",
    providerTitle: "Fournisseurs de connexion associés",
    providerGoogle: "Google",
    providerApple: "Apple",
    providerLogin: "Continuer avec",
    providerLink: "Associer",
    providerPendingTitle: "Configuration du fournisseur en attente",
    providerPendingBody: "MODRIK a préparé une intention sécurisée, mais la configuration client de production n’est pas encore disponible. L’application Web n’invente aucun identifiant ni secret.",
    providerError: "La connexion via le fournisseur n’a pas pu aboutir.",
    accountSummaryUnavailable: "La session est valide. Les détails e-mail/fournisseur ne sont affichés que lorsque le contrat Backend actuel les fournit.",
    working: "Traitement…",
    signedIn: "Connexion sécurisée établie.",
    accountCreated: "Compte créé. Vérifiez votre e-mail avant les modifications d’apprentissage protégées.",
  },
};

export function localeDirection(locale: Locale): "rtl" | "ltr" {
  return locale === "ar" ? "rtl" : "ltr";
}
