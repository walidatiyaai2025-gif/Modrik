import 'models.dart';

class AuthCopy {
  const AuthCopy(this.locale);

  final ModrikLocale locale;

  static const Map<String, Map<ModrikLocale, String>> _copy = {
    'brand': {
      ModrikLocale.en: 'MODRIK | مُدرك',
      ModrikLocale.ar: 'مُدرك | MODRIK',
      ModrikLocale.fr: 'MODRIK | مُدرك',
    },
    'language': {
      ModrikLocale.en: 'Interface language',
      ModrikLocale.ar: 'لغة الواجهة',
      ModrikLocale.fr: 'Langue de l’interface',
    },
    'loading_auth': {
      ModrikLocale.en: 'Checking your secure session',
      ModrikLocale.ar: 'جارٍ التحقق من جلستك الآمنة',
      ModrikLocale.fr: 'Vérification de votre session sécurisée',
    },
    'login_title': {
      ModrikLocale.en: 'Sign in to MODRIK',
      ModrikLocale.ar: 'تسجيل الدخول إلى مُدرك',
      ModrikLocale.fr: 'Se connecter à MODRIK',
    },
    'login_body': {
      ModrikLocale.en: 'Your session is issued and controlled by the MODRIK backend.',
      ModrikLocale.ar: 'يصدر خادم مُدرك جلستك ويتحكم فيها.',
      ModrikLocale.fr: 'Votre session est émise et contrôlée par le serveur MODRIK.',
    },
    'email': {
      ModrikLocale.en: 'Email',
      ModrikLocale.ar: 'البريد الإلكتروني',
      ModrikLocale.fr: 'E-mail',
    },
    'password': {
      ModrikLocale.en: 'Password',
      ModrikLocale.ar: 'كلمة المرور',
      ModrikLocale.fr: 'Mot de passe',
    },
    'name': {
      ModrikLocale.en: 'Name',
      ModrikLocale.ar: 'الاسم',
      ModrikLocale.fr: 'Nom',
    },
    'sign_in': {
      ModrikLocale.en: 'Sign in',
      ModrikLocale.ar: 'تسجيل الدخول',
      ModrikLocale.fr: 'Se connecter',
    },
    'create_account': {
      ModrikLocale.en: 'Create account',
      ModrikLocale.ar: 'إنشاء حساب',
      ModrikLocale.fr: 'Créer un compte',
    },
    'register_title': {
      ModrikLocale.en: 'Create your account',
      ModrikLocale.ar: 'أنشئ حسابك',
      ModrikLocale.fr: 'Créer votre compte',
    },
    'register_body': {
      ModrikLocale.en: 'Use a password of at least 12 characters. Email verification follows registration.',
      ModrikLocale.ar: 'استخدم كلمة مرور من 12 حرفًا على الأقل. يلي التسجيل التحقق من البريد.',
      ModrikLocale.fr: 'Utilisez un mot de passe d’au moins 12 caractères. La vérification de l’e-mail suit.',
    },
    'forgot_password': {
      ModrikLocale.en: 'Forgot password?',
      ModrikLocale.ar: 'نسيت كلمة المرور؟',
      ModrikLocale.fr: 'Mot de passe oublié ?',
    },
    'recovery_title': {
      ModrikLocale.en: 'Recover your account',
      ModrikLocale.ar: 'استعادة حسابك',
      ModrikLocale.fr: 'Récupérer votre compte',
    },
    'recovery_body': {
      ModrikLocale.en: 'For privacy, MODRIK returns the same result whether or not an eligible account exists.',
      ModrikLocale.ar: 'لحماية الخصوصية، يعرض مُدرك النتيجة نفسها سواء وُجد حساب مؤهل أم لا.',
      ModrikLocale.fr: 'Pour protéger votre vie privée, MODRIK renvoie le même résultat qu’un compte existe ou non.',
    },
    'send_recovery': {
      ModrikLocale.en: 'Send recovery request',
      ModrikLocale.ar: 'إرسال طلب الاستعادة',
      ModrikLocale.fr: 'Envoyer la demande',
    },
    'reset_title': {
      ModrikLocale.en: 'Set a new password',
      ModrikLocale.ar: 'تعيين كلمة مرور جديدة',
      ModrikLocale.fr: 'Définir un nouveau mot de passe',
    },
    'reset_token': {
      ModrikLocale.en: 'Recovery token',
      ModrikLocale.ar: 'رمز الاستعادة',
      ModrikLocale.fr: 'Jeton de récupération',
    },
    'new_password': {
      ModrikLocale.en: 'New password',
      ModrikLocale.ar: 'كلمة المرور الجديدة',
      ModrikLocale.fr: 'Nouveau mot de passe',
    },
    'reset_password': {
      ModrikLocale.en: 'Reset password',
      ModrikLocale.ar: 'إعادة تعيين كلمة المرور',
      ModrikLocale.fr: 'Réinitialiser le mot de passe',
    },
    'verification_title': {
      ModrikLocale.en: 'Verify your email',
      ModrikLocale.ar: 'تحقق من بريدك الإلكتروني',
      ModrikLocale.fr: 'Vérifiez votre e-mail',
    },
    'verification_body': {
      ModrikLocale.en: 'Enter the one-time verification token. Protected learning mutations stay backend-blocked until verification succeeds.',
      ModrikLocale.ar: 'أدخل رمز التحقق لمرة واحدة. تبقى تغييرات التعلّم المحمية محجوبة من الخادم حتى نجاح التحقق.',
      ModrikLocale.fr: 'Saisissez le jeton à usage unique. Les mutations d’apprentissage protégées restent bloquées côté serveur.',
    },
    'verification_token': {
      ModrikLocale.en: 'Verification token',
      ModrikLocale.ar: 'رمز التحقق',
      ModrikLocale.fr: 'Jeton de vérification',
    },
    'verify_email': {
      ModrikLocale.en: 'Verify email',
      ModrikLocale.ar: 'تحقق من البريد',
      ModrikLocale.fr: 'Vérifier l’e-mail',
    },
    'resend_verification': {
      ModrikLocale.en: 'Resend verification',
      ModrikLocale.ar: 'إعادة إرسال التحقق',
      ModrikLocale.fr: 'Renvoyer la vérification',
    },
    'back_to_login': {
      ModrikLocale.en: 'Back to sign in',
      ModrikLocale.ar: 'العودة لتسجيل الدخول',
      ModrikLocale.fr: 'Retour à la connexion',
    },
    'or_continue_with': {
      ModrikLocale.en: 'Or continue with',
      ModrikLocale.ar: 'أو المتابعة باستخدام',
      ModrikLocale.fr: 'Ou continuer avec',
    },
    'google': {
      ModrikLocale.en: 'Google',
      ModrikLocale.ar: 'Google',
      ModrikLocale.fr: 'Google',
    },
    'apple': {
      ModrikLocale.en: 'Apple',
      ModrikLocale.ar: 'Apple',
      ModrikLocale.fr: 'Apple',
    },
    'account': {
      ModrikLocale.en: 'Account and sessions',
      ModrikLocale.ar: 'الحساب والجلسات',
      ModrikLocale.fr: 'Compte et sessions',
    },
    'close': {
      ModrikLocale.en: 'Back to learning',
      ModrikLocale.ar: 'العودة إلى التعلّم',
      ModrikLocale.fr: 'Retour à l’apprentissage',
    },
    'account_title': {
      ModrikLocale.en: 'Account security',
      ModrikLocale.ar: 'أمان الحساب',
      ModrikLocale.fr: 'Sécurité du compte',
    },
    'account_body': {
      ModrikLocale.en: 'Sessions and provider links remain backend-authoritative. Tokens are never displayed here.',
      ModrikLocale.ar: 'تبقى الجلسات وروابط مزودي الهوية تحت سلطة الخادم. لا تُعرض الرموز هنا مطلقًا.',
      ModrikLocale.fr: 'Les sessions et liens de fournisseurs restent sous l’autorité du serveur. Les jetons ne sont jamais affichés.',
    },
    'sessions': {
      ModrikLocale.en: 'Active sessions',
      ModrikLocale.ar: 'الجلسات النشطة',
      ModrikLocale.fr: 'Sessions actives',
    },
    'session_current': {
      ModrikLocale.en: 'Current session',
      ModrikLocale.ar: 'الجلسة الحالية',
      ModrikLocale.fr: 'Session actuelle',
    },
    'session_other': {
      ModrikLocale.en: 'Other session',
      ModrikLocale.ar: 'جلسة أخرى',
      ModrikLocale.fr: 'Autre session',
    },
    'session_expires': {
      ModrikLocale.en: 'Expires',
      ModrikLocale.ar: 'تنتهي',
      ModrikLocale.fr: 'Expire',
    },
    'no_sessions': {
      ModrikLocale.en: 'No active session list is available yet.',
      ModrikLocale.ar: 'لا توجد قائمة جلسات نشطة متاحة بعد.',
      ModrikLocale.fr: 'Aucune liste de sessions actives n’est disponible.',
    },
    'refresh_sessions': {
      ModrikLocale.en: 'Refresh sessions',
      ModrikLocale.ar: 'تحديث الجلسات',
      ModrikLocale.fr: 'Actualiser les sessions',
    },
    'revoke_others': {
      ModrikLocale.en: 'Revoke other sessions',
      ModrikLocale.ar: 'إلغاء الجلسات الأخرى',
      ModrikLocale.fr: 'Révoquer les autres sessions',
    },
    'revoke_all': {
      ModrikLocale.en: 'Revoke all sessions',
      ModrikLocale.ar: 'إلغاء كل الجلسات',
      ModrikLocale.fr: 'Révoquer toutes les sessions',
    },
    'logout': {
      ModrikLocale.en: 'Log out of this device',
      ModrikLocale.ar: 'تسجيل الخروج من هذا الجهاز',
      ModrikLocale.fr: 'Se déconnecter de cet appareil',
    },
    'recent_auth_title': {
      ModrikLocale.en: 'Confirm your password',
      ModrikLocale.ar: 'أكد كلمة المرور',
      ModrikLocale.fr: 'Confirmez votre mot de passe',
    },
    'recent_auth_body': {
      ModrikLocale.en: 'Sensitive actions require recent backend authentication.',
      ModrikLocale.ar: 'تتطلب الإجراءات الحساسة مصادقة حديثة من الخادم.',
      ModrikLocale.fr: 'Les actions sensibles exigent une authentification récente côté serveur.',
    },
    'reauthenticate': {
      ModrikLocale.en: 'Confirm identity',
      ModrikLocale.ar: 'تأكيد الهوية',
      ModrikLocale.fr: 'Confirmer l’identité',
    },
    'change_password': {
      ModrikLocale.en: 'Change password',
      ModrikLocale.ar: 'تغيير كلمة المرور',
      ModrikLocale.fr: 'Changer le mot de passe',
    },
    'current_password': {
      ModrikLocale.en: 'Current password',
      ModrikLocale.ar: 'كلمة المرور الحالية',
      ModrikLocale.fr: 'Mot de passe actuel',
    },
    'provider_management': {
      ModrikLocale.en: 'Google and Apple',
      ModrikLocale.ar: 'Google وApple',
      ModrikLocale.fr: 'Google et Apple',
    },
    'provider_management_body': {
      ModrikLocale.en: 'Linking uses backend one-time state/nonce intents. Provider transport stays unavailable until owner configuration exists.',
      ModrikLocale.ar: 'يستخدم الربط حالة وnonce لمرة واحدة من الخادم. يظل اتصال المزود غير متاح حتى اكتمال إعداد المالك.',
      ModrikLocale.fr: 'La liaison utilise les intentions state/nonce du serveur. Le transport fournisseur reste indisponible jusqu’à configuration.',
    },
    'link_google': {
      ModrikLocale.en: 'Link Google',
      ModrikLocale.ar: 'ربط Google',
      ModrikLocale.fr: 'Lier Google',
    },
    'unlink_google': {
      ModrikLocale.en: 'Unlink Google',
      ModrikLocale.ar: 'إلغاء ربط Google',
      ModrikLocale.fr: 'Délier Google',
    },
    'link_apple': {
      ModrikLocale.en: 'Link Apple',
      ModrikLocale.ar: 'ربط Apple',
      ModrikLocale.fr: 'Lier Apple',
    },
    'unlink_apple': {
      ModrikLocale.en: 'Unlink Apple',
      ModrikLocale.ar: 'إلغاء ربط Apple',
      ModrikLocale.fr: 'Délier Apple',
    },
    'delete_account_title': {
      ModrikLocale.en: 'Delete account',
      ModrikLocale.ar: 'حذف الحساب',
      ModrikLocale.fr: 'Supprimer le compte',
    },
    'delete_account_body': {
      ModrikLocale.en: 'This logically deletes the account, revokes every session and preserves only backend-required historical references. Type DELETE to confirm.',
      ModrikLocale.ar: 'يحذف هذا الحساب منطقيًا ويلغي كل الجلسات ويحفظ فقط المراجع التاريخية المطلوبة من الخادم. اكتب DELETE للتأكيد.',
      ModrikLocale.fr: 'Cette action supprime logiquement le compte, révoque toutes les sessions et conserve seulement les références historiques requises. Saisissez DELETE.',
    },
    'delete_confirmation': {
      ModrikLocale.en: 'Type DELETE',
      ModrikLocale.ar: 'اكتب DELETE',
      ModrikLocale.fr: 'Saisissez DELETE',
    },
    'delete_account': {
      ModrikLocale.en: 'Delete my account',
      ModrikLocale.ar: 'حذف حسابي',
      ModrikLocale.fr: 'Supprimer mon compte',
    },
    'retry': {
      ModrikLocale.en: 'Retry',
      ModrikLocale.ar: 'إعادة المحاولة',
      ModrikLocale.fr: 'Réessayer',
    },
    'api_not_configured': {
      ModrikLocale.en: 'This build has no authorized MODRIK API endpoint.',
      ModrikLocale.ar: 'لا يحتوي هذا الإصدار على نقطة API معتمدة لمُدرك.',
      ModrikLocale.fr: 'Cette version ne contient aucun point API MODRIK autorisé.',
    },
    'MOBILE_API_NOT_CONFIGURED': {
      ModrikLocale.en: 'The MODRIK API is not configured for this build.',
      ModrikLocale.ar: 'لم يتم إعداد API مُدرك لهذا الإصدار.',
      ModrikLocale.fr: 'L’API MODRIK n’est pas configurée pour cette version.',
    },
    'MOBILE_SECURE_STORAGE_UNAVAILABLE': {
      ModrikLocale.en: 'Secure session storage is unavailable. MODRIK will not fall back to plaintext token storage.',
      ModrikLocale.ar: 'التخزين الآمن للجلسة غير متاح. لن يستخدم مُدرك تخزينًا نصيًا غير آمن للرمز.',
      ModrikLocale.fr: 'Le stockage sécurisé de session est indisponible. MODRIK ne stockera pas le jeton en clair.',
    },
    'MOBILE_SECURE_STORAGE_INVALID': {
      ModrikLocale.en: 'The saved secure session could not be read. Sign in again after retrying secure storage.',
      ModrikLocale.ar: 'تعذر قراءة الجلسة الآمنة المحفوظة. سجّل الدخول مجددًا بعد إعادة المحاولة.',
      ModrikLocale.fr: 'La session sécurisée enregistrée est illisible. Reconnectez-vous après avoir réessayé.',
    },
    'session_expired': {
      ModrikLocale.en: 'Your session expired or was revoked. Sign in again. Local learning data was not silently synced under another account.',
      ModrikLocale.ar: 'انتهت جلستك أو أُلغيت. سجّل الدخول مجددًا. لم تتم مزامنة بيانات التعلّم المحلية بصمت تحت حساب آخر.',
      ModrikLocale.fr: 'Votre session a expiré ou a été révoquée. Reconnectez-vous. Les données locales n’ont pas été synchronisées sous un autre compte.',
    },
    'offline_saved_session': {
      ModrikLocale.en: 'The server is unreachable. Using the saved secure session only to expose already-cached learning data; writes wait for reconnection.',
      ModrikLocale.ar: 'يتعذر الوصول إلى الخادم. تُستخدم الجلسة الآمنة المحفوظة فقط لعرض بيانات التعلّم المخزنة؛ تنتظر الكتابات إعادة الاتصال.',
      ModrikLocale.fr: 'Le serveur est inaccessible. La session sécurisée enregistrée permet uniquement l’accès au contenu déjà en cache; les écritures attendent.',
    },
    'auth_offline': {
      ModrikLocale.en: 'Account operations need a connection. Cached learning can remain available.',
      ModrikLocale.ar: 'تحتاج عمليات الحساب إلى اتصال. يمكن أن يبقى التعلّم المخزن متاحًا.',
      ModrikLocale.fr: 'Les opérations de compte nécessitent une connexion. Le contenu en cache peut rester disponible.',
    },
    'auth_offline_no_session': {
      ModrikLocale.en: 'You are offline and no validated saved session is available. Sign in when the connection returns.',
      ModrikLocale.ar: 'أنت غير متصل ولا توجد جلسة محفوظة معتمدة. سجّل الدخول عند عودة الاتصال.',
      ModrikLocale.fr: 'Vous êtes hors ligne et aucune session enregistrée validée n’est disponible. Reconnectez-vous plus tard.',
    },
    'verification_offline': {
      ModrikLocale.en: 'Email verification needs a connection before protected learning can continue.',
      ModrikLocale.ar: 'يحتاج التحقق من البريد إلى اتصال قبل متابعة التعلّم المحمي.',
      ModrikLocale.fr: 'La vérification de l’e-mail nécessite une connexion avant de poursuivre.',
    },
    'verification_required': {
      ModrikLocale.en: 'Verify your email before protected learning actions.',
      ModrikLocale.ar: 'تحقق من بريدك قبل إجراءات التعلّم المحمية.',
      ModrikLocale.fr: 'Vérifiez votre e-mail avant les actions d’apprentissage protégées.',
    },
    'verification_resent': {
      ModrikLocale.en: 'A new verification delivery was accepted.',
      ModrikLocale.ar: 'تم قبول إرسال تحقق جديد.',
      ModrikLocale.fr: 'Un nouvel envoi de vérification a été accepté.',
    },
    'verification_complete': {
      ModrikLocale.en: 'Email verified. Your authenticated learning session can continue.',
      ModrikLocale.ar: 'تم التحقق من البريد. يمكنك متابعة جلسة التعلّم المصادق عليها.',
      ModrikLocale.fr: 'E-mail vérifié. Votre session d’apprentissage authentifiée peut continuer.',
    },
    'verification_complete_sign_in': {
      ModrikLocale.en: 'Email verified. Sign in to continue.',
      ModrikLocale.ar: 'تم التحقق من البريد. سجّل الدخول للمتابعة.',
      ModrikLocale.fr: 'E-mail vérifié. Connectez-vous pour continuer.',
    },
    'registration_complete': {
      ModrikLocale.en: 'Account created.',
      ModrikLocale.ar: 'تم إنشاء الحساب.',
      ModrikLocale.fr: 'Compte créé.',
    },
    'login_complete': {
      ModrikLocale.en: 'Signed in securely.',
      ModrikLocale.ar: 'تم تسجيل الدخول بأمان.',
      ModrikLocale.fr: 'Connexion sécurisée réussie.',
    },
    'recovery_accepted': {
      ModrikLocale.en: 'If the account is eligible, recovery delivery has been accepted. Enter the token when available.',
      ModrikLocale.ar: 'إذا كان الحساب مؤهلًا فقد تم قبول إرسال الاستعادة. أدخل الرمز عند توفره.',
      ModrikLocale.fr: 'Si le compte est éligible, la récupération a été acceptée. Saisissez le jeton lorsqu’il est disponible.',
    },
    'password_reset_complete': {
      ModrikLocale.en: 'Password reset completed. Existing sessions were revoked; sign in again.',
      ModrikLocale.ar: 'اكتملت إعادة تعيين كلمة المرور وأُلغيت الجلسات الحالية. سجّل الدخول مجددًا.',
      ModrikLocale.fr: 'Mot de passe réinitialisé. Les sessions existantes ont été révoquées; reconnectez-vous.',
    },
    'recent_auth_complete': {
      ModrikLocale.en: 'Recent authentication refreshed.',
      ModrikLocale.ar: 'تم تحديث المصادقة الحديثة.',
      ModrikLocale.fr: 'Authentification récente actualisée.',
    },
    'password_changed': {
      ModrikLocale.en: 'Password changed. Other sessions were revoked by the backend.',
      ModrikLocale.ar: 'تم تغيير كلمة المرور. ألغى الخادم الجلسات الأخرى.',
      ModrikLocale.fr: 'Mot de passe modifié. Les autres sessions ont été révoquées par le serveur.',
    },
    'sessions_updated': {
      ModrikLocale.en: 'Session list updated.',
      ModrikLocale.ar: 'تم تحديث قائمة الجلسات.',
      ModrikLocale.fr: 'Liste des sessions actualisée.',
    },
    'other_sessions_revoked': {
      ModrikLocale.en: 'Other sessions were revoked.',
      ModrikLocale.ar: 'تم إلغاء الجلسات الأخرى.',
      ModrikLocale.fr: 'Les autres sessions ont été révoquées.',
    },
    'all_sessions_revoked': {
      ModrikLocale.en: 'All sessions were revoked. Sign in again.',
      ModrikLocale.ar: 'تم إلغاء كل الجلسات. سجّل الدخول مجددًا.',
      ModrikLocale.fr: 'Toutes les sessions ont été révoquées. Reconnectez-vous.',
    },
    'logout_complete': {
      ModrikLocale.en: 'Signed out from this device.',
      ModrikLocale.ar: 'تم تسجيل الخروج من هذا الجهاز.',
      ModrikLocale.fr: 'Déconnexion de cet appareil effectuée.',
    },
    'account_deleted': {
      ModrikLocale.en: 'Account deletion completed and sessions were revoked.',
      ModrikLocale.ar: 'اكتمل حذف الحساب وتم إلغاء الجلسات.',
      ModrikLocale.fr: 'Suppression du compte terminée et sessions révoquées.',
    },
    'provider_login_complete': {
      ModrikLocale.en: 'Provider sign-in completed through the backend session boundary.',
      ModrikLocale.ar: 'اكتمل تسجيل الدخول عبر المزود من خلال حدود جلسة الخادم.',
      ModrikLocale.fr: 'Connexion fournisseur terminée via la session du serveur.',
    },
    'provider_linked': {
      ModrikLocale.en: 'Provider linked to this MODRIK account.',
      ModrikLocale.ar: 'تم ربط المزود بحساب مُدرك هذا.',
      ModrikLocale.fr: 'Fournisseur lié à ce compte MODRIK.',
    },
    'provider_unlinked': {
      ModrikLocale.en: 'Provider unlinked.',
      ModrikLocale.ar: 'تم إلغاء ربط المزود.',
      ModrikLocale.fr: 'Fournisseur délié.',
    },
    'local_sync_required_before_sign_out': {
      ModrikLocale.en: 'Sync or resolve local answer changes before ending this session so Issue #14 operations are not discarded.',
      ModrikLocale.ar: 'زامن أو عالج تغييرات الإجابات المحلية قبل إنهاء الجلسة حتى لا تُفقد عمليات المزامنة المعتمدة.',
      ModrikLocale.fr: 'Synchronisez ou résolvez les réponses locales avant de terminer la session afin de ne pas perdre les opérations Issue #14.',
    },
    'INVALID_CREDENTIALS': {
      ModrikLocale.en: 'Email or password is not valid.',
      ModrikLocale.ar: 'البريد أو كلمة المرور غير صحيحة.',
      ModrikLocale.fr: 'E-mail ou mot de passe incorrect.',
    },
    'EMAIL_UNAVAILABLE': {
      ModrikLocale.en: 'That email cannot be registered.',
      ModrikLocale.ar: 'لا يمكن تسجيل هذا البريد.',
      ModrikLocale.fr: 'Cet e-mail ne peut pas être enregistré.',
    },
    'TOKEN_INVALID_OR_EXPIRED': {
      ModrikLocale.en: 'That one-time token is invalid or expired.',
      ModrikLocale.ar: 'رمز الاستخدام لمرة واحدة غير صالح أو منتهي.',
      ModrikLocale.fr: 'Ce jeton à usage unique est invalide ou expiré.',
    },
    'EMAIL_VERIFICATION_REQUIRED': {
      ModrikLocale.en: 'Email verification is required.',
      ModrikLocale.ar: 'يلزم التحقق من البريد الإلكتروني.',
      ModrikLocale.fr: 'La vérification de l’e-mail est requise.',
    },
    'RECENT_AUTHENTICATION_REQUIRED': {
      ModrikLocale.en: 'Confirm your password first, then retry the sensitive action.',
      ModrikLocale.ar: 'أكد كلمة المرور أولًا ثم أعد محاولة الإجراء الحساس.',
      ModrikLocale.fr: 'Confirmez d’abord votre mot de passe puis réessayez l’action sensible.',
    },
    'PROVIDER_CONFIGURATION_PENDING': {
      ModrikLocale.en: 'Google/Apple production configuration is not available yet. No fallback identity system is used.',
      ModrikLocale.ar: 'إعداد Google/Apple الإنتاجي غير متاح بعد. لا يُستخدم نظام هوية بديل.',
      ModrikLocale.fr: 'La configuration Google/Apple de production n’est pas encore disponible. Aucun système d’identité de secours n’est utilisé.',
    },
    'PROVIDER_LINK_REQUIRED': {
      ModrikLocale.en: 'This provider identity requires explicit linking after you sign in to the existing account.',
      ModrikLocale.ar: 'تتطلب هوية المزود ربطًا صريحًا بعد تسجيل الدخول إلى الحساب الحالي.',
      ModrikLocale.fr: 'Cette identité fournisseur exige une liaison explicite après connexion au compte existant.',
    },
    'PROVIDER_IDENTITY_CONFLICT': {
      ModrikLocale.en: 'That provider identity is already bound to another MODRIK account.',
      ModrikLocale.ar: 'هوية المزود مرتبطة بالفعل بحساب مُدرك آخر.',
      ModrikLocale.fr: 'Cette identité fournisseur est déjà liée à un autre compte MODRIK.',
    },
    'PROVIDER_IDENTITY_NOT_FOUND': {
      ModrikLocale.en: 'No active link exists for that provider.',
      ModrikLocale.ar: 'لا يوجد ربط نشط لهذا المزود.',
      ModrikLocale.fr: 'Aucune liaison active pour ce fournisseur.',
    },
    'LAST_RECOVERY_IDENTITY': {
      ModrikLocale.en: 'That provider cannot be unlinked because it is the last usable recovery identity.',
      ModrikLocale.ar: 'لا يمكن إلغاء ربط المزود لأنه آخر وسيلة استعادة متاحة.',
      ModrikLocale.fr: 'Ce fournisseur ne peut pas être délié car il s’agit de la dernière identité de récupération.',
    },
    'TOO_MANY_ATTEMPTS': {
      ModrikLocale.en: 'Too many attempts. Try again later.',
      ModrikLocale.ar: 'محاولات كثيرة جدًا. حاول لاحقًا.',
      ModrikLocale.fr: 'Trop de tentatives. Réessayez plus tard.',
    },
    'VALIDATION_FAILED': {
      ModrikLocale.en: 'Check the entered values and try again.',
      ModrikLocale.ar: 'راجع القيم المدخلة وحاول مجددًا.',
      ModrikLocale.fr: 'Vérifiez les valeurs saisies et réessayez.',
    },
    'unexpected_auth_error': {
      ModrikLocale.en: 'An unexpected authentication error occurred. No credential was exposed.',
      ModrikLocale.ar: 'حدث خطأ مصادقة غير متوقع. لم يتم كشف أي بيانات اعتماد.',
      ModrikLocale.fr: 'Une erreur d’authentification inattendue est survenue. Aucun identifiant n’a été exposé.',
    },
  };

  String t(String key) =>
      _copy[key]?[locale] ??
      _copy[key]?[ModrikLocale.en] ??
      key;
}
