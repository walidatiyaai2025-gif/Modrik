import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  testWidgets('Flutter renders only Backend-authorized hints and review explanations', (tester) async {
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

    final attempt = Attempt.fromJson({
      'id': 'attempt-policy-1',
      'academic_context_id': 'context-1',
      'quiz_id': 'quiz-1',
      'mode': 'practice',
      'hints_allowed': true,
      'reveal_policy': 'after_submit',
      'status': 'graded',
      'blueprint_version': 1,
      'ordering_algorithm': 'modrik-fy-v1',
      'started_at': '2026-09-20T03:00:00Z',
      'completed_at': '2026-09-20T03:05:00Z',
      'archived_at': null,
      'questions': [
        {
          'attempt_question_id': 'question-1',
          'position': 1,
          'type': 'short_text',
          'prompt': {'en': 'What is 2 + 2?'},
          'response_contract': {'kind': 'short_text', 'max_length': 20},
          'hints': ['Think about two pairs.'],
          'current_answer': {
            'revision': 1,
            'value': '4',
            'answered_at': '2026-09-20T03:04:00Z',
          },
        },
      ],
    });

    final result = AttemptResult.fromJson({
      'attempt': attempt.toJson(),
      'score': 1,
      'max_score': 1,
      'review': [
        {
          'attempt_question_id': 'question-1',
          'position': 1,
          'correct': true,
          'awarded_score': 1,
          'maximum_score': 1,
          'explanation': {'en': 'Two plus two equals four.'},
        },
      ],
    });

    controller
      ..status = MobileViewStatus.ready
      ..section = StudentSection.practice
      ..attempt = attempt
      ..result = result;

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(find.textContaining('practice · graded'), findsOneWidget);
    expect(find.text('Show hint'), findsOneWidget);
    expect(find.textContaining('Correct'), findsOneWidget);
    expect(find.textContaining('Two plus two equals four.'), findsOneWidget);

    final hintToggle = find.text('Show hint');
    await tester.ensureVisible(hintToggle);
    await tester.pumpAndSettle();
    await tester.tap(hintToggle);
    await tester.pumpAndSettle();
    expect(find.text('Think about two pairs.'), findsOneWidget);
  });

  test('Flutter preserves Backend exam hint suppression', () {
    final attempt = Attempt.fromJson({
      'id': 'attempt-exam-1',
      'academic_context_id': 'context-1',
      'quiz_id': 'quiz-exam',
      'mode': 'exam',
      'hints_allowed': false,
      'reveal_policy': 'after_submit',
      'status': 'in_progress',
      'blueprint_version': 1,
      'ordering_algorithm': 'modrik-fy-v1',
      'started_at': '2026-09-20T03:00:00Z',
      'completed_at': null,
      'archived_at': null,
      'questions': [
        {
          'attempt_question_id': 'question-exam',
          'position': 1,
          'type': 'short_text',
          'prompt': {'en': 'Exam question'},
          'response_contract': {'kind': 'short_text', 'max_length': 20},
          'hints': [],
          'current_answer': null,
        },
      ],
    });

    expect(attempt.mode, 'exam');
    expect(attempt.hintsAllowed, isFalse);
    expect(attempt.questions.single.hints, isEmpty);
  });
}
