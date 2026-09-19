import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  testWidgets('Flutter preserves Backend multi-select and boolean answer types', (tester) async {
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

    controller
      ..status = MobileViewStatus.ready
      ..section = StudentSection.practice
      ..academicContext = AcademicContext.fromJson({
        'state': 'active',
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'year_level': 'Year 7',
        'activated_at': '2026-08-20T10:00:00Z',
      })
      ..attempt = Attempt.fromJson({
        'id': 'attempt-structured-1',
        'academic_context_id': 'context-1',
        'quiz_id': 'quiz-1',
        'status': 'in_progress',
        'blueprint_version': 1,
        'ordering_algorithm': 'backend-order',
        'started_at': '2026-09-19T20:00:00Z',
        'completed_at': null,
        'archived_at': null,
        'questions': [
          {
            'attempt_question_id': 'question-multi',
            'position': 1,
            'type': 'multi_select',
            'prompt': {'en': 'Choose all correct options'},
            'response_contract': {
              'kind': 'multi_select',
              'options': [
                {'id': 'option-a', 'label': {'en': 'Alpha'}},
                {'id': 'option-b', 'label': {'en': 'Beta'}},
              ],
            },
            'current_answer': null,
          },
          {
            'attempt_question_id': 'question-bool',
            'position': 2,
            'type': 'true_false',
            'prompt': {'en': 'This statement is false'},
            'response_contract': {'kind': 'boolean'},
            'current_answer': null,
          },
        ],
      });

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(find.text('Alpha'), findsOneWidget);
    expect(find.text('Beta'), findsOneWidget);
    expect(find.text('True'), findsOneWidget);
    expect(find.text('False'), findsOneWidget);

    await tester.tap(find.text('Alpha'));
    await tester.pump();
    await tester.tap(find.text('Beta'));
    await tester.pump();
    await tester.tap(find.text('False'));
    await tester.pump();

    expect(controller.answers['question-multi'], ['option-a', 'option-b']);
    expect(controller.answers['question-bool'], false);
    expect(controller.answers['question-bool'], isA<bool>());
  });

  testWidgets('Flutter hydrates a missing multi-select answer as an empty list, not text', (tester) async {
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
        'id': 'attempt-structured-2',
        'academic_context_id': 'context-1',
        'quiz_id': 'quiz-1',
        'status': 'in_progress',
        'blueprint_version': 1,
        'ordering_algorithm': 'backend-order',
        'started_at': '2026-09-19T20:00:00Z',
        'completed_at': null,
        'archived_at': null,
        'questions': [
          {
            'attempt_question_id': 'question-multi',
            'position': 1,
            'type': 'multi_select',
            'prompt': {'en': 'Choose options'},
            'response_contract': {
              'kind': 'multi_select',
              'options': [
                {'id': 'option-a', 'label': {'en': 'Alpha'}},
              ],
            },
            'current_answer': null,
          },
        ],
      });

    await controller.resumeAttempt();

    expect(controller.answers['question-multi'], isA<List>());
    expect(controller.answers['question-multi'], isEmpty);
  });
}
