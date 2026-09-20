import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/student_adaptive_study.dart';

void main() {
  testWidgets(
    'Flutter dashboard renders Backend adaptive mission needs-practice and mistakes',
    (tester) async {
      final controller = MobileLearningController(
        gateway: const UnconfiguredLearningGateway(),
        config: MobileBootstrapConfig(
          apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
        ),
      );

      controller
        ..status = MobileViewStatus.ready
        ..section = StudentSection.dashboard
        ..academicContext = AcademicContext.fromJson({
          'state': 'active',
          'context_id': 'context-1',
          'academic_track_id': 'track-1',
          'year_level': 'Year 7',
          'activated_at': '2026-09-20T10:00:00Z',
        })
        ..adaptiveStudy = AdaptiveStudySnapshot.fromJson({
          'state': 'active',
          'features': {
            'daily_plan': {'state': 'enabled', 'effective': true},
            'mistake_notebook': {'state': 'enabled', 'effective': true},
          },
          'today_mission': {
            'status': 'ready',
            'selected': [
              _target(
                skillId: 'skill-mission',
                skill: 'Fractions mission',
                assessmentId: 'quiz-mission',
              ),
            ],
          },
          'needs_practice': {
            'status': 'ready',
            'items': [
              _target(
                skillId: 'skill-practice',
                skill: 'Decimals practice',
                assessmentId: 'quiz-practice',
                scorePercent: 42,
              ),
            ],
          },
          'mistakes': {
            'status': 'ready',
            'items': [
              _target(
                skillId: 'skill-mistake',
                skill: 'Algebra mistake',
                assessmentId: 'quiz-mistake',
              ),
            ],
          },
        });

      await tester.pumpWidget(
        ModrikApp(controller: controller, autoInitialize: false),
      );

      expect(find.text("Today's Mission"), findsOneWidget);
      expect(find.text('Needs Practice'), findsOneWidget);
      expect(find.text('My Mistakes'), findsOneWidget);
      expect(find.text('Fractions mission'), findsOneWidget);
      expect(find.text('Decimals practice'), findsOneWidget);
      expect(find.text('Algebra mistake'), findsOneWidget);
      expect(find.text('42%'), findsOneWidget);
      expect(find.text('Open practice'), findsNWidgets(3));
    },
  );

  testWidgets('Flutter adaptive Home preserves disabled and empty truth states',
      (tester) async {
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

    controller
      ..status = MobileViewStatus.ready
      ..section = StudentSection.dashboard
      ..academicContext = AcademicContext.fromJson({
        'state': 'active',
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'year_level': 'Year 7',
        'activated_at': '2026-09-20T10:00:00Z',
      })
      ..adaptiveStudy = AdaptiveStudySnapshot.fromJson({
        'state': 'active',
        'features': {
          'daily_plan': {'state': 'disabled', 'effective': false},
          'mistake_notebook': {'state': 'disabled', 'effective': false},
        },
        'today_mission': {
          'status': 'disabled',
          'reason': 'feature_disabled',
          'items': [],
        },
        'needs_practice': {
          'status': 'empty',
          'items': [],
        },
        'mistakes': {
          'status': 'disabled',
          'reason': 'feature_disabled',
          'items': [],
        },
      });

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(
      find.text('This adaptive feature is not enabled for your learning context.'),
      findsNWidgets(2),
    );
    expect(
      find.text('No practiced skill currently needs extra work.'),
      findsOneWidget,
    );
    expect(find.text('Open practice'), findsNothing);
  });

  test('adaptive target remains non-actionable without a Backend assessment',
      () {
    final snapshot = AdaptiveStudySnapshot.fromJson({
      'state': 'active',
      'features': {
        'daily_plan': {'state': 'enabled', 'effective': true},
        'mistake_notebook': {'state': 'enabled', 'effective': true},
      },
      'today_mission': {
        'status': 'degraded',
        'selected': [
          _target(
            skillId: 'skill-unavailable',
            skill: 'Unavailable skill',
            assessmentId: null,
            availableQuestionCount: 0,
          ),
        ],
      },
      'needs_practice': {'status': 'empty', 'items': []},
      'mistakes': {'status': 'empty', 'items': []},
    });

    expect(snapshot.todayMission.items.single.assessment.isAvailable, isFalse);
    expect(snapshot.todayMission.isDegraded, isTrue);
  });
}

Map<String, Object?> _target({
  required String skillId,
  required String skill,
  required String? assessmentId,
  double? scorePercent,
  int availableQuestionCount = 1,
}) {
  return {
    'skill_id': skillId,
    'skill_reference': 'SKILL:$skillId',
    'skill_title': {'en': skill},
    'subject': {
      'id': 'subject-1',
      'reference': 'SUBJECT:MATH',
      'title': {'en': 'Mathematics'},
    },
    'assessment': {
      'id': assessmentId,
      'kind': assessmentId == null ? null : 'practice',
      'title': assessmentId == null ? null : {'en': '$skill practice'},
      'available_question_count': availableQuestionCount,
    },
    if (scorePercent != null) 'score_percent': scorePercent,
  };
}
