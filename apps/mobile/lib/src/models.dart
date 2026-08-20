import 'dart:collection';

enum ModrikLocale {
  ar('ar'),
  en('en'),
  fr('fr');

  const ModrikLocale(this.code);
  final String code;

  static ModrikLocale fromCode(Object? value) {
    return values.firstWhere(
      (locale) => locale.code == value,
      orElse: () => ModrikLocale.en,
    );
  }
}

typedef LocalizedText = Map<ModrikLocale, String>;

Object? freezeJsonValue(Object? value) {
  if (value == null || value is String || value is bool || value is num) {
    return value;
  }
  if (value is List) {
    return List<Object?>.unmodifiable(value.map(freezeJsonValue));
  }
  if (value is Map) {
    final frozen = <String, Object?>{};
    for (final entry in value.entries) {
      final key = entry.key;
      if (key is! String) {
        throw ArgumentError.value(value, 'value', 'JSON object keys must be strings.');
      }
      frozen[key] = freezeJsonValue(entry.value);
    }
    return Map<String, Object?>.unmodifiable(frozen);
  }
  throw ArgumentError.value(value, 'value', 'Answer values must be JSON-compatible.');
}

bool jsonValueEquals(Object? left, Object? right) {
  if (identical(left, right)) return true;
  if (left is List && right is List) {
    if (left.length != right.length) return false;
    for (var index = 0; index < left.length; index++) {
      if (!jsonValueEquals(left[index], right[index])) return false;
    }
    return true;
  }
  if (left is Map && right is Map) {
    if (left.length != right.length) return false;
    for (final entry in left.entries) {
      if (!right.containsKey(entry.key) ||
          !jsonValueEquals(entry.value, right[entry.key])) {
        return false;
      }
    }
    return true;
  }
  return left == right;
}

LocalizedText localizedTextFromJson(Object? value) {
  final source = value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
  return UnmodifiableMapView({
    for (final locale in ModrikLocale.values)
      if (source[locale.code] is String) locale: source[locale.code] as String,
  });
}

Map<String, String> localizedTextToJson(LocalizedText value) => {
      for (final entry in value.entries) entry.key.code: entry.value,
    };

String localize(LocalizedText value, ModrikLocale locale) {
  return value[locale] ??
      value[ModrikLocale.en] ??
      value[ModrikLocale.ar] ??
      value[ModrikLocale.fr] ??
      '';
}

class Session {
  const Session({required this.userId, required this.locale, required this.roles});

  factory Session.fromJson(Map<String, dynamic> json) => Session(
        userId: json['user_id'] as String,
        locale: ModrikLocale.fromCode(json['locale']),
        roles: List<String>.unmodifiable(
          (json['roles'] as List<dynamic>? ?? const []).whereType<String>(),
        ),
      );

  final String userId;
  final ModrikLocale locale;
  final List<String> roles;
}

class AcademicContext {
  const AcademicContext._({
    required this.state,
    this.contextId,
    this.academicTrackId,
    this.yearLevel,
    this.activatedAt,
  });

  factory AcademicContext.fromJson(Map<String, dynamic> json) {
    final state = json['state'] as String? ?? 'onboarding_required';
    if (state != 'active') {
      return const AcademicContext._(state: 'onboarding_required');
    }
    return AcademicContext._(
      state: state,
      contextId: json['context_id'] as String?,
      academicTrackId: json['academic_track_id'] as String?,
      yearLevel: json['year_level'] as String?,
      activatedAt: json['activated_at'] as String?,
    );
  }

  final String state;
  final String? contextId;
  final String? academicTrackId;
  final String? yearLevel;
  final String? activatedAt;

  bool get requiresOnboarding => state != 'active';
}

class LessonBlock {
  const LessonBlock({
    required this.id,
    required this.position,
    required this.type,
    required this.content,
  });

  factory LessonBlock.fromJson(Map<String, dynamic> json) => LessonBlock(
        id: json['id'] as String,
        position: (json['position'] as num).toInt(),
        type: json['type'] as String,
        content: localizedTextFromJson(json['content']),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'position': position,
        'type': type,
        'content': localizedTextToJson(content),
      };

  final String id;
  final int position;
  final String type;
  final LocalizedText content;
}

class Lesson {
  const Lesson({
    required this.id,
    required this.curriculumNodeId,
    required this.contentVersion,
    required this.title,
    required this.practiceQuizId,
    required this.blocks,
  });

  factory Lesson.fromJson(Map<String, dynamic> json) => Lesson(
        id: json['id'] as String,
        curriculumNodeId: json['curriculum_node_id'] as String,
        contentVersion: (json['content_version'] as num).toInt(),
        title: localizedTextFromJson(json['title']),
        practiceQuizId: json['practice_quiz_id'] as String,
        blocks: List<LessonBlock>.unmodifiable(
          (json['blocks'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map((item) => LessonBlock.fromJson(Map<String, dynamic>.from(item))),
        ),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'curriculum_node_id': curriculumNodeId,
        'content_version': contentVersion,
        'title': localizedTextToJson(title),
        'practice_quiz_id': practiceQuizId,
        'blocks': blocks.map((block) => block.toJson()).toList(growable: false),
      };

  final String id;
  final String curriculumNodeId;
  final int contentVersion;
  final LocalizedText title;
  final String practiceQuizId;
  final List<LessonBlock> blocks;
}

class ChoiceOption {
  const ChoiceOption({required this.id, required this.label});

  factory ChoiceOption.fromJson(Map<String, dynamic> json) => ChoiceOption(
        id: json['id'] as String,
        label: localizedTextFromJson(json['label']),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'label': localizedTextToJson(label),
      };

  final String id;
  final LocalizedText label;
}

class ResponseContract {
  const ResponseContract({required this.kind, this.options = const [], this.maxLength});

  factory ResponseContract.fromJson(Map<String, dynamic> json) => ResponseContract(
        kind: json['kind'] as String,
        options: List<ChoiceOption>.unmodifiable(
          (json['options'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map((item) => ChoiceOption.fromJson(Map<String, dynamic>.from(item))),
        ),
        maxLength: (json['max_length'] as num?)?.toInt(),
      );

  Map<String, dynamic> toJson() => {
        'kind': kind,
        if (options.isNotEmpty) 'options': options.map((option) => option.toJson()).toList(growable: false),
        if (maxLength != null) 'max_length': maxLength,
      };

  final String kind;
  final List<ChoiceOption> options;
  final int? maxLength;
}

class CurrentAnswer {
  CurrentAnswer({
    required this.revision,
    required Object? value,
    required this.answeredAt,
  }) : value = freezeJsonValue(value);

  factory CurrentAnswer.fromJson(Map<String, dynamic> json) => CurrentAnswer(
        revision: (json['revision'] as num).toInt(),
        value: json['value'],
        answeredAt: json['answered_at'] as String,
      );

  Map<String, dynamic> toJson() => {
        'revision': revision,
        'value': value,
        'answered_at': answeredAt,
      };

  final int revision;
  final Object? value;
  final String answeredAt;
}

class AttemptQuestion {
  const AttemptQuestion({
    required this.attemptQuestionId,
    required this.position,
    required this.type,
    required this.prompt,
    required this.responseContract,
    required this.currentAnswer,
  });

  factory AttemptQuestion.fromJson(Map<String, dynamic> json) => AttemptQuestion(
        attemptQuestionId: json['attempt_question_id'] as String,
        position: (json['position'] as num).toInt(),
        type: json['type'] as String,
        prompt: localizedTextFromJson(json['prompt']),
        responseContract: ResponseContract.fromJson(
          Map<String, dynamic>.from(json['response_contract'] as Map),
        ),
        currentAnswer: json['current_answer'] is Map
            ? CurrentAnswer.fromJson(Map<String, dynamic>.from(json['current_answer'] as Map))
            : null,
      );

  Map<String, dynamic> toJson() => {
        'attempt_question_id': attemptQuestionId,
        'position': position,
        'type': type,
        'prompt': localizedTextToJson(prompt),
        'response_contract': responseContract.toJson(),
        'current_answer': currentAnswer?.toJson(),
      };

  final String attemptQuestionId;
  final int position;
  final String type;
  final LocalizedText prompt;
  final ResponseContract responseContract;
  final CurrentAnswer? currentAnswer;
}

class Attempt {
  const Attempt({
    required this.id,
    required this.academicContextId,
    required this.quizId,
    required this.status,
    required this.blueprintVersion,
    required this.orderingAlgorithm,
    required this.startedAt,
    required this.completedAt,
    required this.archivedAt,
    required this.questions,
  });

  factory Attempt.fromJson(Map<String, dynamic> json) {
    // The order in this list is an immutable server-owned attempt snapshot.
    // Never sort, shuffle or rebuild it on the client.
    final questions = (json['questions'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => AttemptQuestion.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);
    return Attempt(
      id: json['id'] as String,
      academicContextId: json['academic_context_id'] as String,
      quizId: json['quiz_id'] as String,
      status: json['status'] as String,
      blueprintVersion: (json['blueprint_version'] as num).toInt(),
      orderingAlgorithm: json['ordering_algorithm'] as String? ?? '',
      startedAt: json['started_at'] as String,
      completedAt: json['completed_at'] as String?,
      archivedAt: json['archived_at'] as String?,
      questions: List<AttemptQuestion>.unmodifiable(questions),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'academic_context_id': academicContextId,
        'quiz_id': quizId,
        'status': status,
        'blueprint_version': blueprintVersion,
        'ordering_algorithm': orderingAlgorithm,
        'started_at': startedAt,
        'completed_at': completedAt,
        'archived_at': archivedAt,
        'questions': questions.map((question) => question.toJson()).toList(growable: false),
      };

  final String id;
  final String academicContextId;
  final String quizId;
  final String status;
  final int blueprintVersion;
  final String orderingAlgorithm;
  final String startedAt;
  final String? completedAt;
  final String? archivedAt;
  final List<AttemptQuestion> questions;
}

class AttemptResult {
  const AttemptResult({required this.attempt, required this.score, required this.maxScore});

  factory AttemptResult.fromJson(Map<String, dynamic> json) => AttemptResult(
        attempt: Attempt.fromJson(Map<String, dynamic>.from(json['attempt'] as Map)),
        score: (json['score'] as num).toDouble(),
        maxScore: (json['max_score'] as num).toDouble(),
      );

  final Attempt attempt;
  final double score;
  final double maxScore;
}

class ProgressSnapshot {
  const ProgressSnapshot({
    required this.academicContextId,
    required this.curriculumNodeId,
    required this.mastery,
    required this.sourceVersion,
    required this.calculatedAt,
  });

  factory ProgressSnapshot.fromJson(Map<String, dynamic> json) => ProgressSnapshot(
        academicContextId: json['academic_context_id'] as String,
        curriculumNodeId: json['curriculum_node_id'] as String,
        mastery: (json['mastery'] as num).toDouble(),
        sourceVersion: (json['source_version'] as num).toInt(),
        calculatedAt: json['calculated_at'] as String,
      );

  final String academicContextId;
  final String curriculumNodeId;
  final double mastery;
  final int sourceVersion;
  final String calculatedAt;
}
