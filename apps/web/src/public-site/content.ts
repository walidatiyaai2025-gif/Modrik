export const publicLocales = ["ar", "en", "fr"] as const;
export type PublicLocale = (typeof publicLocales)[number];

export type LocalizedText = Record<PublicLocale, string>;

export function localized(en: string, ar: string, fr: string): LocalizedText {
  return { en, ar, fr };
}

export function publicDirection(locale: PublicLocale): "rtl" | "ltr" {
  return locale === "ar" ? "rtl" : "ltr";
}

export function parsePublicLocale(value: string | string[] | undefined): PublicLocale {
  const candidate = Array.isArray(value) ? value[0] : value;
  return publicLocales.includes(candidate as PublicLocale) ? (candidate as PublicLocale) : "en";
}

export const legalBlockerIds = [
  "LEGAL_ENTITY_CONTROLLER",
  "PUBLIC_CONTACT",
  "JURISDICTION",
  "PROCESSING_BASES",
  "VENDOR_INVENTORY",
  "INTERNATIONAL_TRANSFERS",
  "RETENTION_SCHEDULE",
  "AGE_GUARDIAN_POLICY",
  "SAFETY_ESCALATION_CONTACT",
  "COPYRIGHT_TAKEDOWN_CONTACT",
  "SUPPORT_CHANNEL_HOURS",
  "POLICY_EFFECTIVE_DATE",
  "POLICY_VERSION",
] as const;

export type LegalBlockerId = (typeof legalBlockerIds)[number];

export const legalBlockers: Record<LegalBlockerId, LocalizedText> = {
  LEGAL_ENTITY_CONTROLLER: localized(
    "BLOCKED — owner/legal input required: legal entity and data controller.",
    "محجوب — يلزم إدخال من المالك/القانوني: الكيان القانوني ومراقب البيانات.",
    "BLOQUÉ — information propriétaire/juridique requise : entité juridique et responsable du traitement.",
  ),
  PUBLIC_CONTACT: localized(
    "BLOCKED — owner/legal input required: approved public contact details.",
    "محجوب — يلزم إدخال من المالك/القانوني: بيانات التواصل العامة المعتمدة.",
    "BLOQUÉ — information propriétaire/juridique requise : coordonnées publiques approuvées.",
  ),
  JURISDICTION: localized(
    "BLOCKED — owner/legal input required: governing law and jurisdiction.",
    "محجوب — يلزم إدخال من المالك/القانوني: القانون الحاكم والاختصاص القضائي.",
    "BLOQUÉ — information propriétaire/juridique requise : droit applicable et juridiction.",
  ),
  PROCESSING_BASES: localized(
    "BLOCKED — owner/legal input required: approved purposes and lawful processing bases.",
    "محجوب — يلزم إدخال من المالك/القانوني: الأغراض والأسس القانونية المعتمدة للمعالجة.",
    "BLOQUÉ — information propriétaire/juridique requise : finalités et bases légales approuvées du traitement.",
  ),
  VENDOR_INVENTORY: localized(
    "BLOCKED — owner/legal input required: production vendor and processor inventory.",
    "محجوب — يلزم إدخال من المالك/القانوني: قائمة موردي ومعالجي الإنتاج.",
    "BLOQUÉ — information propriétaire/juridique requise : inventaire des fournisseurs et sous-traitants de production.",
  ),
  INTERNATIONAL_TRANSFERS: localized(
    "BLOCKED — owner/legal input required: international transfer facts and safeguards.",
    "محجوب — يلزم إدخال من المالك/القانوني: حقائق وضمانات نقل البيانات دوليًا.",
    "BLOQUÉ — information propriétaire/juridique requise : transferts internationaux et garanties.",
  ),
  RETENTION_SCHEDULE: localized(
    "BLOCKED — owner/legal input required: approved data-retention and hard-purge schedule.",
    "محجوب — يلزم إدخال من المالك/القانوني: جدول الاحتفاظ والحذف النهائي المعتمد.",
    "BLOQUÉ — information propriétaire/juridique requise : calendrier approuvé de conservation et purge définitive.",
  ),
  AGE_GUARDIAN_POLICY: localized(
    "BLOCKED — owner/legal/safety input required: age, eligibility and guardian policy.",
    "محجوب — يلزم إدخال من المالك/القانوني/السلامة: سياسة العمر والأهلية وولي الأمر.",
    "BLOQUÉ — information propriétaire/juridique/sécurité requise : politique d’âge, d’éligibilité et de représentant légal.",
  ),
  SAFETY_ESCALATION_CONTACT: localized(
    "BLOCKED — owner/legal/safety input required: approved safeguarding escalation contacts and process.",
    "محجوب — يلزم إدخال من المالك/القانوني/السلامة: جهات الاتصال وإجراءات التصعيد للحماية.",
    "BLOQUÉ — information propriétaire/juridique/sécurité requise : contacts et procédure d’escalade de protection.",
  ),
  COPYRIGHT_TAKEDOWN_CONTACT: localized(
    "BLOCKED — owner/legal input required: copyright/takedown reporting contact and approved procedure.",
    "محجوب — يلزم إدخال من المالك/القانوني: جهة وإجراء الإبلاغ عن حقوق النشر والإزالة.",
    "BLOQUÉ — information propriétaire/juridique requise : contact et procédure approuvée de signalement/retrait de contenu.",
  ),
  SUPPORT_CHANNEL_HOURS: localized(
    "BLOCKED — owner/operations input required: approved support channels, hours and escalation ownership.",
    "محجوب — يلزم إدخال من المالك/التشغيل: قنوات الدعم وساعات العمل ومسؤولية التصعيد.",
    "BLOQUÉ — information propriétaire/opérations requise : canaux, horaires et responsabilité d’escalade du support.",
  ),
  POLICY_EFFECTIVE_DATE: localized(
    "BLOCKED — owner/legal approval required before an effective date can be published.",
    "محجوب — يلزم اعتماد المالك/القانوني قبل نشر تاريخ سريان.",
    "BLOQUÉ — approbation propriétaire/juridique requise avant publication d’une date d’entrée en vigueur.",
  ),
  POLICY_VERSION: localized(
    "BLOCKED — owner/legal approval required before a final policy version can be published.",
    "محجوب — يلزم اعتماد المالك/القانوني قبل نشر إصدار نهائي للسياسة.",
    "BLOQUÉ — approbation propriétaire/juridique requise avant publication d’une version finale de la politique.",
  ),
};

export type PublicSection = {
  id: string;
  title: LocalizedText;
  paragraphs: LocalizedText[];
  bullets?: LocalizedText[];
  blockers?: LegalBlockerId[];
};

export type PublicPageKey =
  | "landing"
  | "help"
  | "adminGuide"
  | "about"
  | "goal"
  | "vision"
  | "mission"
  | "disclaimer"
  | "privacy"
  | "terms"
  | "safety"
  | "cookies"
  | "contentPolicy"
  | "accountDeletion"
  | "support"
  | "contact";

export type PublicPageDefinition = {
  key: PublicPageKey;
  slug: string;
  eyebrow: LocalizedText;
  title: LocalizedText;
  summary: LocalizedText;
  seoDescription: LocalizedText;
  sections: PublicSection[];
  template: boolean;
  indexable: boolean;
};

const productEyebrow = localized("MODRIK public information", "معلومات مُدرك العامة", "Informations publiques MODRIK");
const guideEyebrow = localized("Guidance", "الإرشادات", "Guides");
const trustEyebrow = localized("Trust & transparency", "الثقة والشفافية", "Confiance et transparence");
const legalEyebrow = localized("Draft policy template", "نموذج سياسة مسودة", "Modèle de politique — brouillon");

export const publicPages: Record<PublicPageKey, PublicPageDefinition> = {
  landing: {
    key: "landing",
    slug: "landing",
    eyebrow: productEyebrow,
    title: localized("Learn More. Achieve More.", "تعلّم أكثر. أنجز أكثر.", "Apprenez davantage. Progressez davantage."),
    summary: localized(
      "MODRIK is building a calm, multilingual learning workspace for study, practice and progress, with backend-owned academic and assessment rules.",
      "تعمل مُدرك على بناء مساحة تعلّم هادئة ومتعددة اللغات للمذاكرة والتدريب والتقدّم، مع بقاء القواعد الأكاديمية وقواعد التقييم مملوكة للخادم.",
      "MODRIK construit un espace d’apprentissage calme et multilingue pour étudier, s’exercer et suivre sa progression, avec des règles académiques et d’évaluation gérées par le Backend.",
    ),
    seoDescription: localized(
      "Explore MODRIK, a multilingual learning workspace designed for clear study, practice and progress workflows.",
      "تعرّف على مُدرك، مساحة تعلّم متعددة اللغات مصممة لمسارات واضحة للمذاكرة والتدريب والتقدّم.",
      "Découvrez MODRIK, un espace d’apprentissage multilingue conçu pour des parcours clairs d’étude, d’exercice et de progression.",
    ),
    template: false,
    indexable: true,
    sections: [
      {
        id: "workspace",
        title: localized("One focused learning desk", "مكتب تعلّم واحد وواضح", "Un espace d’apprentissage concentré"),
        paragraphs: [localized(
          "The Student Web experience separates study, server-authoritative practice and progress into purpose-built workspaces rather than stretching a mobile interface across a desktop.",
          "تفصل تجربة الطالب على الويب بين المذاكرة والتدريب المعتمد من الخادم والتقدّم في مساحات مخصصة، بدل تمديد واجهة هاتف على سطح المكتب.",
          "L’expérience Web élève sépare l’étude, les exercices autoritaires du serveur et la progression dans des espaces dédiés, au lieu d’étirer une interface mobile sur ordinateur.",
        )],
      },
      {
        id: "languages",
        title: localized("Arabic, English and French by design", "العربية والإنجليزية والفرنسية من الأساس", "Arabe, anglais et français dès la conception"),
        paragraphs: [localized(
          "MODRIK treats language direction, mixed-content reading, keyboard access and large text as product requirements, not optional polish.",
          "تتعامل مُدرك مع اتجاه اللغة والمحتوى المختلط والوصول بلوحة المفاتيح والنص الكبير كمتطلبات للمنتج، لا كتحسينات اختيارية.",
          "MODRIK traite la direction du texte, le contenu mixte, l’accès clavier et le texte agrandi comme des exigences produit, et non comme des finitions facultatives.",
        )],
      },
      {
        id: "trust",
        title: localized("Trust boundaries stay visible", "حدود الثقة تبقى واضحة", "Des limites de confiance visibles"),
        paragraphs: [localized(
          "Assessment order, scoring, academic context and publication authority remain backend-owned. Public legal templates stay explicitly unapproved until the missing owner/legal facts are supplied.",
          "يبقى ترتيب التقييم والدرجات والسياق الأكاديمي وسلطة النشر مملوكًا للخادم. وتظل النماذج القانونية العامة غير معتمدة بوضوح حتى توفير الحقائق المطلوبة من المالك/القانوني.",
          "L’ordre des évaluations, la notation, le contexte académique et l’autorité de publication restent gérés par le Backend. Les modèles juridiques publics restent explicitement non approuvés tant que les informations propriétaire/juridiques manquantes ne sont pas fournies.",
        )],
      },
    ],
  },
  help: {
    key: "help",
    slug: "help",
    eyebrow: guideEyebrow,
    title: localized("Learner guide", "دليل المتعلّم", "Guide de l’apprenant"),
    summary: localized(
      "A practical guide to the current MODRIK learner workflow without inventing curriculum, grades or support promises.",
      "دليل عملي لمسار المتعلّم الحالي في مُدرك دون اختراع منهج أو درجات أو وعود دعم.",
      "Un guide pratique du parcours apprenant actuel de MODRIK, sans inventer de programme, de résultats ni de promesses de support.",
    ),
    seoDescription: localized("Learn how to navigate study, practice, progress, language and reconnect states in MODRIK.", "تعرّف على المذاكرة والتدريب والتقدّم واللغة وحالات إعادة الاتصال في مُدرك.", "Découvrez l’étude, les exercices, la progression, les langues et la reconnexion dans MODRIK."),
    template: false,
    indexable: true,
    sections: [
      {
        id: "start",
        title: localized("1. Confirm your learning context", "1. تحقّق من سياق التعلّم", "1. Vérifiez votre contexte d’apprentissage"),
        paragraphs: [localized(
          "Your active academic context comes from the Backend. A track change uses the defined full-reset flow and preserves prior history by archiving it rather than silently rewriting it.",
          "يأتي سياقك الأكاديمي النشط من الخادم. ويستخدم تغيير المسار تدفق إعادة الضبط الكامل المحدد مع حفظ السجل السابق بالأرشفة بدل تعديله بصمت.",
          "Votre contexte académique actif vient du Backend. Un changement de parcours utilise le flux de réinitialisation complète défini et conserve l’historique antérieur par archivage.",
        )],
      },
      {
        id: "study",
        title: localized("2. Study the published lesson", "2. ذاكر الدرس المنشور", "2. Étudiez la leçon publiée"),
        paragraphs: [localized(
          "Use the Study workspace for the lesson reading flow. Switch between Arabic, English and French where localized content is available; mixed-direction text is handled independently from the interface direction.",
          "استخدم مساحة المذاكرة لقراءة الدرس. بدّل بين العربية والإنجليزية والفرنسية عندما يتوفر المحتوى المترجم؛ ويُتعامل مع اتجاه النص المختلط بشكل مستقل عن اتجاه الواجهة.",
          "Utilisez l’espace Étude pour lire la leçon. Passez entre l’arabe, l’anglais et le français lorsque le contenu localisé est disponible ; la direction du contenu mixte est gérée indépendamment de l’interface.",
        )],
      },
      {
        id: "practice",
        title: localized("3. Practice without reshuffling authority", "3. تدرب دون نقل سلطة الترتيب للعميل", "3. Exercez-vous sans déplacer l’autorité d’ordre vers le client"),
        paragraphs: [localized(
          "Starting practice asks the Backend to create an authoritative attempt. Reconnecting to the same attempt resumes the persisted server order; the browser does not generate the assessment seed, question selection, scoring or authoritative order.",
          "عند بدء التدريب يطلب المتصفح من الخادم إنشاء محاولة معتمدة. وعند إعادة الاتصال تُستأنف نفس المحاولة بالترتيب المحفوظ في الخادم؛ ولا ينشئ المتصفح بذرة التقييم أو اختيار الأسئلة أو الدرجات أو الترتيب المعتمد.",
          "Le démarrage d’un exercice demande au Backend de créer une tentative autoritaire. La reconnexion reprend l’ordre persisté ; le navigateur ne génère ni graine, ni sélection de questions, ni notation, ni ordre autoritaire.",
        )],
      },
      {
        id: "progress",
        title: localized("4. Read progress from the active context", "4. اقرأ التقدّم من السياق النشط", "4. Consultez la progression du contexte actif"),
        paragraphs: [localized(
          "Progress is a Backend projection. The Web client displays it and does not independently calculate mastery.",
          "التقدّم إسقاط محسوب في الخادم. تعرضه واجهة الويب ولا تحسب الإتقان بشكل مستقل.",
          "La progression est une projection du Backend. Le client Web l’affiche sans recalculer la maîtrise de son côté.",
        )],
      },
      {
        id: "offline",
        title: localized("5. Reconnect safely", "5. أعد الاتصال بأمان", "5. Reconnectez-vous en sécurité"),
        paragraphs: [localized(
          "When the Web experience detects an offline or stale state it keeps server actions paused and offers a retry path. Do not assume an unsent local change is acknowledged until the server confirms it.",
          "عندما تكتشف تجربة الويب حالة عدم اتصال أو بيانات قديمة فإنها توقف إجراءات الخادم وتوفر مسار إعادة المحاولة. لا تعتبر أي تغيير محلي غير مُرسل مؤكدًا حتى يؤكده الخادم.",
          "Lorsque l’expérience Web détecte un état hors ligne ou périmé, les actions serveur sont suspendues et une nouvelle tentative est proposée. Ne considérez pas un changement local non envoyé comme confirmé avant l’accusé du serveur.",
        )],
      },
      {
        id: "support",
        title: localized("6. Need support?", "6. تحتاج إلى دعم؟", "6. Besoin d’aide ?"),
        paragraphs: [localized(
          "Use the Support and Contact guidance pages for the current release status. A final public support channel, service hours and escalation owner have not yet been approved.",
          "استخدم صفحتي الدعم والتواصل لمعرفة حالة الإصدار الحالية. لم يتم بعد اعتماد قناة دعم عامة نهائية أو ساعات الخدمة أو مسؤول التصعيد.",
          "Consultez les pages Support et Contact pour l’état actuel. Aucun canal public final, horaire de service ni responsable d’escalade n’est encore approuvé.",
        )],
        blockers: ["SUPPORT_CHANNEL_HOURS", "PUBLIC_CONTACT"],
      },
    ],
  },
  adminGuide: {
    key: "adminGuide",
    slug: "admin-guide",
    eyebrow: guideEyebrow,
    title: localized("Admin / Content Team guide", "دليل الإدارة / فريق المحتوى", "Guide Admin / Équipe contenu"),
    summary: localized(
      "Operational guidance for the controlled content-preparation and publication workflow, using synthetic examples only.",
      "إرشادات تشغيلية لمسار إعداد المحتوى ونشره بصورة محكومة، باستخدام أمثلة اصطناعية فقط.",
      "Guide opérationnel du flux contrôlé de préparation et de publication de contenu, avec des exemples synthétiques uniquement.",
    ),
    seoDescription: localized("MODRIK Admin and Content Team guide for deterministic preparation, validation, review and controlled publication.", "دليل إدارة وفريق محتوى مُدرك للإعداد الحتمي والتحقق والمراجعة والنشر المحكوم.", "Guide MODRIK Admin et Équipe contenu pour la préparation déterministe, la validation, la revue et la publication contrôlée."),
    template: false,
    indexable: false,
    sections: [
      {
        id: "authority",
        title: localized("Authority boundary", "حدود الصلاحيات", "Limite d’autorité"),
        paragraphs: [localized(
          "Only authenticated admin or content_team operators may use the official-content workflow. Student or UGC identifiers have no automatic promotion path into official curriculum.",
          "فقط المشغلون الموثقون بأدوار admin أو content_team يمكنهم استخدام مسار المحتوى الرسمي. لا يوجد مسار ترقية تلقائية لمعرّفات الطالب أو المحتوى الذي ينشئه المستخدم إلى المنهج الرسمي.",
          "Seuls les opérateurs authentifiés avec les rôles admin ou content_team peuvent utiliser le flux de contenu officiel. Aucun identifiant élève ou UGC n’est promu automatiquement vers le programme officiel.",
        )],
      },
      {
        id: "prepare",
        title: localized("Prepare and bind", "الإعداد والربط", "Préparer et lier"),
        paragraphs: [localized(
          "Create the Preparation request, keep preparation_request_id, settings_hash and schema_version together, and never move a returned archive between requests.",
          "أنشئ طلب الإعداد واحتفظ بـ preparation_request_id وsettings_hash وschema_version معًا، ولا تنقل الأرشيف المرتجع بين الطلبات.",
          "Créez la demande de préparation, conservez ensemble preparation_request_id, settings_hash et schema_version, et ne déplacez jamais une archive retournée entre demandes.",
        )],
      },
      {
        id: "validate",
        title: localized("Validate before review", "تحقق قبل المراجعة", "Valider avant la revue"),
        paragraphs: [localized(
          "Archive safety, origin binding, schema, semantic references and rights eligibility must pass before review. A validation failure is a block, not a warning to bypass.",
          "يجب أن تنجح سلامة الأرشيف وربط المصدر والمخطط والمراجع الدلالية وأهلية الحقوق قبل المراجعة. فشل التحقق هو حظر وليس تحذيرًا يمكن تجاوزه.",
          "La sécurité de l’archive, la liaison d’origine, le schéma, les références sémantiques et l’éligibilité des droits doivent être validés avant revue. Un échec est un blocage, pas un avertissement à contourner.",
        )],
      },
      {
        id: "review",
        title: localized("Dry-run, review, then publish", "نفّذ المعاينة ثم راجع ثم انشر", "Prévisualiser, réviser, puis publier"),
        paragraphs: [localized(
          "Run the deterministic dry-run/diff, record approved/rejected/request_fix with the required reason, import only a fresh approved snapshot, then publish as a separate idempotent transaction.",
          "نفّذ dry-run/diff الحتمي، وسجّل approved أو rejected أو request_fix مع السبب المطلوب، واستورد فقط لقطة معتمدة وحديثة، ثم نفّذ النشر كمعاملة مستقلة قابلة لإعادة التنفيذ بأمان.",
          "Exécutez le dry-run/diff déterministe, enregistrez approved/rejected/request_fix avec la raison requise, importez uniquement un instantané approuvé et frais, puis publiez dans une transaction idempotente distincte.",
        )],
      },
      {
        id: "stale",
        title: localized("Never bypass stale preparation", "لا تتجاوز إعدادًا قديمًا", "Ne contournez jamais une préparation périmée"),
        paragraphs: [localized(
          "If the workflow returns PREPARATION_REGENERATION_REQUIRED, regenerate the preparation request and use the replacement prompt/bundle. Do not edit canonical rows, audit rows or outbox rows by hand to force progress.",
          "إذا أعاد المسار PREPARATION_REGENERATION_REQUIRED، فأعد إنشاء طلب الإعداد واستخدم الـprompt/bundle البديل. لا تعدّل الصفوف الرسمية أو سجلات التدقيق أو outbox يدويًا لفرض التقدم.",
          "Si le flux retourne PREPARATION_REGENERATION_REQUIRED, régénérez la demande et utilisez le nouveau prompt/bundle. Ne modifiez pas manuellement les lignes canoniques, d’audit ou d’outbox pour forcer l’avancement.",
        )],
      },
      {
        id: "production",
        title: localized("Production facts remain blocked", "حقائق الإنتاج تظل محجوبة", "Les faits de production restent bloqués"),
        paragraphs: [localized(
          "Do not synthesize an exam board, syllabus/version, curriculum-rights claim, legal fact, credential or escalation owner. The repository workflow is valid with synthetic fixtures while those owner-controlled inputs remain absent.",
          "لا تُنشئ مجلس امتحان أو منهجًا/إصدارًا أو ادعاء حقوق محتوى أو حقيقة قانونية أو بيانات اعتماد أو مسؤول تصعيد. يظل مسار المستودع صالحًا باستخدام fixtures اصطناعية بينما تغيب مدخلات المالك.",
          "N’inventez aucun organisme d’examen, programme/version, droit de contenu, fait juridique, identifiant ni responsable d’escalade. Le flux du dépôt reste valide avec des fixtures synthétiques tant que ces entrées contrôlées par le propriétaire manquent.",
        )],
        blockers: ["SUPPORT_CHANNEL_HOURS", "PUBLIC_CONTACT"],
      },
    ],
  },
  about: {
    key: "about",
    slug: "about",
    eyebrow: productEyebrow,
    title: localized("About MODRIK", "عن مُدرك", "À propos de MODRIK"),
    summary: localized(
      "MODRIK | مُدرك is an education software project focused on clear, multilingual learning workflows and explicit backend authority.",
      "مُدرك | MODRIK مشروع برمجيات تعليمية يركز على مسارات تعلّم واضحة ومتعددة اللغات وعلى إبقاء السلطة الأساسية صريحة في الخادم.",
      "MODRIK | مُدرك est un projet logiciel éducatif centré sur des parcours d’apprentissage clairs et multilingues et une autorité Backend explicite.",
    ),
    seoDescription: localized("About the MODRIK education software project and its product principles.", "عن مشروع مُدرك التعليمي ومبادئ المنتج.", "À propos du projet logiciel éducatif MODRIK et de ses principes produit."),
    template: false,
    indexable: true,
    sections: [
      {
        id: "principles",
        title: localized("Calm, capable and explicit", "هادئ وقادر وواضح", "Calme, capable et explicite"),
        paragraphs: [localized(
          "The product direction is modern education without visual noise, fake social proof or hidden client-side authority. Current implementation emphasizes accessible study, practice, progress and controlled content operations.",
          "اتجاه المنتج هو تعليم عصري بلا ضوضاء بصرية أو إثبات اجتماعي مزيف أو سلطة مخفية في العميل. يركز التنفيذ الحالي على المذاكرة والتدريب والتقدّم المتاح للجميع وعلى تشغيل المحتوى بصورة محكومة.",
          "La direction produit vise une éducation moderne sans bruit visuel, preuve sociale inventée ni autorité cachée côté client. L’implémentation actuelle privilégie l’étude accessible, les exercices, la progression et les opérations de contenu contrôlées.",
        )],
      },
      {
        id: "boundaries",
        title: localized("What we do not claim", "ما لا ندّعيه", "Ce que nous ne revendiquons pas"),
        paragraphs: [localized(
          "This release does not claim school partnerships, student counts, exam-board approval, guaranteed outcomes or final legal policy approval. Exact real curriculum identifiers and rights remain owner-controlled inputs.",
          "لا يدّعي هذا الإصدار وجود شراكات مدارس أو أعداد طلاب أو اعتماد مجالس امتحانات أو نتائج مضمونة أو اعتماد نهائي للسياسات القانونية. تظل معرّفات المناهج الحقيقية وحقوقها مدخلات يملكها صاحب المشروع.",
          "Cette version ne revendique aucun partenariat scolaire, nombre d’élèves, agrément d’un organisme d’examen, résultat garanti ni approbation juridique finale. Les identifiants et droits réels de programme restent des entrées contrôlées par le propriétaire.",
        )],
      },
    ],
  },
  goal: {
    key: "goal",
    slug: "goal",
    eyebrow: productEyebrow,
    title: localized("Our goal", "هدفنا", "Notre objectif"),
    summary: localized(
      "Make the learning workflow easier to understand: know what to study, practice in a controlled attempt, and see progress without blurring authority or history.",
      "جعل مسار التعلّم أسهل فهمًا: اعرف ما تذاكره، وتدرّب في محاولة محكومة، وشاهد التقدّم دون طمس مصدر السلطة أو السجل.",
      "Rendre le parcours d’apprentissage plus simple à comprendre : savoir quoi étudier, s’exercer dans une tentative contrôlée et voir sa progression sans brouiller l’autorité ni l’historique.",
    ),
    seoDescription: localized("MODRIK's product goal for clear study, practice and progress workflows.", "هدف مُدرك لمسارات واضحة للمذاكرة والتدريب والتقدّم.", "L’objectif produit de MODRIK pour des parcours clairs d’étude, d’exercice et de progression."),
    template: false,
    indexable: true,
    sections: [
      {
        id: "measure",
        title: localized("Clarity before claims", "الوضوح قبل الادعاءات", "La clarté avant les promesses"),
        paragraphs: [localized(
          "This is a product objective, not a promise of grades, admissions or exam outcomes. Learning results depend on many factors outside software control.",
          "هذا هدف للمنتج وليس وعدًا بدرجات أو قبول أو نتائج امتحانات. تعتمد نتائج التعلّم على عوامل كثيرة خارج سيطرة البرمجيات.",
          "Il s’agit d’un objectif produit, pas d’une promesse de notes, d’admission ou de résultats d’examen. Les résultats d’apprentissage dépendent de nombreux facteurs hors du contrôle du logiciel.",
        )],
      },
    ],
  },
  vision: {
    key: "vision",
    slug: "vision",
    eyebrow: productEyebrow,
    title: localized("Vision", "الرؤية", "Vision"),
    summary: localized(
      "A learning environment where multilingual access, trustworthy state and usable guidance are built into the product rather than added at the end.",
      "بيئة تعلّم تُبنى فيها إتاحة اللغات وحالة البيانات الموثوقة والإرشادات القابلة للاستخدام داخل المنتج من البداية بدل إضافتها في النهاية.",
      "Un environnement d’apprentissage où l’accès multilingue, l’état fiable et les guides utilisables sont intégrés au produit dès le départ.",
    ),
    seoDescription: localized("MODRIK's vision for multilingual, accessible and trustworthy learning software.", "رؤية مُدرك لبرمجيات تعلّم متعددة اللغات ومتاحة وموثوقة.", "La vision de MODRIK pour un logiciel d’apprentissage multilingue, accessible et fiable."),
    template: false,
    indexable: true,
    sections: [{ id: "direction", title: localized("Designed for trust", "مصمم للثقة", "Conçu pour la confiance"), paragraphs: [localized("The vision favors explicit rules, recoverable history, accessible interaction and truthful public information over shortcuts that hide uncertainty.", "تفضّل الرؤية القواعد الصريحة والسجل القابل للاسترجاع والتفاعل المتاح والمعلومات العامة الصادقة على الاختصارات التي تخفي عدم اليقين.", "La vision privilégie des règles explicites, un historique récupérable, une interaction accessible et une information publique honnête plutôt que des raccourcis masquant l’incertitude.")] }],
  },
  mission: {
    key: "mission",
    slug: "mission",
    eyebrow: productEyebrow,
    title: localized("Mission", "الرسالة", "Mission"),
    summary: localized(
      "Build maintainable learning software that supports study, authoritative practice, progress and governed content across Arabic, English and French.",
      "بناء برمجيات تعلّم قابلة للصيانة تدعم المذاكرة والتدريب المعتمد والتقدّم والمحتوى المحكوم بالعربية والإنجليزية والفرنسية.",
      "Construire un logiciel d’apprentissage maintenable qui prend en charge l’étude, les exercices autoritaires, la progression et le contenu gouverné en arabe, anglais et français.",
    ),
    seoDescription: localized("MODRIK's mission for maintainable multilingual learning software.", "رسالة مُدرك لبناء برمجيات تعلّم متعددة اللغات وقابلة للصيانة.", "La mission de MODRIK pour un logiciel d’apprentissage multilingue et maintenable."),
    template: false,
    indexable: true,
    sections: [{ id: "how", title: localized("How the mission shows up in engineering", "كيف تظهر الرسالة في الهندسة", "Comment la mission se traduit en ingénierie"), paragraphs: [localized("Backend-owned business rules, explicit offline/idempotency boundaries, accessible client surfaces, deterministic content governance and fail-closed safety controls are implementation choices that support this mission.", "تدعم هذه الرسالة اختيارات تنفيذية مثل قواعد الأعمال المملوكة للخادم، وحدود عدم الاتصال/idempotency الصريحة، وواجهات العملاء المتاحة، وحوكمة المحتوى الحتمية، وضوابط السلامة التي تفشل إلى الوضع الآمن.", "Les règles métier gérées par le Backend, les limites hors ligne/idempotence explicites, les interfaces accessibles, la gouvernance déterministe du contenu et les contrôles de sécurité fail-closed sont des choix d’implémentation au service de cette mission.")] }],
  },
  disclaimer: {
    key: "disclaimer",
    slug: "disclaimer",
    eyebrow: trustEyebrow,
    title: localized("Educational & AI disclaimer", "إخلاء مسؤولية تعليمي والذكاء الاصطناعي", "Avertissement éducatif & IA"),
    summary: localized(
      "Draft public guidance about educational use and optional AI boundaries. It is not final legal text and requires owner/legal approval before production publication.",
      "إرشادات عامة مسودة حول الاستخدام التعليمي وحدود الذكاء الاصطناعي الاختياري. ليست نصًا قانونيًا نهائيًا وتتطلب اعتماد المالك/القانوني قبل النشر الإنتاجي.",
      "Guide public provisoire sur l’usage éducatif et les limites de l’IA facultative. Ce n’est pas un texte juridique final et une approbation propriétaire/juridique est requise avant publication en production.",
    ),
    seoDescription: localized("Draft MODRIK educational and optional-AI disclaimer, pending legal approval.", "مسودة إخلاء مسؤولية مُدرك التعليمية والذكاء الاصطناعي الاختياري، بانتظار الاعتماد القانوني.", "Projet d’avertissement MODRIK sur l’éducation et l’IA facultative, en attente d’approbation juridique."),
    template: true,
    indexable: false,
    sections: [
      { id: "education", title: localized("Educational support, not an outcome guarantee", "دعم تعليمي وليس ضمانًا للنتائج", "Soutien éducatif, sans garantie de résultat"), paragraphs: [localized("MODRIK is designed to support learning workflows. Software, lesson content, practice scores and progress indicators do not guarantee exam grades, admissions, qualifications or other educational outcomes.", "صُممت مُدرك لدعم مسارات التعلّم. لا تضمن البرمجيات أو محتوى الدروس أو درجات التدريب أو مؤشرات التقدّم درجات امتحانات أو قبولًا أو مؤهلات أو نتائج تعليمية أخرى.", "MODRIK est conçu pour soutenir les parcours d’apprentissage. Le logiciel, les leçons, les scores d’exercice et les indicateurs de progression ne garantissent ni notes d’examen, ni admission, ni qualification, ni autre résultat éducatif.")] },
      { id: "ai", title: localized("AI is not the learning source of truth", "الذكاء الاصطناعي ليس مصدر الحقيقة للتعلّم", "L’IA n’est pas la source de vérité de l’apprentissage"), paragraphs: [localized("The learning core does not require a paid AI API. Optional AI assistance is an auxiliary composition aid behind deterministic validation; it does not own student identity, assessment scoring, progress or official publication authority.", "لا تتطلب نواة التعلّم واجهة ذكاء اصطناعي مدفوعة. المساعدة الاختيارية بالذكاء الاصطناعي أداة مساعدة خلف تحقق حتمي، ولا تملك هوية الطالب أو درجات التقييم أو التقدّم أو سلطة النشر الرسمي.", "Le cœur d’apprentissage ne nécessite pas d’API IA payante. L’assistance IA facultative reste un outil auxiliaire derrière une validation déterministe ; elle ne gère ni identité élève, ni notation, ni progression, ni autorité de publication officielle.")] },
      { id: "approval", title: localized("Approval still required", "لا يزال الاعتماد مطلوبًا", "Approbation encore requise"), paragraphs: [localized("The final disclaimer wording and any jurisdiction-specific disclosures remain owner/legal decisions.", "تظل الصياغة النهائية لإخلاء المسؤولية وأي إفصاحات خاصة بالاختصاص القضائي قرارات للمالك/القانوني.", "La formulation finale et toute information propre à une juridiction restent des décisions propriétaire/juridiques.")], blockers: ["LEGAL_ENTITY_CONTROLLER", "JURISDICTION", "POLICY_EFFECTIVE_DATE", "POLICY_VERSION"] },
    ],
  },
  privacy: {
    key: "privacy",
    slug: "privacy",
    eyebrow: legalEyebrow,
    title: localized("Privacy template — not approved", "نموذج الخصوصية — غير معتمد", "Modèle de confidentialité — non approuvé"),
    summary: localized("This page is an engineering template for a future Privacy Notice. It is not a final policy, legal advice or an approved statement of production data practices.", "هذه الصفحة نموذج هندسي لإشعار خصوصية مستقبلي. ليست سياسة نهائية أو نصيحة قانونية أو بيانًا معتمدًا لممارسات بيانات الإنتاج.", "Cette page est un modèle d’ingénierie pour une future notice de confidentialité. Ce n’est ni une politique finale, ni un avis juridique, ni une déclaration approuvée des pratiques de données en production."),
    seoDescription: localized("Unapproved MODRIK Privacy Notice template with explicit owner/legal blockers.", "نموذج إشعار خصوصية مُدرك غير المعتمد مع حواجز المالك/القانوني الصريحة.", "Modèle non approuvé de notice de confidentialité MODRIK avec blocages propriétaire/juridiques explicites."),
    template: true,
    indexable: false,
    sections: [
      { id: "identity", title: localized("Who is responsible?", "من المسؤول؟", "Qui est responsable ?"), paragraphs: [localized("The final notice must identify the legal entity/controller and approved public privacy contact. Those facts are intentionally not inferred from repository ownership, domain registration or developer accounts.", "يجب أن يحدد الإشعار النهائي الكيان القانوني/مراقب البيانات وجهة التواصل المعتمدة للخصوصية. لا يتم استنتاج هذه الحقائق من ملكية المستودع أو تسجيل النطاق أو حسابات المطورين.", "La notice finale doit identifier l’entité juridique/responsable du traitement et le contact confidentialité approuvé. Ces faits ne sont pas déduits de la propriété du dépôt, du domaine ou des comptes développeur.")], blockers: ["LEGAL_ENTITY_CONTROLLER", "PUBLIC_CONTACT"] },
      { id: "processing", title: localized("Data categories, purposes and lawful bases", "فئات البيانات والأغراض والأسس القانونية", "Catégories de données, finalités et bases légales"), paragraphs: [localized("Engineering contracts define security-minimizing behavior in specific flows, but the final public notice must be based on an approved production data inventory and legal analysis rather than code assumptions.", "تحدد العقود الهندسية سلوكًا يقلل البيانات في مسارات محددة، لكن الإشعار العام النهائي يجب أن يعتمد على قائمة بيانات إنتاج معتمدة وتحليل قانوني وليس على افتراضات الكود.", "Les contrats d’ingénierie définissent des comportements de minimisation dans certains flux, mais la notice publique finale doit reposer sur un inventaire de production approuvé et une analyse juridique, pas sur des suppositions du code.")], blockers: ["PROCESSING_BASES", "VENDOR_INVENTORY"] },
      { id: "transfers", title: localized("Vendors and international transfers", "الموردون ونقل البيانات دوليًا", "Fournisseurs et transferts internationaux"), paragraphs: [localized("No vendor list or transfer statement is approved for this template. Provider configuration that remains external or disabled must not be converted into a claim about production processing.", "لا توجد قائمة موردين أو صياغة نقل بيانات معتمدة لهذا النموذج. ولا يجوز تحويل إعدادات المزود الخارجي أو المعطلة إلى ادعاء حول معالجة الإنتاج.", "Aucune liste de fournisseurs ni déclaration de transfert n’est approuvée pour ce modèle. Une configuration fournisseur externe ou désactivée ne doit pas devenir une affirmation sur le traitement en production.")], blockers: ["VENDOR_INVENTORY", "INTERNATIONAL_TRANSFERS"] },
      { id: "retention", title: localized("Retention, deletion and minors", "الاحتفاظ والحذف والقُصّر", "Conservation, suppression et mineurs"), paragraphs: [localized("The Backend has an auditable account-deletion lifecycle, but final hard-purge periods, retention schedules, age/guardian rules and legal disclosures remain owner/legal inputs.", "يوجد في الخادم مسار قابل للتدقيق لحذف الحساب، لكن فترات الحذف النهائي وجداول الاحتفاظ وقواعد العمر/ولي الأمر والإفصاحات القانونية تظل مدخلات للمالك/القانوني.", "Le Backend possède un cycle de suppression de compte auditable, mais les délais de purge définitive, calendriers de conservation, règles d’âge/représentant légal et mentions juridiques restent des entrées propriétaire/juridiques.")], blockers: ["RETENTION_SCHEDULE", "AGE_GUARDIAN_POLICY"] },
      { id: "version", title: localized("Version and effective date", "الإصدار وتاريخ السريان", "Version et date d’entrée en vigueur"), paragraphs: [localized("A final version and effective date may only be published after owner/legal approval.", "لا يجوز نشر إصدار نهائي وتاريخ سريان إلا بعد اعتماد المالك/القانوني.", "Une version finale et une date d’entrée en vigueur ne peuvent être publiées qu’après approbation propriétaire/juridique.")], blockers: ["POLICY_VERSION", "POLICY_EFFECTIVE_DATE"] },
    ],
  },
  terms: {
    key: "terms",
    slug: "terms",
    eyebrow: legalEyebrow,
    title: localized("Terms template — not approved", "نموذج الشروط — غير معتمد", "Modèle de conditions — non approuvé"),
    summary: localized("A structural template for future Terms of Use. It is not a contract, legal advice or approved production terms.", "نموذج هيكلي لشروط استخدام مستقبلية. ليس عقدًا أو نصيحة قانونية أو شروط إنتاج معتمدة.", "Un modèle structurel pour de futures Conditions d’utilisation. Ce n’est ni un contrat, ni un avis juridique, ni des conditions de production approuvées."),
    seoDescription: localized("Unapproved MODRIK Terms of Use template with explicit legal blockers.", "نموذج شروط استخدام مُدرك غير المعتمد مع الحواجز القانونية الصريحة.", "Modèle non approuvé des Conditions d’utilisation MODRIK avec blocages juridiques explicites."),
    template: true,
    indexable: false,
    sections: [
      { id: "party", title: localized("Contracting party and eligibility", "الطرف المتعاقد والأهلية", "Partie contractante et éligibilité"), paragraphs: [localized("The final Terms must identify the legal entity and approved eligibility/guardian rules. This template does not infer either.", "يجب أن تحدد الشروط النهائية الكيان القانوني وقواعد الأهلية/ولي الأمر المعتمدة. لا يستنتج هذا النموذج أيًا منهما.", "Les Conditions finales doivent identifier l’entité juridique et les règles approuvées d’éligibilité/représentant légal. Ce modèle ne déduit ni l’un ni l’autre.")], blockers: ["LEGAL_ENTITY_CONTROLLER", "AGE_GUARDIAN_POLICY"] },
      { id: "service", title: localized("Service scope and educational use", "نطاق الخدمة والاستخدام التعليمي", "Périmètre du service et usage éducatif"), paragraphs: [localized("Final service availability, eligibility, cancellation, responsibility and educational-use terms require legal/product approval. No grade or exam outcome is guaranteed by this template.", "تتطلب شروط توافر الخدمة والأهلية والإلغاء والمسؤولية والاستخدام التعليمي اعتمادًا قانونيًا/منتجيًا. لا يضمن هذا النموذج أي درجة أو نتيجة امتحان.", "Les conditions finales de disponibilité, d’éligibilité, d’annulation, de responsabilité et d’usage éducatif nécessitent une approbation juridique/produit. Ce modèle ne garantit aucun résultat scolaire ou d’examen.")] },
      { id: "law", title: localized("Governing law and disputes", "القانون الحاكم والنزاعات", "Droit applicable et litiges"), paragraphs: [localized("No governing law, forum, dispute process or jurisdiction is selected in this repository template.", "لم يتم اختيار قانون حاكم أو محكمة أو مسار نزاع أو اختصاص قضائي في نموذج المستودع هذا.", "Aucun droit applicable, tribunal, processus de litige ou juridiction n’est choisi dans ce modèle de dépôt.")], blockers: ["JURISDICTION", "PUBLIC_CONTACT"] },
      { id: "version", title: localized("Approval and version", "الاعتماد والإصدار", "Approbation et version"), paragraphs: [localized("Do not use this template as an acceptance contract until final wording, version and effective date are approved.", "لا تستخدم هذا النموذج كعقد قبول حتى اعتماد الصياغة النهائية والإصدار وتاريخ السريان.", "N’utilisez pas ce modèle comme contrat d’acceptation avant approbation de la rédaction finale, de la version et de la date d’entrée en vigueur.")], blockers: ["POLICY_VERSION", "POLICY_EFFECTIVE_DATE"] },
    ],
  },
  safety: {
    key: "safety",
    slug: "safety",
    eyebrow: trustEyebrow,
    title: localized("Child & minor safety", "سلامة الأطفال والقُصّر", "Sécurité des enfants et mineurs"),
    summary: localized("Product-safety guidance based on implemented safe defaults. Final age/guardian policy and safeguarding escalation wording remain unapproved owner/legal/safety inputs.", "إرشادات سلامة للمنتج مبنية على الإعدادات الآمنة المنفذة. تظل سياسة العمر/ولي الأمر وصياغة تصعيد الحماية مدخلات غير معتمدة للمالك/القانوني/السلامة.", "Guide de sécurité produit basé sur les défauts sûrs implémentés. La politique d’âge/représentant légal et la procédure d’escalade restent des entrées non approuvées propriétaire/juridiques/sécurité."),
    seoDescription: localized("MODRIK child and minor safety guidance with explicit pending age and escalation policy blockers.", "إرشادات سلامة الأطفال والقُصّر في مُدرك مع حواجز صريحة لسياسة العمر والتصعيد المعلقة.", "Guide MODRIK de sécurité des enfants et mineurs avec blocages explicites sur l’âge et l’escalade."),
    template: true,
    indexable: false,
    sections: [
      { id: "defaults", title: localized("Safe defaults in the current architecture", "إعدادات آمنة في البنية الحالية", "Défauts sûrs dans l’architecture actuelle"), paragraphs: [localized("Advertising eligibility is backend-controlled and fails closed when required policy/assurance is missing, stale, invalid or not eligible; immutable no-ad zones and a global kill switch take precedence. No ad network is activated by that decision contract.", "أهلية الإعلانات مملوكة للخادم وتفشل إلى المنع عند غياب/قدم/عدم صلاحية السياسة أو التحقق المطلوب أو عدم الأهلية؛ وتتقدم مناطق منع الإعلانات غير القابلة للتغيير ومفتاح الإيقاف العام. ولا يفعّل عقد القرار شبكة إعلانات.", "L’éligibilité publicitaire est gérée par le Backend et échoue en mode fermé si la politique/assurance requise manque, est périmée, invalide ou inéligible ; les zones sans publicité immuables et l’arrêt global prévalent. Ce contrat n’active aucun réseau publicitaire.")] },
      { id: "community", title: localized("Community features are not a P0 activation", "ميزات المجتمع ليست مفعلة في P0", "Les fonctions communautaires ne sont pas activées en P0"), paragraphs: [localized("Community Q&A remains a later activation boundary; the P0 repository does not introduce direct messages or silently promote learner content into official curriculum.", "تظل أسئلة وأجوبة المجتمع حد تفعيل لاحقًا؛ ولا يضيف مستودع P0 رسائل خاصة ولا يرقّي محتوى المتعلم بصمت إلى المنهج الرسمي.", "Le Q&R communautaire reste une activation ultérieure ; le dépôt P0 n’introduit pas de messages privés et ne promeut pas silencieusement le contenu apprenant vers le programme officiel.")] },
      { id: "policy", title: localized("Final age and safeguarding policy is pending", "سياسة العمر والحماية النهائية معلقة", "La politique finale d’âge et de protection est en attente"), paragraphs: [localized("Engineering safe defaults do not determine the final legal age threshold, guardian workflow, emergency wording or safeguarding escalation process.", "لا تحدد الإعدادات الهندسية الآمنة حد العمر القانوني النهائي أو مسار ولي الأمر أو صياغة الطوارئ أو مسار تصعيد الحماية.", "Les défauts techniques sûrs ne déterminent pas le seuil d’âge légal final, le parcours du représentant légal, les mentions d’urgence ni la procédure d’escalade de protection.")], blockers: ["AGE_GUARDIAN_POLICY", "SAFETY_ESCALATION_CONTACT", "PUBLIC_CONTACT"] },
    ],
  },
  cookies: {
    key: "cookies",
    slug: "cookies",
    eyebrow: legalEyebrow,
    title: localized("Cookie & tracking template — not approved", "نموذج ملفات الارتباط والتتبع — غير معتمد", "Modèle cookies & suivi — non approuvé"),
    summary: localized("A release template for documenting cookies, local storage and optional tracking after a production inventory and legal determination exist. It is not an active consent notice.", "نموذج إصدار لتوثيق ملفات الارتباط والتخزين المحلي والتتبع الاختياري بعد وجود جرد إنتاج وتحديد قانوني. ليس إشعار موافقة فعّالًا.", "Un modèle de version pour documenter cookies, stockage local et suivi facultatif après inventaire de production et analyse juridique. Ce n’est pas une notice de consentement active."),
    seoDescription: localized("Unapproved MODRIK cookie and tracking template, pending vendor inventory and legal determination.", "نموذج ملفات ارتباط وتتبع مُدرك غير المعتمد، بانتظار جرد الموردين والتحديد القانوني.", "Modèle non approuvé MODRIK cookies et suivi, en attente d’inventaire fournisseurs et d’analyse juridique."),
    template: true,
    indexable: false,
    sections: [
      { id: "categories", title: localized("Categories to inventory before approval", "فئات يجب جردها قبل الاعتماد", "Catégories à inventorier avant approbation"), paragraphs: [localized("The final notice must distinguish strictly necessary storage from any optional analytics, crash reporting or other tracking actually enabled in production. This template does not assume that optional providers are active.", "يجب أن يميّز الإشعار النهائي بين التخزين الضروري وأي تحليلات اختيارية أو تقارير أعطال أو تتبع آخر مفعّل فعليًا في الإنتاج. لا يفترض هذا النموذج تفعيل مزودين اختياريين.", "La notice finale doit distinguer le stockage strictement nécessaire de toute analyse, rapport de panne ou autre suivi facultatif réellement activé en production. Ce modèle ne suppose aucun fournisseur facultatif actif.")], blockers: ["VENDOR_INVENTORY", "PROCESSING_BASES"] },
      { id: "consent", title: localized("Consent behavior is not invented here", "لا يتم اختراع سلوك الموافقة هنا", "Le comportement de consentement n’est pas inventé ici"), paragraphs: [localized("Applicable-law requirements, consent categories, retention and withdrawal behavior require legal/product approval. This page does not activate a consent manager or tracking SDK.", "تتطلب متطلبات القانون المنطبق وفئات الموافقة والاحتفاظ وسلوك سحب الموافقة اعتمادًا قانونيًا/منتجيًا. لا تفعّل هذه الصفحة مدير موافقة أو SDK للتتبع.", "Les exigences légales, catégories de consentement, conservation et retrait nécessitent une approbation juridique/produit. Cette page n’active aucun gestionnaire de consentement ni SDK de suivi.")], blockers: ["JURISDICTION", "RETENTION_SCHEDULE"] },
      { id: "version", title: localized("Final notice status", "حالة الإشعار النهائي", "Statut de la notice finale"), paragraphs: [localized("Version and effective date stay blocked until the inventory and approved wording are complete.", "يبقى الإصدار وتاريخ السريان محجوبين حتى اكتمال الجرد والصياغة المعتمدة.", "La version et la date d’entrée en vigueur restent bloquées jusqu’à finalisation de l’inventaire et du texte approuvé.")], blockers: ["POLICY_VERSION", "POLICY_EFFECTIVE_DATE"] },
    ],
  },
  contentPolicy: {
    key: "contentPolicy",
    slug: "content-policy",
    eyebrow: trustEyebrow,
    title: localized("Content, copyright & reporting shell", "واجهة المحتوى وحقوق النشر والإبلاغ", "Cadre contenu, droit d’auteur & signalement"),
    summary: localized("A transparent description of the implemented content-governance boundary plus a placeholder reporting/takedown route. It is not final takedown policy or legal advice.", "وصف شفاف لحدود حوكمة المحتوى المنفذة مع مسار إبلاغ/إزالة placeholder. ليست سياسة إزالة نهائية أو نصيحة قانونية.", "Une description transparente de la gouvernance de contenu implémentée avec une voie de signalement/retrait provisoire. Ce n’est ni une politique de retrait finale ni un avis juridique."),
    seoDescription: localized("MODRIK content governance and unapproved copyright/takedown reporting shell.", "حوكمة محتوى مُدرك وواجهة إبلاغ حقوق النشر/الإزالة غير المعتمدة.", "Gouvernance de contenu MODRIK et cadre non approuvé de signalement droit d’auteur/retrait."),
    template: true,
    indexable: false,
    sections: [
      { id: "official", title: localized("Official content is governed", "المحتوى الرسمي محكوم", "Le contenu officiel est gouverné"), paragraphs: [localized("Official curriculum content can only move through authorized Admin/Content Team preparation, validation, review and publication. Learner UGC cannot automatically become official curriculum.", "لا ينتقل محتوى المنهج الرسمي إلا عبر إعداد وتحقق ومراجعة ونشر معتمد من الإدارة/فريق المحتوى. ولا يمكن لمحتوى المتعلم أن يصبح منهجًا رسميًا تلقائيًا.", "Le contenu officiel ne peut passer que par la préparation, validation, revue et publication autorisées par Admin/Équipe contenu. L’UGC apprenant ne peut pas devenir automatiquement contenu officiel.")] },
      { id: "rights", title: localized("Real rights evidence is still an external requirement", "إثبات الحقوق الحقيقية ما زال متطلبًا خارجيًا", "La preuve réelle des droits reste une exigence externe"), paragraphs: [localized("Synthetic fixtures can exercise the workflow, but real curriculum publication remains blocked until exact curriculum identifiers and content-rights evidence are provided and reviewed.", "يمكن للـfixtures الاصطناعية اختبار المسار، لكن نشر المنهج الحقيقي يظل محجوبًا حتى توفير معرّفات المنهج الدقيقة وإثبات حقوق المحتوى ومراجعتها.", "Les fixtures synthétiques peuvent tester le flux, mais la publication de contenu réel reste bloquée jusqu’à fourniture et revue des identifiants exacts et preuves de droits.")] },
      { id: "report", title: localized("Reporting/takedown contact is pending", "جهة الإبلاغ/الإزالة معلقة", "Le contact de signalement/retrait est en attente"), paragraphs: [localized("Do not send copyrighted material, student data or sensitive evidence to an invented address. A final report intake channel, ownership-verification process, response workflow and takedown wording require owner/legal approval.", "لا ترسل مواد محمية بحقوق النشر أو بيانات طالب أو أدلة حساسة إلى عنوان مُخترع. تحتاج قناة استقبال البلاغات النهائية ومسار التحقق من الملكية والاستجابة وصياغة الإزالة إلى اعتماد المالك/القانوني.", "N’envoyez pas de contenu protégé, données élève ou preuves sensibles à une adresse inventée. Le canal final, la vérification de propriété, le traitement et le texte de retrait nécessitent une approbation propriétaire/juridique.")], blockers: ["COPYRIGHT_TAKEDOWN_CONTACT", "PUBLIC_CONTACT", "POLICY_EFFECTIVE_DATE", "POLICY_VERSION"] },
    ],
  },
  accountDeletion: {
    key: "accountDeletion",
    slug: "account-deletion",
    eyebrow: trustEyebrow,
    title: localized("Account deletion guidance", "إرشادات حذف الحساب", "Guide de suppression du compte"),
    summary: localized("What the current backend account lifecycle supports, and which public support/retention details are still pending approval.", "ما يدعمه مسار الحساب الحالي في الخادم، وما يظل معلقًا من تفاصيل الدعم/الاحتفاظ العامة.", "Ce que le cycle de compte Backend actuel prend en charge, et les détails publics de support/conservation encore en attente."),
    seoDescription: localized("MODRIK account deletion guidance with pending support and retention policy details.", "إرشادات حذف حساب مُدرك مع تفاصيل دعم واحتفاظ معلقة.", "Guide de suppression du compte MODRIK avec détails de support et conservation en attente."),
    template: true,
    indexable: false,
    sections: [
      { id: "backend", title: localized("Backend deletion is a protected account action", "الحذف في الخادم إجراء حساب محمي", "La suppression Backend est une action de compte protégée"), paragraphs: [localized("The implemented account lifecycle requires recent production authentication plus explicit DELETE confirmation. It marks the account deleted, removes direct account credentials/contact material from active use, revokes sessions and one-time tokens, and records a redacted security event.", "يتطلب مسار الحساب المنفذ مصادقة إنتاج حديثة مع تأكيد DELETE صريح. ويعلّم الحساب كمحذوف، ويزيل بيانات اعتماد/اتصال الحساب المباشرة من الاستخدام النشط، ويلغي الجلسات والرموز أحادية الاستخدام، ويسجل حدث أمني منزوع البيانات الحساسة.", "Le cycle implémenté exige une authentification de production récente et une confirmation DELETE explicite. Il marque le compte supprimé, retire les identifiants/coordonnées directes de l’usage actif, révoque sessions et jetons à usage unique, et enregistre un événement de sécurité expurgé.")] },
      { id: "retention", title: localized("Hard purge and retention are not decided by engineering", "الحذف النهائي والاحتفاظ ليسا قرارًا هندسيًا", "La purge définitive et la conservation ne sont pas décidées par l’ingénierie"), paragraphs: [localized("Final retention and hard-purge periods remain owner/legal inputs. This guidance must not imply an unapproved deadline.", "تظل فترات الاحتفاظ والحذف النهائي مدخلات للمالك/القانوني. ولا يجب أن توحي هذه الإرشادات بمدة غير معتمدة.", "Les durées finales de conservation et purge restent des entrées propriétaire/juridiques. Ce guide ne doit pas suggérer de délai non approuvé.")], blockers: ["RETENTION_SCHEDULE"] },
      { id: "help", title: localized("Public deletion support channel is pending", "قناة دعم الحذف العامة معلقة", "Le canal public d’aide à la suppression est en attente"), paragraphs: [localized("A final user-facing deletion entry point and approved support/contact route must be confirmed before production publication. This page intentionally does not invent an email address or service promise.", "يجب تأكيد نقطة دخول حذف الحساب للمستخدم وقناة الدعم/التواصل المعتمدة قبل النشر الإنتاجي. لا تخترع هذه الصفحة بريدًا إلكترونيًا أو وعد خدمة.", "Un point d’entrée utilisateur final et un canal de support/contact approuvé doivent être confirmés avant publication en production. Cette page n’invente volontairement ni adresse e-mail ni promesse de service.")], blockers: ["PUBLIC_CONTACT", "SUPPORT_CHANNEL_HOURS"] },
    ],
  },
  support: {
    key: "support",
    slug: "support",
    eyebrow: guideEyebrow,
    title: localized("Support guidance", "إرشادات الدعم", "Guide de support"),
    summary: localized("Self-service guidance is available now; final public support channels, hours and escalation ownership are still owner/operations inputs.", "إرشادات الخدمة الذاتية متاحة الآن؛ أما قنوات الدعم العامة النهائية وساعاتها ومسؤولية التصعيد فما زالت مدخلات للمالك/التشغيل.", "Le guide en libre-service est disponible ; les canaux publics finaux, horaires et responsabilités d’escalade restent des entrées propriétaire/opérations."),
    seoDescription: localized("MODRIK support guidance and explicit pending support-channel blockers.", "إرشادات دعم مُدرك وحواجز قناة الدعم المعلقة بوضوح.", "Guide de support MODRIK et blocages explicites des canaux de support en attente."),
    template: true,
    indexable: false,
    sections: [
      { id: "self-service", title: localized("Start with the learner guide", "ابدأ بدليل المتعلّم", "Commencez par le guide apprenant"), paragraphs: [localized("For study, practice, progress, language or reconnect behavior, use the Learner guide first. Account deletion has its own guidance page, and content/copyright reporting has a separate shell.", "للمذاكرة أو التدريب أو التقدّم أو اللغة أو إعادة الاتصال، ابدأ بدليل المتعلّم. حذف الحساب له صفحة إرشادات مستقلة، والإبلاغ عن المحتوى/حقوق النشر له واجهة منفصلة.", "Pour l’étude, les exercices, la progression, la langue ou la reconnexion, commencez par le Guide apprenant. La suppression de compte et le signalement contenu/droit d’auteur ont leurs pages dédiées.")] },
      { id: "channel", title: localized("No public support address is approved yet", "لا يوجد عنوان دعم عام معتمد بعد", "Aucune adresse publique de support n’est encore approuvée"), paragraphs: [localized("Do not treat repository author emails, GitHub accounts or placeholder values as support contacts. Production channels, hours, response expectations and escalation ownership must be supplied explicitly.", "لا تعتبر بريد مؤلفي المستودع أو حسابات GitHub أو قيم placeholder جهات دعم. يجب توفير قنوات الإنتاج وساعاتها وتوقعات الاستجابة ومسؤولية التصعيد بشكل صريح.", "Ne considérez pas les e-mails d’auteurs du dépôt, comptes GitHub ou valeurs placeholder comme contacts support. Les canaux de production, horaires, attentes de réponse et responsabilités d’escalade doivent être fournis explicitement.")], blockers: ["PUBLIC_CONTACT", "SUPPORT_CHANNEL_HOURS", "SAFETY_ESCALATION_CONTACT"] },
    ],
  },
  contact: {
    key: "contact",
    slug: "contact",
    eyebrow: trustEyebrow,
    title: localized("Contact status", "حالة التواصل", "Statut des contacts"),
    summary: localized("MODRIK's final public support, privacy, legal, safety and copyright contacts have not yet been approved. This page intentionally does not fabricate them.", "لم تُعتمد بعد جهات التواصل العامة النهائية للدعم والخصوصية والقانون والسلامة وحقوق النشر في مُدرك. تتعمد هذه الصفحة عدم اختراعها.", "Les contacts publics finaux de MODRIK pour support, confidentialité, juridique, sécurité et droit d’auteur ne sont pas encore approuvés. Cette page ne les invente volontairement pas."),
    seoDescription: localized("MODRIK contact-status page showing explicit pending public contact blockers.", "صفحة حالة تواصل مُدرك التي تعرض حواجز التواصل العامة المعلقة بوضوح.", "Page d’état des contacts MODRIK présentant les blocages explicites des contacts publics en attente."),
    template: true,
    indexable: false,
    sections: [
      { id: "why", title: localized("Why there is no placeholder email", "لماذا لا يوجد بريد placeholder", "Pourquoi il n’y a pas d’e-mail provisoire"), paragraphs: [localized("Publishing an invented address would misdirect privacy, safety, legal or account requests and would violate the release-input contract. The production contact set must be supplied and approved by the accountable owner before cutover.", "نشر عنوان مُخترع قد يضلل طلبات الخصوصية أو السلامة أو الشؤون القانونية أو الحساب، وسيخالف عقد مدخلات الإصدار. يجب توفير واعتماد مجموعة جهات التواصل الإنتاجية من المالك المسؤول قبل التحويل.", "Publier une adresse inventée détournerait les demandes de confidentialité, sécurité, juridique ou compte et violerait le contrat d’entrées de version. Les contacts de production doivent être fournis et approuvés avant bascule.")], blockers: ["PUBLIC_CONTACT", "SAFETY_ESCALATION_CONTACT", "COPYRIGHT_TAKEDOWN_CONTACT", "SUPPORT_CHANNEL_HOURS"] },
    ],
  },
};

export const publicPageKeys = Object.keys(publicPages) as PublicPageKey[];

export function pageKeyForSlug(slug: string): PublicPageKey | null {
  return publicPageKeys.find((key) => publicPages[key].slug === slug) ?? null;
}

export function publicPath(key: PublicPageKey): string {
  return `/${publicPages[key].slug}`;
}

export function publicHref(key: PublicPageKey, locale: PublicLocale): string {
  const path = publicPath(key);
  return locale === "en" ? path : `${path}?lang=${locale}`;
}

export const publicUi = {
  en: {
    skip: "Skip to main content",
    primaryNavigation: "Public information navigation",
    language: "Language",
    templateTitle: "Draft template — not approved legal text",
    templateBody: "This surface is release scaffolding only. Missing owner/legal facts remain explicit blockers and must be approved before production publication.",
    blockedInputs: "Pending owner-controlled inputs",
    explore: "Explore",
    learnerGuide: "Learner guide",
    about: "About MODRIK",
    safety: "Safety",
    landing: "Home",
    trustLegal: "Trust & legal templates",
    guides: "Guides",
    purpose: "Purpose",
    privacy: "Privacy template",
    terms: "Terms template",
    cookies: "Cookie template",
    disclaimer: "Educational & AI disclaimer",
    contentPolicy: "Content & copyright",
    accountDeletion: "Account deletion",
    support: "Support",
    contact: "Contact status",
    adminGuide: "Admin / Content guide",
    goal: "Goal",
    vision: "Vision",
    mission: "Mission",
    releaseBoundary: "Release boundary",
    releaseBoundaryBody: "This Next.js landing is a release candidate surface only. deploy/coming-soon remains the canonical public shell until an explicit approved cutover and rollback/domain smoke review.",
  },
  ar: {
    skip: "انتقل إلى المحتوى الرئيسي",
    primaryNavigation: "التنقل في المعلومات العامة",
    language: "اللغة",
    templateTitle: "نموذج مسودة — ليس نصًا قانونيًا معتمدًا",
    templateBody: "هذه الواجهة تجهيز للإصدار فقط. تظل حقائق المالك/القانوني المفقودة حواجز صريحة ويجب اعتمادها قبل النشر الإنتاجي.",
    blockedInputs: "مدخلات معلقة يتحكم بها المالك",
    explore: "استكشف",
    learnerGuide: "دليل المتعلّم",
    about: "عن مُدرك",
    safety: "السلامة",
    landing: "الرئيسية",
    trustLegal: "الثقة والنماذج القانونية",
    guides: "الأدلة",
    purpose: "الهدف والاتجاه",
    privacy: "نموذج الخصوصية",
    terms: "نموذج الشروط",
    cookies: "نموذج ملفات الارتباط",
    disclaimer: "إخلاء المسؤولية التعليمي والذكاء الاصطناعي",
    contentPolicy: "المحتوى وحقوق النشر",
    accountDeletion: "حذف الحساب",
    support: "الدعم",
    contact: "حالة التواصل",
    adminGuide: "دليل الإدارة / المحتوى",
    goal: "الهدف",
    vision: "الرؤية",
    mission: "الرسالة",
    releaseBoundary: "حدود الإصدار",
    releaseBoundaryBody: "صفحة Next.js هذه مرشح إصدار فقط. يظل deploy/coming-soon هو الغلاف العام المعتمد حتى تحويل صريح ومعتمد مع مراجعة الاسترجاع وفحوصات الدومين.",
  },
  fr: {
    skip: "Aller au contenu principal",
    primaryNavigation: "Navigation des informations publiques",
    language: "Langue",
    templateTitle: "Modèle brouillon — texte juridique non approuvé",
    templateBody: "Cette surface est uniquement un échafaudage de version. Les faits propriétaire/juridiques manquants restent des blocages explicites à approuver avant publication en production.",
    blockedInputs: "Entrées contrôlées par le propriétaire en attente",
    explore: "Explorer",
    learnerGuide: "Guide apprenant",
    about: "À propos de MODRIK",
    safety: "Sécurité",
    landing: "Accueil",
    trustLegal: "Confiance & modèles juridiques",
    guides: "Guides",
    purpose: "Objectif et direction",
    privacy: "Modèle confidentialité",
    terms: "Modèle conditions",
    cookies: "Modèle cookies",
    disclaimer: "Avertissement éducatif & IA",
    contentPolicy: "Contenu & droit d’auteur",
    accountDeletion: "Suppression du compte",
    support: "Support",
    contact: "Statut des contacts",
    adminGuide: "Guide Admin / Contenu",
    goal: "Objectif",
    vision: "Vision",
    mission: "Mission",
    releaseBoundary: "Limite de version",
    releaseBoundaryBody: "Cette landing Next.js est uniquement une surface candidate. deploy/coming-soon reste le shell public canonique jusqu’à une bascule explicitement approuvée avec revue de rollback et tests du domaine.",
  },
} as const;
