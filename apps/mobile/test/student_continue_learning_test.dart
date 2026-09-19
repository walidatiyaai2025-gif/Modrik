import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  testWidgets('Flutter dashboard resumes the exact cached attempt through Continue Learning', (tester) async {
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

    controller
      ..status = MobileViewStatus.offline
      ..section = StudentSection.dashboard
      ..academicContext = AcademicContext.fromJson({
        'state': 'active',
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'year_level': 'Year 7',
        'activated_at': '2026-08-20T10:00:00Z',
      })
      ..attempt = Attempt.fromJson({
        'id': 'attempt-continue-1',
        'academic_context_id': 'context-1',
        'quiz_id': 'quiz-1',
        'status': 'in_progress',
        'blueprint_version': 1,
        'ordering_algorithm': 'backend-order',
        'started_at': '2026-09-19T18:00:00Z',
        'completed_at': null,
        'archived_at': null,
        'questions': [],
      });

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(find.text('Continue Learning'), findsOneWidget);
    expect(find.text('Resume this attempt'), findsOneWidget);
    expect(find.byIcon(Icons.play_circle_outline), findsOneWidget);

    await tester.tap(find.text('Resume this attempt'));
    await tester.pump();

    expect(controller.section, StudentSection.practice);
    expect(controller.messageCode, 'resume_cached_attempt');
  });

  testWidgets('Flutter dashboard does not invent Continue Learning without an attempt', (tester) async {
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
        'activated_at': '2026-08-20T10:00:00Z',
      });

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(find.text('Continue Learning'), findsNothing);
    expect(find.text('Resume this attempt'), findsNothing);
  });
}
