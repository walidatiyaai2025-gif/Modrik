import 'models.dart';

class MobileCopy {
  const MobileCopy(this.locale);

  final ModrikLocale locale;

  static const Map<String, Map<ModrikLocale, String>> _copy = {
    'brand': {
      ModrikLocale.en: 'MODRIK | مُدرك',
      ModrikLocale.ar: 'مُدرك | MODRIK',
      ModrikLocale.fr: 'MODRIK | مُدرك',
    },
    'dashboard': {
      ModrikLocale.en: 'Dashboard',
      ModrikLocale.ar: 'الرئيسية',
      ModrikLocale.fr: 'Tableau de bord',
    },
    'study': {
      ModrikLocale.en: 'Study',
      ModrikLocale.ar: 'المذاكرة',
      ModrikLocale.fr: 'Étude',
    },
    'practice': {
      ModrikLocale.en: 'Practice',
      ModrikLocale.ar: 'التدريب',
      ModrikLocale.fr: 'Exercice',
    },
    'progress': {
      ModrikLocale.en: 'Progress',
      ModrikLocale.ar: 'التقدّم',
      ModrikLocale.fr: 'Progression',
    },
    'loading_title': {
      ModrikLocale.en: 'Loading your learning space',
      ModrikLocale.ar: 'جارٍ تحميل مساحة التعلّم',
      ModrikLocale.fr: 'Chargement de votre espace',
    },
    'loading_body': {
      ModrikLocale.en: 'Checking your academic context and saved learning data.',
      ModrikLocale.ar: 'يتم التحقق من سياقك الأكاديمي وبيانات التعلّم المحفوظة.',
      ModrikLocale.fr: 'Vérification de votre contexte académique et des données enregistrées.',
    },
    'empty_title': {
      ModrikLocale.en: 'Nothing is assigned yet',
      ModrikLocale.ar: 'لا يوجد محتوى مخصص بعد',
      ModrikLocale.fr: 'Aucun contenu assigné',
    },
    'empty_body': {
      ModrikLocale.en: 'Published learning content will appear here when the backend assigns it.',
      ModrikLocale.ar: 'سيظهر المحتوى المنشور هنا عندما يخصصه الخادم.',
      ModrikLocale.fr: 'Le contenu publié apparaîtra ici quand le serveur l’assignera.',
    },
    'error_title': {
      ModrikLocale.en: 'Learning service unavailable',
      ModrikLocale.ar: 'خدمة التعلّم غير متاحة',
      ModrikLocale.fr: 'Service d’apprentissage indisponible',
    },
    'error_body': {
      ModrikLocale.en: 'Your local answers are kept. Try the request again.',
      ModrikLocale.ar: 'إجاباتك المحلية محفوظة. حاول الطلب مرة أخرى.',
      ModrikLocale.fr: 'Vos réponses locales sont conservées. Réessayez.',
    },
    'permission_title': {
      ModrikLocale.en: 'Connection configuration required',
      ModrikLocale.ar: 'يلزم إعداد الاتصال',
      ModrikLocale.fr: 'Configuration de connexion requise',
    },
    'permission_body': {
      ModrikLocale.en: 'This build has no authorized learning endpoint or the session is not permitted.',
      ModrikLocale.ar: 'هذا الإصدار لا يحتوي على نقطة تعلّم مصرح بها أو أن الجلسة غير مسموح بها.',
      ModrikLocale.fr: 'Cette version n’a pas de service autorisé ou la session n’est pas permise.',
    },
    'offline_title': {
      ModrikLocale.en: 'You are offline',
      ModrikLocale.ar: 'أنت غير متصل',
      ModrikLocale.fr: 'Vous êtes hors ligne',
    },
    'offline_cached': {
      ModrikLocale.en: 'Using the exact learning snapshot saved on this device. Changes will wait for the canonical sync service.',
      ModrikLocale.ar: 'يتم استخدام نسخة التعلّم المحفوظة على الجهاز كما هي. ستنتظر التغييرات خدمة المزامنة المعتمدة.',
      ModrikLocale.fr: 'Utilisation de la copie exacte enregistrée. Les changements attendront le service de synchronisation officiel.',
    },
    'offline_no_downloads': {
      ModrikLocale.en: 'No downloaded learning content is available on this device yet.',
      ModrikLocale.ar: 'لا يوجد محتوى تعلّم محمّل على هذا الجهاز بعد.',
      ModrikLocale.fr: 'Aucun contenu téléchargé n’est encore disponible.',
    },
    'stale': {
      ModrikLocale.en: 'Saved content may be out of date.',
      ModrikLocale.ar: 'قد يكون المحتوى المحفوظ قديمًا.',
      ModrikLocale.fr: 'Le contenu enregistré peut être ancien.',
    },
    'retry': {
      ModrikLocale.en: 'Retry',
      ModrikLocale.ar: 'إعادة المحاولة',
      ModrikLocale.fr: 'Réessayer',
    },
    'language': {
      ModrikLocale.en: 'Interface language',
      ModrikLocale.ar: 'لغة الواجهة',
      ModrikLocale.fr: 'Langue de l’interface',
    },
    'onboarding_title': {
      ModrikLocale.en: 'Set your academic context',
      ModrikLocale.ar: 'إعداد السياق الأكاديمي',
      ModrikLocale.fr: 'Configurer votre contexte académique',
    },
    'onboarding_body': {
      ModrikLocale.en: 'Your academic track is confirmed by the backend. The app does not choose or change academic policy itself.',
      ModrikLocale.ar: 'يؤكد الخادم مسارك الأكاديمي. التطبيق لا يختار أو يغيّر السياسة الأكاديمية بنفسه.',
      ModrikLocale.fr: 'Le parcours est confirmé par le serveur. L’application ne décide pas de la politique académique.',
    },
    'assigned_track': {
      ModrikLocale.en: 'Backend-assigned track',
      ModrikLocale.ar: 'المسار المخصص من الخادم',
      ModrikLocale.fr: 'Parcours assigné par le serveur',
    },
    'track_missing': {
      ModrikLocale.en: 'No academic track was supplied to this build. Connect through an authorized onboarding entry point.',
      ModrikLocale.ar: 'لم يتم تزويد هذا الإصدار بمسار أكاديمي. استخدم نقطة إعداد مصرح بها.',
      ModrikLocale.fr: 'Aucun parcours n’a été fourni. Utilisez un point d’entrée autorisé.',
    },
    'confirm_context': {
      ModrikLocale.en: 'Confirm academic context',
      ModrikLocale.ar: 'تأكيد السياق الأكاديمي',
      ModrikLocale.fr: 'Confirmer le contexte',
    },
    'academic_context': {
      ModrikLocale.en: 'Academic context',
      ModrikLocale.ar: 'السياق الأكاديمي',
      ModrikLocale.fr: 'Contexte académique',
    },
    'active': {
      ModrikLocale.en: 'Active',
      ModrikLocale.ar: 'نشط',
      ModrikLocale.fr: 'Actif',
    },
    'year_level': {
      ModrikLocale.en: 'Year level',
      ModrikLocale.ar: 'السنة الدراسية',
      ModrikLocale.fr: 'Niveau',
    },
    'offline_learning': {
      ModrikLocale.en: 'Offline learning',
      ModrikLocale.ar: 'التعلّم دون اتصال',
      ModrikLocale.fr: 'Apprentissage hors ligne',
    },
    'download_ready': {
      ModrikLocale.en: 'Current lesson is cached for offline study.',
      ModrikLocale.ar: 'الدرس الحالي محفوظ للمذاكرة دون اتصال.',
      ModrikLocale.fr: 'La leçon actuelle est enregistrée pour une étude hors ligne.',
    },
    'no_download': {
      ModrikLocale.en: 'No lesson has been cached yet.',
      ModrikLocale.ar: 'لم يتم حفظ درس بعد.',
      ModrikLocale.fr: 'Aucune leçon n’est encore enregistrée.',
    },
    'pending_changes': {
      ModrikLocale.en: 'Pending changes',
      ModrikLocale.ar: 'تغييرات معلّقة',
      ModrikLocale.fr: 'Changements en attente',
    },
    'pending_count': {
      ModrikLocale.en: 'operation(s) waiting for sync',
      ModrikLocale.ar: 'عملية بانتظار المزامنة',
      ModrikLocale.fr: 'opération(s) en attente',
    },
    'sync_now': {
      ModrikLocale.en: 'Sync pending changes',
      ModrikLocale.ar: 'مزامنة التغييرات',
      ModrikLocale.fr: 'Synchroniser',
    },
    'lesson_empty': {
      ModrikLocale.en: 'No published lesson is assigned to this build.',
      ModrikLocale.ar: 'لا يوجد درس منشور مخصص لهذا الإصدار.',
      ModrikLocale.fr: 'Aucune leçon publiée n’est assignée.',
    },
    'fixture_note': {
      ModrikLocale.en: 'Only backend-published content is shown.',
      ModrikLocale.ar: 'يظهر فقط المحتوى المنشور من الخادم.',
      ModrikLocale.fr: 'Seul le contenu publié par le serveur est affiché.',
    },
    'start_practice': {
      ModrikLocale.en: 'Start new practice',
      ModrikLocale.ar: 'ابدأ تدريبًا جديدًا',
      ModrikLocale.fr: 'Commencer un exercice',
    },
    'resume_practice': {
      ModrikLocale.en: 'Resume this attempt',
      ModrikLocale.ar: 'استئناف هذه المحاولة',
      ModrikLocale.fr: 'Reprendre cette tentative',
    },
    'attempt_empty': {
      ModrikLocale.en: 'Start practice while connected. A resumed attempt always keeps the backend question order.',
      ModrikLocale.ar: 'ابدأ التدريب أثناء الاتصال. المحاولة المستأنفة تحتفظ دائمًا بترتيب أسئلة الخادم.',
      ModrikLocale.fr: 'Démarrez en ligne. Une tentative reprise conserve toujours l’ordre du serveur.',
    },
    'answer_every_question': {
      ModrikLocale.en: 'Answer every question before submitting.',
      ModrikLocale.ar: 'أجب عن جميع الأسئلة قبل الإرسال.',
      ModrikLocale.fr: 'Répondez à toutes les questions avant l’envoi.',
    },
    'submit': {
      ModrikLocale.en: 'Save answers and submit',
      ModrikLocale.ar: 'احفظ الإجابات وأرسل',
      ModrikLocale.fr: 'Enregistrer et envoyer',
    },
    'backend_result': {
      ModrikLocale.en: 'Backend result',
      ModrikLocale.ar: 'نتيجة الخادم',
      ModrikLocale.fr: 'Résultat du serveur',
    },
    'score_authority': {
      ModrikLocale.en: 'Scoring is calculated only by the backend.',
      ModrikLocale.ar: 'يتم احتساب النتيجة بواسطة الخادم فقط.',
      ModrikLocale.fr: 'Le score est calculé uniquement par le serveur.',
    },
    'progress_empty': {
      ModrikLocale.en: 'Complete learning activity to receive backend-calculated progress.',
      ModrikLocale.ar: 'أكمل نشاط التعلّم لعرض التقدّم المحسوب من الخادم.',
      ModrikLocale.fr: 'Terminez une activité pour recevoir la progression calculée par le serveur.',
    },
    'mastery': {
      ModrikLocale.en: 'Mastery',
      ModrikLocale.ar: 'الإتقان',
      ModrikLocale.fr: 'Maîtrise',
    },
    'new_attempt_requires_connection': {
      ModrikLocale.en: 'A new attempt must be created by the backend. Reconnect first.',
      ModrikLocale.ar: 'يجب إنشاء المحاولة الجديدة بواسطة الخادم. أعد الاتصال أولًا.',
      ModrikLocale.fr: 'Une nouvelle tentative doit être créée par le serveur. Reconnectez-vous.',
    },
    'resume_cached_attempt': {
      ModrikLocale.en: 'Resuming the exact saved attempt snapshot.',
      ModrikLocale.ar: 'يتم استئناف نسخة المحاولة المحفوظة كما هي.',
      ModrikLocale.fr: 'Reprise de la copie exacte de la tentative enregistrée.',
    },
    'submit_requires_sync': {
      ModrikLocale.en: 'Answers are queued locally. Reconnect and sync before backend submission.',
      ModrikLocale.ar: 'الإجابات معلّقة محليًا. أعد الاتصال والمزامنة قبل الإرسال للخادم.',
      ModrikLocale.fr: 'Les réponses sont en attente localement. Reconnectez-vous et synchronisez avant l’envoi.',
    },
    'sync_contract_pending': {
      ModrikLocale.en: 'Pending operations are safe locally. The canonical Issue #14 sync transport is not available in this branch yet.',
      ModrikLocale.ar: 'العمليات المعلّقة محفوظة محليًا. نقل المزامنة المعتمد في Issue #14 لم يصل لهذا الفرع بعد.',
      ModrikLocale.fr: 'Les opérations sont conservées localement. Le transport officiel de l’Issue #14 n’est pas encore disponible.',
    },
    'nothing_to_sync': {
      ModrikLocale.en: 'There are no pending changes to sync.',
      ModrikLocale.ar: 'لا توجد تغييرات معلّقة للمزامنة.',
      ModrikLocale.fr: 'Aucun changement n’est en attente.',
    },
    'sync_complete': {
      ModrikLocale.en: 'Pending changes were acknowledged by the sync service.',
      ModrikLocale.ar: 'تم تأكيد التغييرات بواسطة خدمة المزامنة.',
      ModrikLocale.fr: 'Les changements ont été confirmés par le service de synchronisation.',
    },
    'onboarding_requires_connection': {
      ModrikLocale.en: 'Academic context confirmation requires a connection.',
      ModrikLocale.ar: 'تأكيد السياق الأكاديمي يتطلب اتصالًا.',
      ModrikLocale.fr: 'La confirmation du contexte nécessite une connexion.',
    },
    'academic_track_not_configured': {
      ModrikLocale.en: 'The authorized academic track is not configured.',
      ModrikLocale.ar: 'المسار الأكاديمي المصرح به غير مُعد.',
      ModrikLocale.fr: 'Le parcours académique autorisé n’est pas configuré.',
    },
    'answer_hint': {
      ModrikLocale.en: 'Type your answer',
      ModrikLocale.ar: 'اكتب إجابتك',
      ModrikLocale.fr: 'Saisissez votre réponse',
    },
    'question': {
      ModrikLocale.en: 'Question',
      ModrikLocale.ar: 'سؤال',
      ModrikLocale.fr: 'Question',
    },
  };

  String t(String key) => _copy[key]?[locale] ?? _copy[key]?[ModrikLocale.en] ?? key;
}
