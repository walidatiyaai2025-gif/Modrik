import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/runtime_diagnostic_transport.dart';

void main() {
  test('learning diagnostic operation names strip short dynamic resource IDs', () {
    const lessonId = 'lesson-short-7';
    const attemptId = 'attempt-short-8';
    const questionId = 'question-short-9';

    final operations = [
      diagnosticOperationName('learning', 'GET', 'lessons/$lessonId'),
      diagnosticOperationName('learning', 'GET', 'attempts/$attemptId'),
      diagnosticOperationName(
        'learning',
        'PUT',
        'attempts/$attemptId/answers/$questionId',
      ),
      diagnosticOperationName(
        'learning',
        'POST',
        'attempts/$attemptId/submit',
      ),
    ];

    expect(operations, [
      'learning.get.lessons',
      'learning.get.attempts',
      'learning.put.attempts.answers',
      'learning.post.attempts.submit',
    ]);

    for (final operation in operations) {
      expect(operation, isNot(contains(lessonId)));
      expect(operation, isNot(contains(attemptId)));
      expect(operation, isNot(contains(questionId)));
    }
  });

  test('static non-learning route segments remain available for support triage', () {
    expect(
      diagnosticOperationName(
        'auth',
        'POST',
        'auth/providers/google/login-intents',
      ),
      'auth.post.auth.providers.google',
    );
    expect(
      diagnosticOperationName('sync', 'POST', 'sync/answers'),
      'sync.post.sync.answers',
    );
  });
}
