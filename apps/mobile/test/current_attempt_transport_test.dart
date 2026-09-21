import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';

void main() {
  test('Flutter current-attempt transport consumes the Backend authority endpoint', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      expect(request.method, 'GET');
      expect(request.uri.path, '/api/v1/attempts/current');
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {
          'attempt': _attemptJson('attempt-server-current'),
        },
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
      );

      final attempt = await gateway.currentAttempt();

      expect(attempt?.id, 'attempt-server-current');
      expect(attempt?.questions.map((question) => question.attemptQuestionId), [
        'q-server-second',
        'q-server-first',
      ]);
    } finally {
      await server.close(force: true);
    }
  });

  test('Flutter current-attempt transport preserves authoritative null', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      expect(request.method, 'GET');
      expect(request.uri.path, '/api/v1/attempts/current');
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {'attempt': null},
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
      );

      expect(await gateway.currentAttempt(), isNull);
    } finally {
      await server.close(force: true);
    }
  });
}

Map<String, dynamic> _attemptJson(String id) => {
      'id': id,
      'academic_context_id': 'context-1',
      'quiz_id': 'quiz-1',
      'status': 'in_progress',
      'blueprint_version': 1,
      'ordering_algorithm': 'backend-owned',
      'started_at': '2026-09-21T10:00:00Z',
      'completed_at': null,
      'archived_at': null,
      'questions': [
        {
          'attempt_question_id': 'q-server-second',
          'position': 2,
          'type': 'single_choice',
          'prompt': {'en': 'Second by position'},
          'response_contract': {
            'kind': 'single_choice',
            'options': [
              {'id': 'b', 'label': {'en': 'B'}},
              {'id': 'a', 'label': {'en': 'A'}},
            ],
          },
          'current_answer': null,
        },
        {
          'attempt_question_id': 'q-server-first',
          'position': 1,
          'type': 'short_text',
          'prompt': {'en': 'First by position'},
          'response_contract': {'kind': 'short_text', 'max_length': 120},
          'current_answer': null,
        },
      ],
    };
