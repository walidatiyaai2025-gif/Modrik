import 'dart:collection';

import 'models.dart';

class AdaptiveFeatureState {
  const AdaptiveFeatureState({
    required this.state,
    required this.effective,
  });

  factory AdaptiveFeatureState.fromJson(Object? value) {
    final json = value is Map
        ? Map<String, dynamic>.from(value)
        : <String, dynamic>{};
    return AdaptiveFeatureState(
      state: json['state'] as String? ?? 'disabled',
      effective: json['effective'] == true,
    );
  }

  final String state;
  final bool effective;
}

class AdaptiveAssessmentTarget {
  const AdaptiveAssessmentTarget({
    required this.id,
    required this.kind,
    required this.title,
    required this.availableQuestionCount,
  });

  factory AdaptiveAssessmentTarget.fromJson(Object? value) {
    final json = value is Map
        ? Map<String, dynamic>.from(value)
        : <String, dynamic>{};
    return AdaptiveAssessmentTarget(
      id: json['id'] as String?,
      kind: json['kind'] as String?,
      title: localizedTextFromJson(json['title']),
      availableQuestionCount:
          (json['available_question_count'] as num? ?? 0).toInt(),
    );
  }

  final String? id;
  final String? kind;
  final LocalizedText title;
  final int availableQuestionCount;

  bool get isAvailable =>
      id != null &&
      id!.isNotEmpty &&
      availableQuestionCount > 0 &&
      (kind == 'practice' || kind == 'quiz');
}

class AdaptiveStudyTarget {
  const AdaptiveStudyTarget({
    required this.skillId,
    required this.skillReference,
    required this.skillTitle,
    required this.subjectId,
    required this.subjectReference,
    required this.subjectTitle,
    required this.assessment,
    this.displayBand,
    this.scorePercent,
    this.sourceType,
    this.reason,
    this.latestWrongAt,
  });

  factory AdaptiveStudyTarget.fromJson(Map<String, dynamic> json) {
    final subject = json['subject'] is Map
        ? Map<String, dynamic>.from(json['subject'] as Map)
        : <String, dynamic>{};

    return AdaptiveStudyTarget(
      skillId: json['skill_id'] as String? ?? '',
      skillReference: json['skill_reference'] as String? ?? '',
      skillTitle: localizedTextFromJson(json['skill_title']),
      subjectId: subject['id'] as String? ?? '',
      subjectReference: subject['reference'] as String? ?? '',
      subjectTitle: localizedTextFromJson(subject['title']),
      assessment: AdaptiveAssessmentTarget.fromJson(json['assessment']),
      displayBand: json['display_band'] as String?,
      scorePercent: (json['score_percent'] as num?)?.toDouble(),
      sourceType: json['source_type'] as String?,
      reason: json['reason'] as String?,
      latestWrongAt: json['latest_wrong_at'] as String?,
    );
  }

  final String skillId;
  final String skillReference;
  final LocalizedText skillTitle;
  final String subjectId;
  final String subjectReference;
  final LocalizedText subjectTitle;
  final AdaptiveAssessmentTarget assessment;
  final String? displayBand;
  final double? scorePercent;
  final String? sourceType;
  final String? reason;
  final String? latestWrongAt;
}

class AdaptiveStudySurface {
  const AdaptiveStudySurface({
    required this.status,
    required this.reason,
    required this.items,
  });

  factory AdaptiveStudySurface.fromJson(
    Object? value, {
    required String itemKey,
  }) {
    final json = value is Map
        ? Map<String, dynamic>.from(value)
        : <String, dynamic>{};
    final rawItems = json[itemKey] as List<dynamic>? ??
        json['items'] as List<dynamic>? ??
        const <dynamic>[];

    return AdaptiveStudySurface(
      status: json['status'] as String? ?? 'empty',
      reason: json['reason'] as String?,
      items: List<AdaptiveStudyTarget>.unmodifiable(
        rawItems.whereType<Map>().map(
              (item) => AdaptiveStudyTarget.fromJson(
                Map<String, dynamic>.from(item),
              ),
            ),
      ),
    );
  }

  final String status;
  final String? reason;
  final List<AdaptiveStudyTarget> items;

  bool get isDisabled => status == 'disabled';
  bool get isDegraded => status == 'degraded';
  bool get isEmpty => items.isEmpty;
}

class AdaptiveStudySnapshot {
  const AdaptiveStudySnapshot({
    required this.state,
    required this.dailyPlan,
    required this.mistakeNotebook,
    required this.todayMission,
    required this.needsPractice,
    required this.mistakes,
  });

  factory AdaptiveStudySnapshot.fromJson(Map<String, dynamic> json) {
    final features = json['features'] is Map
        ? Map<String, dynamic>.from(json['features'] as Map)
        : <String, dynamic>{};

    return AdaptiveStudySnapshot(
      state: json['state'] as String? ?? 'onboarding_required',
      dailyPlan: AdaptiveFeatureState.fromJson(features['daily_plan']),
      mistakeNotebook:
          AdaptiveFeatureState.fromJson(features['mistake_notebook']),
      todayMission: AdaptiveStudySurface.fromJson(
        json['today_mission'],
        itemKey: 'selected',
      ),
      needsPractice: AdaptiveStudySurface.fromJson(
        json['needs_practice'],
        itemKey: 'items',
      ),
      mistakes: AdaptiveStudySurface.fromJson(
        json['mistakes'],
        itemKey: 'items',
      ),
    );
  }

  final String state;
  final AdaptiveFeatureState dailyPlan;
  final AdaptiveFeatureState mistakeNotebook;
  final AdaptiveStudySurface todayMission;
  final AdaptiveStudySurface needsPractice;
  final AdaptiveStudySurface mistakes;

  bool get isActive => state == 'active';
}

List<AdaptiveStudyTarget> immutableAdaptiveTargets(
  Iterable<AdaptiveStudyTarget> values,
) =>
    UnmodifiableListView(values.toList(growable: false));
